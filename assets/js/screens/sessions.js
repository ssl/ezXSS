window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util;

  var DASH = "-";
  var MID = "\u00b7";
  var PER_PAGE = 50;
  var POLL_MS = 2500;
  var LIVE_WINDOW = 45;

  var SCOPES = [
    { key: "page",     label: "This page" },
    { key: "all",      label: "All",                           scope: {} },
    { key: "online",   label: "Online now",                    scope: { seen_within: LIVE_WINDOW } },
    { key: "m5",       label: "Active \u00b7 last 5 min",      scope: { seen_within: 300 } },
    { key: "h1",       label: "Active \u00b7 last 1 hour",     scope: { seen_within: 3600 } },
    { key: "stale",    label: "Stale \u00b7 over 1 hour",      scope: { seen_before: 3600 } },
    { key: "inactive", label: "Inactive \u00b7 over 7 days",   scope: { seen_before: 604800 } }
  ];

  var listState = { archive: 0, payload: "", search: "", page: 1 };
  var currentId = null;
  var pollTimer = null;
  var lastConsole = null;
  var lastPingUnix = null;
  var pingTickTimer = null;
  var currentPollMs = POLL_MS;
  var wired = false;
  var mounted = false;
  var selection = {};
  var scopeSel = null;
  var scopeCountToken = 0;
  var payloadOptions = null;

  function activeMode() { return scopeSel ? "scope" : "ids"; }

  function el(id) { return EZ.dom.byId(id); }

  function isLive(unix) {
    if (unix == null || unix === "") { return false; }
    return (Math.floor(Date.now() / 1000) - Number(unix)) <= LIVE_WINDOW;
  }

  function loadList() {
    var box = el("sess-list");
    if (!box) { return; }
    box.innerHTML = EZ.ui.loading();

    var q = "?archive=" + listState.archive +
      "&page=" + listState.page +
      "&per_page=" + PER_PAGE +
      (listState.payload ? "&payload=" + encodeURIComponent(listState.payload) : "") +
      (listState.search ? "&search=" + encodeURIComponent(listState.search) : "");

    EZ.api.get("/sessions" + q).then(function (res) {
      var meta = (res.meta && res.meta.pagination) || {};
      paintList(res.data || []);
      paintPager(meta);
      var note = el("sess-catchall-note");
      if (note) { note.hidden = !(listState.payload === "__catchall__" && (Number(meta.total) || 0) > 0); }
    }).catch(function () {
      box.innerHTML = '<p class="field-hint" style="padding:var(--space-sm)">Could not load sessions.</p>';
      paintPager({});
    });
  }

  function paintList(rows) {
    var box = el("sess-list");
    if (!box) { return; }
    if (!rows.length) {
      box.innerHTML = '<p class="field-hint" style="padding:var(--space-sm)">' +
        (listState.search ? "No sessions match your search." :
          (listState.payload ? "No sessions match this payload." :
            (listState.archive ? "No archived sessions." : "No active sessions yet."))) + "</p>";
      syncWithbar();
      return;
    }
    box.innerHTML = rows.map(function (r) {
      var id = String(r.id);
      var active = id === String(currentId) ? " is-active" : "";
      var checked = selection[id] ? " checked" : "";
      var req = (r.requests == null ? "?" : r.requests) + " req";
      var host = [r.origin || "(no origin)", r.browser || "Unknown", req]
        .map(function (s) { return util.escapeHtml(String(s)); }).join(" " + MID + " ");
      var flag = isLive(r.last)
        ? '<span class="live"><span class="dot"></span>live</span>'
        : '<span class="tag tag--muted" style="font-size:11px">' + util.escapeHtml(EZ.format.fmtAgo(r.last)) + "</span>";
      return '<div class="session-item' + active + '" data-sess-id="' + util.escapeHtml(id) + '" role="button" tabindex="0">' +
        '<label class="chkbox session-item__check" aria-label="Select session ' + util.escapeHtml(id) + '">' +
          '<input type="checkbox" class="chkbox" data-sess-check value="' + util.escapeHtml(id) + '"' + checked + ">" +
          '<span class="chkbox__box"></span>' +
        "</label>" +
        '<span class="session-item__body">' +
          '<span class="session-item__top"><span class="session-item__id">' + util.escapeHtml(r.client_id || DASH) + "</span>" + flag + "</span>" +
          '<span class="session-item__host">' + host + "</span>" +
        "</span>" +
        "</div>";
    }).join("");

    dom.qsa("[data-sess-id]", box).forEach(function (b) {
      dom.on(b, "click", function (e) {
        if (e.target.closest(".session-item__check")) { return; }
        EZ.router.navigate("sessions", { id: b.getAttribute("data-sess-id") });
      });
      dom.on(b, "keydown", function (e) {
        if (e.target.closest(".session-item__check")) { return; }
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          EZ.router.navigate("sessions", { id: b.getAttribute("data-sess-id") });
        }
      });
    });
    dom.qsa("input[data-sess-check]", box).forEach(function (c) {
      dom.on(c, "change", function () {
        if (c.checked) { selection[c.value] = true; } else { delete selection[c.value]; }
        scopeSel = null;
        syncScopeMenu();
        syncWithbar();
      });
      dom.on(c, "click", function (e) { e.stopPropagation(); });
    });
    dom.qsa(".session-item__check", box).forEach(function (lbl) {
      dom.on(lbl, "click", function (e) { e.stopPropagation(); });
    });
    syncWithbar();
  }

  function paintPager(meta) {
    EZ.ui.renderPager(el("sess-pager"), meta, function (p) {
      listState.page = p;
      clearSelection();
      loadList();
    });
  }

  function clearSelection() {
    selection = {};
    scopeSel = null;
    syncScopeMenu();
    syncWithbar();
  }

  function selectedIds() { return Object.keys(selection); }

  function pageCheckboxes() { return dom.qsa("#sess-list input[data-sess-check]"); }

  function pageAllSelected() {
    var boxes = pageCheckboxes();
    return boxes.length > 0 && boxes.every(function (c) { return c.checked; });
  }

  function syncWithbar() {
    var bar = el("sess-withbar");
    if (!bar) { return; }

    if (activeMode() === "scope") {
      bar.hidden = false;
      renderWithbarActions();
      var slabel = el("sess-withbar-label");
      if (slabel) { slabel.textContent = scopeSel.matched + " selected " + DASH + " " + scopeSel.label; }
      return;
    }

    var ids = selectedIds();
    bar.hidden = ids.length === 0;
    if (ids.length) {
      renderWithbarActions();
      var label = el("sess-withbar-label");
      if (label) { label.textContent = ids.length + " selected:"; }
    }
  }

  function renderScopeMenu() {
    var menu = el("sess-scope-menu");
    if (!menu) { return; }
    menu.innerHTML = SCOPES.map(function (s) {
      var checked = (scopeSel && scopeSel.key === s.key) ? "true" :
        (!scopeSel && s.key === "page" && pageAllSelected() ? "true" : "false");
      return '<button class="menu__link sess-scope__opt" type="button" role="menuitemradio"' +
        ' data-scope="' + s.key + '" aria-checked="' + checked + '">' +
        '<span>' + util.escapeHtml(s.label) + "</span>" +
        '<span class="sess-scope__count" data-scope-count="' + s.key + '"></span>' +
        "</button>";
    }).join("");
    dom.qsa("[data-scope]", menu).forEach(function (b) {
      dom.on(b, "click", function () { selectScope(b.getAttribute("data-scope")); });
    });
    paintScopeCount("page", String(pageCheckboxes().length));
    loadScopeCounts();
  }

  function scopeForRequest(scope) {
    var out = { archive: listState.archive };
    if (listState.payload) { out.payload = listState.payload; }
    if (listState.search) { out.search = listState.search; }
    if (scope) {
      if (scope.seen_within != null) { out.seen_within = scope.seen_within; }
      if (scope.seen_before != null) { out.seen_before = scope.seen_before; }
    }
    return out;
  }

  function paintScopeCount(key, text) {
    var n = dom.qsa('[data-scope-count="' + key + '"]', el("sess-scope-menu"))[0];
    if (n) { n.textContent = text; }
  }

  function loadScopeCounts() {
    var token = ++scopeCountToken;
    SCOPES.forEach(function (s) {
      if (s.key === "page") { return; }
      paintScopeCount(s.key, "...");
      var scope = scopeForRequest(s.scope);
      var q = "?archive=" + scope.archive +
        (scope.payload ? "&payload=" + encodeURIComponent(scope.payload) : "") +
        (scope.search ? "&search=" + encodeURIComponent(scope.search) : "") +
        (scope.seen_within != null ? "&seen_within=" + scope.seen_within : "") +
        (scope.seen_before != null ? "&seen_before=" + scope.seen_before : "");
      EZ.api.get("/sessions/count" + q, { noGlobalError: true }).then(function (res) {
        if (token !== scopeCountToken) { return; }
        var c = (res.data && res.data.count != null) ? Number(res.data.count) : 0;
        paintScopeCount(s.key, c.toLocaleString());
      }).catch(function () {
        if (token !== scopeCountToken) { return; }
        paintScopeCount(s.key, DASH);
      });
    });
  }

  function syncScopeMenu() {
    var menu = el("sess-scope-menu");
    if (!menu) { return; }
    dom.qsa("[data-scope]", menu).forEach(function (b) {
      var key = b.getAttribute("data-scope");
      var on = scopeSel ? (scopeSel.key === key) : (key === "page" && pageAllSelected());
      b.setAttribute("aria-checked", on ? "true" : "false");
    });
    if (!scopeSel) { paintScopeCount("page", String(pageCheckboxes().length)); }
  }

  function selectScope(key) {
    var def = null;
    for (var i = 0; i < SCOPES.length; i++) { if (SCOPES[i].key === key) { def = SCOPES[i]; break; } }
    if (!def) { return; }

    if (key === "page") {
      scopeSel = null;
      selection = {};
      pageCheckboxes().forEach(function (c) { c.checked = true; selection[c.value] = true; });
    } else {
      selection = {};
      var scope = scopeForRequest(def.scope);
      var countText = (dom.qsa('[data-scope-count="' + key + '"]', el("sess-scope-menu"))[0] || {}).textContent || "";
      var matched = parseInt(countText.replace(/[^0-9]/g, ""), 10);
      if (!isFinite(matched)) { matched = 0; }
      scopeSel = { key: key, label: def.label, scope: scope, matched: matched };
    }
    closeScopeMenu();
    syncScopeMenu();
    syncWithbar();
  }

  function openScopeMenu() {
    var wrap = el("sess-scope"), toggle = el("sess-scope-toggle");
    if (!wrap) { return; }
    wrap.classList.add("is-open");
    if (toggle) { toggle.setAttribute("aria-expanded", "true"); }
    renderScopeMenu();
    dom.on(document, "click", onScopeOutside, true);
    dom.on(document, "keydown", onScopeKeydown, true);
  }

  function closeScopeMenu() {
    var wrap = el("sess-scope"), toggle = el("sess-scope-toggle");
    if (!wrap) { return; }
    wrap.classList.remove("is-open");
    if (toggle) { toggle.setAttribute("aria-expanded", "false"); }
    document.removeEventListener("click", onScopeOutside, true);
    document.removeEventListener("keydown", onScopeKeydown, true);
  }

  function onScopeOutside(e) {
    var wrap = el("sess-scope");
    if (wrap && !wrap.contains(e.target)) { closeScopeMenu(); }
  }

  function onScopeKeydown(e) {
    if (e.key === "Escape") { closeScopeMenu(); }
  }

  function renderWithbarActions() {
    var host = el("sess-withbar-actions");
    if (!host) { return; }
    var html;
    var divider = '<span class="withbar__divider" aria-hidden="true"></span>';
    if (listState.archive === 1) {
      html =
        '<button class="btn btn--sm" type="button" data-bulk="unarchive">Unarchive</button>' +
        divider +
        '<button class="btn btn--sm" type="button" data-bulk="delete">Delete</button>';
    } else {
      html =
        '<button class="btn btn--sm" type="button" data-bulk="archive">Archive</button>' +
        '<button class="btn btn--sm" type="button" data-bulk="killreconnect">Kill reconnect</button>' +
        '<button class="btn btn--sm" type="button" data-bulk="eval">JS-eval...</button>' +
        divider +
        '<button class="btn btn--sm" type="button" data-bulk="kill">Kill</button>' +
        '<button class="btn btn--sm" type="button" data-bulk="delete">Delete</button>';
    }
    host.innerHTML = html;
    dom.qsa("[data-bulk]", host).forEach(function (b) {
      dom.on(b, "click", function () { onBulk(b.getAttribute("data-bulk")); });
    });
  }

  function onBulk(kind) {
    if (activeMode() === "scope") {
      var m = scopeSel.matched;
      var sp = m === 1 ? "" : "s";
      if (kind === "delete") {
        EZ.ui.confirm({
          title: "Delete sessions", danger: true, confirmLabel: "Delete",
          message: "Delete " + m + " session" + sp + " (" + scopeSel.label + ") and all their collected data? This cannot be undone."
        }).then(function (ok) { if (!ok) { return; } sessionsBulkScope("delete"); });
      } else if (kind === "archive" || kind === "unarchive") {
        sessionsBulkScope(kind);
      } else if (kind === "kill") {
        EZ.ui.confirm({
          title: "Kill sessions", danger: true, confirmLabel: "Kill",
          message: "Stop persistent mode on " + m + " session" + sp + " (" + scopeSel.label + ")? They stop checking in for queued commands."
        }).then(function (ok) { if (!ok) { return; } consoleBulkScope("kill")["catch"](function () {}); });
      } else if (kind === "killreconnect") {
        consoleBulkScope(kind)["catch"](function () {});
      } else if (kind === "eval") {
        openEvalModal(null);
      }
      return;
    }

    var ids = selectedIds().map(Number);
    if (!ids.length) { return; }
    var n = ids.length;
    var plural = n === 1 ? "" : "s";

    if (kind === "delete") {
      EZ.ui.confirm({
        title: "Delete sessions", danger: true, confirmLabel: "Delete",
        message: "Delete " + n + " session" + plural + " and all their collected data? This cannot be undone."
      }).then(function (ok) { if (!ok) { return; } sessionsBulk("delete", ids); });
    } else if (kind === "archive" || kind === "unarchive") {
      sessionsBulk(kind, ids);
    } else if (kind === "kill") {
      EZ.ui.confirm({
        title: "Kill sessions", danger: true, confirmLabel: "Kill",
        message: "Stop persistent mode on " + n + " session" + plural + "? They stop checking in for queued commands."
      }).then(function (ok) { if (!ok) { return; } consoleBulk("kill", ids); });
    } else if (kind === "killreconnect") {
      consoleBulk(kind, ids);
    } else if (kind === "eval") {
      openEvalModal(ids);
    }
  }

  function sessionsBulk(action, ids) {
    EZ.api.post("/sessions/bulk", { action: action, ids: ids }).then(function (res) {
      toastBulk(res.data || {});
      clearSelection();
      loadList();
    }).catch(function () {});
  }

  function consoleBulk(action, ids, command) {
    var body = { action: action, ids: ids };
    if (command != null) { body.command = command; }
    return EZ.api.post("/console/bulk", body).then(function (res) {
      toastBulk(res.data || {});
      return res;
    });
  }

  function sessionsBulkScope(action) {
    EZ.api.post("/sessions/bulk", { action: action, scope: scopeSel.scope }).then(function (res) {
      toastBulk(res.data || {});
      clearSelection();
      loadList();
    }).catch(function () {});
  }

  function consoleBulkScope(action, command) {
    var body = { action: action, scope: scopeSel.scope };
    if (command != null) { body.command = command; }
    return EZ.api.post("/console/bulk", body).then(function (res) {
      toastBulk(res.data || {});
      return res;
    });
  }

  function toastBulk(d) {
    var applied = Number(d.applied || 0);
    var skipped = (d.skipped && d.skipped.length) ? d.skipped.length : 0;
    var msg = applied + " applied" + (skipped ? " (" + skipped + " skipped)" : "");
    EZ.ui.toast(msg, skipped ? "info" : "success");
  }

  function openEvalModal(ids) {
    var body = el("sess-eval-body"), foot = el("sess-eval-foot");
    if (!body) { return; }
    var scoped = activeMode() === "scope";
    var n = scoped ? scopeSel.matched : ids.length;
    var suffix = scoped ? " (" + scopeSel.label + ")" : "";
    body.innerHTML =
      '<p class="field-hint" style="margin-bottom:var(--space-md)">The command is queued to each selected session and delivered on its next ping.</p>' +
      '<div class="field"><label class="field-label" for="sess-eval-cmd">JavaScript to evaluate</label>' +
        '<textarea class="input mono" id="sess-eval-cmd" rows="4" spellcheck="false" placeholder="alert(document.domain)"></textarea></div>' +
      '<p class="field-hint" id="sess-eval-msg" hidden></p>';
    foot.innerHTML =
      '<button class="btn btn--ghost" type="button" data-close-modal>Cancel</button>' +
      '<button class="btn btn--primary" type="button" id="sess-eval-go">Queue to ' + n + ' session' + (n === 1 ? "" : "s") + "</button>";

    dom.on(el("sess-eval-go"), "click", function () {
      var cmd = (el("sess-eval-cmd") || {}).value || "";
      cmd = cmd.trim();
      if (!cmd) { evalMsg("Enter a command to queue.", true); return; }
      EZ.ui.confirm({
        title: "Queue command",
        confirmLabel: "Queue",
        message: "Queue this command to " + n + " session" + (n === 1 ? "" : "s") + suffix + "?"
      }).then(function (ok) {
        if (!ok) { return; }
        var btn = el("sess-eval-go"); if (btn) { btn.disabled = true; }
        var p = scoped ? consoleBulkScope("execute", cmd) : consoleBulk("execute", ids, cmd);
        p.then(function () {
          closeEvalModal();
          clearSelection();
          loadList();
        }).catch(function (err) {
          evalMsg((err && err.message) || "Could not queue the command.", true);
          if (btn) { btn.disabled = false; }
        });
      });
    });
    EZ.ui.openModal("sess-eval-modal");
    var ta = el("sess-eval-cmd"); if (ta) { ta.focus(); }
  }

  function evalMsg(text, isError) { EZ.ui.fieldMsg(el("sess-eval-msg"), text, isError); }

  function closeEvalModal() {
    var m = el("sess-eval-modal");
    if (m) { m.classList.remove("is-open"); }
  }

  function fillPayloadOptions(sel) {
    if (!sel) { return; }
    function paint() {
      var cur = listState.payload;
      var opts = '<option value="">All payloads</option>';
      if (EZ.util.can("session.viewall")) {
        opts += '<option value="__catchall__"' + (cur === "__catchall__" ? " selected" : "") + ">Catch-all (unassigned)</option>";
      }
      (payloadOptions || []).forEach(function (name) {
        opts += '<option value="' + util.escapeHtml(name) + '"' + (name === cur ? " selected" : "") + ">" + util.escapeHtml(name) + "</option>";
      });
      sel.innerHTML = opts;
    }
    if (payloadOptions) { paint(); return; }
    EZ.api.get("/sessions/payloads").then(function (res) {
      payloadOptions = (res.data || []).filter(Boolean);
      paint();
    }).catch(function () { payloadOptions = []; });
  }

  function setConsoleVisible(on) {
    var form = el("console-form"); if (form) { form.hidden = !on; }
  }

  function isMultiline() {
    var ml = el("console-multiline");
    return !!(ml && ml.getAttribute("aria-pressed") === "true");
  }

  function showEmptyDetail() {
    stopPoll();
    currentId = null;
    lastConsole = null;
    lastPingUnix = null;
    var title = el("sess-title");
    if (title) { title.textContent = "No session selected"; title.setAttribute("title", "No session selected"); }
    var sub = el("sess-sub");
    if (sub) { sub.innerHTML = '<span class="field-hint">Pick a session from the list to view its console.</span>'; }
    var actions = el("sess-actions"); if (actions) { actions.hidden = true; }
    setConsoleVisible(false);
    var reqWrap = el("sess-requests-wrap"); if (reqWrap) { reqWrap.hidden = true; }
    var out = el("console-out");
    if (out) { out.innerHTML = '<div class="console__line"><span class="out faint">Select a session to stream its console output.</span></div>'; }
    setText("sess-foot", "Commands are queued and delivered to the client on its next ping.");
  }

  function setText(id, text) { var n = el(id); if (n) { n.textContent = text; } }

  function loadDetail(id) {
    if (String(id) === String(currentId)) { return; }
    currentId = id;
    lastConsole = null;
    highlightList();
    var out = el("console-out");
    if (out) { out.innerHTML = '<div class="console__line"><span class="out faint">Loading...</span></div>'; }

    EZ.api.get("/sessions/get/" + encodeURIComponent(id)).then(function (res) {
      renderDetail(res.data || {});
    }).catch(function () {
      setText("sess-title", "Session unavailable");
      setText("sess-sub", "Could not load this session.");
      var a = el("sess-actions"); if (a) { a.hidden = true; }
      setConsoleVisible(false);
      var reqWrap = el("sess-requests-wrap"); if (reqWrap) { reqWrap.hidden = true; }
    });
  }

  function renderDetail(s) {
    if (String(s.id) !== String(currentId)) { return; }

    var live = isLive(s.last)
      ? ' <span class="live"><span class="dot"></span>live</span>'
      : "";
    var cid = s.client_id || DASH;
    var title = el("sess-title");
    if (title) {
      title.innerHTML = util.escapeHtml(cid) + live;
      title.setAttribute("title", cid);
    }

    var reqN = (s.requests == null ? "?" : s.requests);
    if (s.last != null) { lastPingUnix = Number(s.last); }
    var subEl = el("sess-sub");
    if (subEl) {
      var sep = '<span class="sess-meta__sep" aria-hidden="true">' + MID + "</span>";
      subEl.innerHTML = [
        '<span class="sess-meta__host mono">' + util.escapeHtml(s.origin || "(no origin)") + "</span>",
        '<span class="sess-meta__ip mono">' + util.escapeHtml(s.ip || DASH) + "</span>",
        '<span class="sess-meta__item">' + util.escapeHtml(reqN + " request" + (Number(s.requests) === 1 ? "" : "s")) + "</span>",
        '<span class="sess-meta__item sess-meta__ping">' + util.escapeHtml("last ping " + EZ.format.fmtAgo(s.last)) + "</span>"
      ].join(sep);
    }

    var actions = el("sess-actions");
    if (actions) { actions.hidden = false; }
    var archived = Number(s.archive) === 1;
    var archBtn = el("sess-archive");
    if (archBtn) { archBtn.textContent = archived ? "Unarchive" : "Archive"; }

    setConsoleVisible(true);
    applyConsoleMode();
    setText("sess-foot", "Commands are queued and delivered to the client on its next ping.");

    renderConsole(s.console || "");

    loadRequests(s.id);

    startPoll(s.id);
  }

  var INLINE_REQ_CAP = 20;

  function reqRowHtml(r) {
    var chev = '<span class="reqrow__chev"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
    var uri = r.shorturi || r.uri || "(no uri)";
    var meta = [r.ip || DASH, r.browser || "Unknown", EZ.format.fmtAgo(r.last || r.created_at)]
      .map(function (s) { return util.escapeHtml(String(s)); }).join(" " + MID + " ");
    return '<button class="reqrow" type="button" data-req-id="' + util.escapeHtml(String(r.id)) + '">' +
      '<div class="reqrow__main">' +
        '<div class="reqrow__top"><span class="reqrow__uri">' + util.escapeHtml(String(uri)) + "</span></div>" +
        '<div class="reqrow__meta">' + meta + "</div>" +
      "</div>" + chev +
      "</button>";
  }

  function wireReqRows(container) {
    dom.qsa("[data-req-id]", container).forEach(function (b) {
      dom.on(b, "click", function () { loadRequest(b.getAttribute("data-req-id")); });
    });
  }

  function loadRequests(id) {
    var wrap = el("sess-requests-wrap"), list = el("sess-requests");
    if (!wrap || !list) { return; }
    wrap.hidden = false;
    list.innerHTML = EZ.ui.loading();
    EZ.api.get("/sessions/requests/" + encodeURIComponent(id) + "?per_page=" + INLINE_REQ_CAP + "&page=1", { noGlobalError: true }).then(function (res) {
      if (String(id) !== String(currentId)) { return; }
      paintRequests(res.data || [], (res.meta && res.meta.pagination) || {}, id);
    }).catch(function () {
      list.innerHTML = '<p class="field-hint" style="padding:var(--space-sm)">Could not load requests.</p>';
    });
  }

  function paintRequests(rows, meta, id) {
    var list = el("sess-requests"), count = el("sess-requests-count");
    if (!list) { return; }
    var total = Number((meta && meta.total) || rows.length);
    if (count) {
      count.textContent = (total > rows.length)
        ? (rows.length + " of " + total.toLocaleString())
        : (total + " request" + (total === 1 ? "" : "s"));
    }
    if (!rows.length) {
      list.innerHTML = '<p class="field-hint" style="padding:var(--space-sm)">No requests captured yet.</p>';
      return;
    }
    var html = rows.map(reqRowHtml).join("");
    if (total > rows.length) {
      html += '<button class="btn btn--sm reqlist__all" type="button" id="sess-requests-all">' +
        "View all " + total.toLocaleString() + " requests \u2192</button>";
    }
    list.innerHTML = html;
    wireReqRows(list);
    var allBtn = el("sess-requests-all");
    if (allBtn) { dom.on(allBtn, "click", function () { EZ.router.navigate("session-requests", { id: id }); }); }
  }

  function loadRequest(reqId) {
    var body = el("request-drawer-body"), foot = el("request-drawer-foot");
    if (!body) { return; }
    body.innerHTML = EZ.ui.loading();
    if (foot) { foot.innerHTML = '<button class="btn btn--ghost" type="button" data-close-modal>Close</button>'; }
    EZ.ui.openModal("request-drawer");
    EZ.api.get("/sessions/request/" + encodeURIComponent(reqId), { noGlobalError: true }).then(function (res) {
      paintRequestDetail(res.data || {});
    }).catch(function () {
      body.innerHTML = '<p class="field-hint">Could not load this request.</p>';
    });
  }

  function reqCopyRow(label, id, value) {
    value = (value == null || value === "") ? DASH : String(value);
    return '<div class="frow"><div class="frow__k">' + util.escapeHtml(label) + "</div>" +
      '<div class="frow__v"><div class="copyfield">' +
      '<input class="input mono" id="' + id + '" readonly value="' + util.escapeHtml(value) + '">' +
      EZ.ui.copyBtn("#" + id) +
      "</div></div></div>";
  }

  function reqDataRow(label, value) {
    value = (value == null || value === "") ? DASH : String(value);
    return '<div class="frow"><div class="frow__k">' + util.escapeHtml(label) + "</div>" +
      '<div class="frow__v">' + util.escapeHtml(value) + "</div></div>";
  }

  function reqBlock(label, id, value) {
    var v = value;
    if (v && typeof v === "object") { try { v = JSON.stringify(v, null, 2); } catch (e) { v = String(v); } }
    v = (v == null) ? "" : String(v);
    if (v === "") {
      return '<div class="frow frow--block"><div class="field--copy"><div class="field--copy__head">' +
        '<span class="field-label">' + util.escapeHtml(label) + "</span></div>" +
        '<p class="frow__v faint">Not captured.</p></div></div>';
    }
    return '<div class="frow frow--block"><div class="field--copy">' +
      EZ.ui.copyHead(util.escapeHtml(label), "#" + id) +
      '<textarea spellcheck="false" class="input mono" rows="5" id="' + id + '" readonly>' +
      util.escapeHtml(v) + "</textarea></div></div>";
  }

  var REQ_RENDER_DOM_BTN =
    '<button class="copyfield__btn" type="button" id="req-render-dom" aria-label="Render HTML as a page" title="Render HTML as a page">' +
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18" stroke-linecap="round"/><circle cx="6" cy="6.5" r=".6" fill="currentColor"/></svg></button>';
  function reqDomBlock(value) {
    var v = (value == null) ? "" : String(value);
    if (v === "") {
      return '<div class="frow frow--block"><div class="field--copy"><div class="field--copy__head">' +
        '<span class="field-label">DOM</span></div>' +
        '<p class="frow__v faint">Not captured.</p></div></div>';
    }
    return '<div class="frow frow--block"><div class="field--copy">' +
      EZ.ui.copyHead("DOM", "#req-dom", REQ_RENDER_DOM_BTN) +
      '<textarea spellcheck="false" class="input mono" rows="5" id="req-dom" readonly>' +
      util.escapeHtml(v) + "</textarea></div></div>";
  }

  function paintRequestDetail(r) {
    var body = el("request-drawer-body");
    if (!body) { return; }
    var ua = (r.browser ? r.browser + " - " : "") + (r.useragent || "");
    body.innerHTML =
      '<div class="freport">' +
        reqCopyRow("URI", "req-uri", r.uri) +
        reqCopyRow("IP", "req-ip", r.ip) +
        reqCopyRow("Referer", "req-referer", r.referer) +
        reqDataRow("User-agent", ua) +
        reqBlock("Cookies", "req-cookies", r.cookies) +
        reqDomBlock(r.dom) +
        reqBlock("localStorage", "req-localstorage", r.localstorage) +
        reqBlock("sessionStorage", "req-sessionstorage", r.sessionstorage) +
        reqBlock("Console", "req-console", r.console) +
      "</div>";
    var renderBtn = el("req-render-dom");
    if (renderBtn) { dom.on(renderBtn, "click", function () { EZ.ui.openDomViewer(r.dom || ""); }); }
  }

  function applyConsoleMode() {
    var cur = el("console-cmd");
    if (!cur) { return; }
    var multiline = isMultiline();
    var isTextarea = cur.tagName === "TEXTAREA";
    if (multiline === isTextarea) { return; }

    var node = document.createElement(multiline ? "textarea" : "input");
    node.className = "input mono";
    node.id = "console-cmd";
    node.setAttribute("autocomplete", "off");
    node.value = cur.value;
    if (multiline) {
      node.rows = 3;
      node.placeholder = "JS to eval in the target session (Ctrl/Cmd+Enter to send)...";
    } else {
      node.type = "text";
      node.placeholder = "JS to eval in the target session...";
    }
    cur.parentNode.replaceChild(node, cur);
    dom.on(node, "keydown", onConsoleKeydown);
  }

  function onConsoleKeydown(e) {
    if (isMultiline() && e.key === "Enter" && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      submitCommand();
    }
  }

  function highlightList() {
    dom.qsa("#sess-list [data-sess-id]").forEach(function (b) {
      b.classList.toggle("is-active", b.getAttribute("data-sess-id") === String(currentId));
    });
  }

  function renderConsole(text) {
    var out = el("console-out");
    if (!out) { return; }
    lastConsole = String(text == null ? "" : text);
    updateKillReconnect(lastConsole);

    var lines = lastConsole.split(/\r?\n/).filter(function (l) { return l !== ""; });
    if (!lines.length) {
      out.innerHTML = '<div class="console__line"><span class="out faint">No console output captured yet.</span></div>';
      return;
    }
    out.innerHTML = lines.map(function (l) {
      return '<div class="console__line"><span class="out">' + util.escapeHtml(l) + "</span></div>";
    }).join("");
    out.scrollTop = out.scrollHeight;
  }

  function appendLine(html) {
    var out = el("console-out");
    if (!out) { return; }
    var faint = out.querySelector(".out.faint");
    if (faint) { out.innerHTML = ""; }
    var now = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", second: "2-digit" });
    var div = document.createElement("div");
    div.className = "console__line";
    div.innerHTML = '<span class="t">[' + now + "]</span> " + html;
    out.appendChild(div);
    out.scrollTop = out.scrollHeight;
  }

  function tickPingDisplay() {
    if (lastPingUnix == null) { return; }
    var span = dom.qs(".sess-meta__ping");
    if (span) { span.textContent = "last ping " + EZ.format.fmtAgo(lastPingUnix); }
  }

  function desiredPollMs() {
    if (lastPingUnix == null) { return POLL_MS; }
    var age = Math.floor(Date.now() / 1000) - lastPingUnix;
    if (age > 300) { return 30000; }
    if (age > 60) { return 15000; }
    if (age > LIVE_WINDOW) { return 10000; }
    return POLL_MS;
  }

  function startPoll(id) {
    stopPoll();
    currentPollMs = POLL_MS;
    pollTimer = window.setInterval(function () { pollOutput(id); }, currentPollMs);
    pingTickTimer = window.setInterval(tickPingDisplay, 1000);
  }
  function stopPoll() {
    if (pollTimer) { window.clearInterval(pollTimer); pollTimer = null; }
    if (pingTickTimer) { window.clearInterval(pingTickTimer); pingTickTimer = null; }
    lastPingUnix = null;
    currentPollMs = POLL_MS;
  }
  function pollOutput(id) {
    if (String(id) !== String(currentId)) { stopPoll(); return; }
    EZ.api.get("/console/output/" + encodeURIComponent(id), { noGlobalError: true }).then(function (res) {
      if (String(id) !== String(currentId)) { return; }
      var d = res.data || {};
      var text = d.console != null ? String(d.console) : "";
      if (text !== lastConsole) { renderConsole(text); }
      if (d.last != null) { lastPingUnix = Number(d.last); }
      var want = desiredPollMs();
      if (want !== currentPollMs) {
        currentPollMs = want;
        if (pollTimer) { window.clearInterval(pollTimer); }
        pollTimer = window.setInterval(function () { pollOutput(id); }, currentPollMs);
      }
    }).catch(function () {});
  }

  function submitCommand(e) {
    if (e) { e.preventDefault(); }
    if (!currentId) { return; }
    var input = el("console-cmd");
    if (!input) { return; }
    var cmd = input.value.trim();
    if (!cmd) { return; }

    var id = currentId;
    EZ.api.post("/console/execute/" + encodeURIComponent(id), { command: cmd }).then(function () {
      appendLine('<span class="in">&raquo; ' + util.escapeHtml(cmd) + "</span>");
      appendLine('<span class="ok">queued - delivered to client on next ping</span>');
      input.value = "";
    }).catch(function () {  });
  }

  function doArchive() {
    if (!currentId) { return; }
    var id = currentId;
    EZ.api.post("/sessions/archive/" + encodeURIComponent(id), {}).then(function (res) {
      var archived = (res.data && Number(res.data.archive)) === 1;
      var btn = el("sess-archive");
      if (btn) { btn.textContent = archived ? "Unarchive" : "Archive"; }
      EZ.ui.flashSaved();
      loadList();
    }).catch(function () {});
  }

  function doDelete() {
    if (!currentId) { return; }
    EZ.ui.confirm({
      title: "Delete session", danger: true, confirmLabel: "Delete",
      message: "Delete this session and all its collected data? This cannot be undone."
    }).then(function (ok) {
      if (!ok) { return; }
      var id = currentId;
      EZ.api.del("/sessions/delete/" + encodeURIComponent(id)).then(function () {
        EZ.ui.toast("Session deleted.", "success");
        showEmptyDetail();
        EZ.router.navigate("sessions");
        loadList();
      }).catch(function () {});
    });
  }

  function doKill() {
    if (!currentId) { return; }
    var id = currentId;
    EZ.ui.confirm({
      title: "Kill session", danger: true, confirmLabel: "Kill",
      message: "Stop persistent mode on this session? The client stops checking in for queued commands."
    }).then(function (ok) {
      if (!ok) { return; }
      EZ.api.post("/console/kill/" + encodeURIComponent(id), {}).then(function (res) {
        EZ.ui.toast((res.data && res.data.message) || "Killed persistent.", "success");
        appendLine('<span class="ok">ez_stop() queued</span>');
      }).catch(function () {});
    });
  }

  function hadAutoReconnect(consoleText) {
    return /setItem\(['"]ezProxy/.test(String(consoleText == null ? "" : consoleText));
  }

  function updateKillReconnect(consoleText) {
    var b = el("sess-killreconnect");
    if (b) { b.hidden = !hadAutoReconnect(consoleText); }
  }

  function doKillReconnect() {
    if (!currentId) { return; }
    EZ.api.post("/console/killreconnect/" + encodeURIComponent(currentId), {}).then(function (res) {
      EZ.ui.toast((res.data && res.data.message) || "Killed auto reconnect.", "success");
      appendLine('<span class="ok">auto-reconnect flag removal queued</span>');
    }).catch(function () {});
  }

  function openProxyModal() {
    if (!currentId) { return; }
    var body = el("proxy-modal-body"), foot = el("proxy-modal-foot");
    if (!body) { return; }

    body.innerHTML =
      '<p class="field-hint" style="margin-bottom:var(--space-md)">Opens a reverse-proxy tunnel from the target session. Point the external <span class="mono">ezProxy</span> client at this server with an API token (create one under Users &amp; keys), then enter where it listens below.</p>' +
      '<div class="field"><label class="field-label" for="proxy-ipport">Proxy host:port</label>' +
        '<input class="input mono" id="proxy-ipport" placeholder="10.0.0.1:8080" autocapitalize="none" spellcheck="false"></div>' +
      '<div class="toggle-item" style="margin-top:var(--space-md)">' +
        '<div><div class="toggle-item__label">Pass origin</div>' +
        '<div class="toggle-item__sub">Also expose the tunnel on the session\u2019s own origin.</div></div>' +
        '<label class="switch"><input type="checkbox" id="proxy-passorigin"><span class="switch__track"></span></label>' +
      "</div>" +
      '<div class="toggle-item" style="margin-top:var(--space-sm)">' +
        '<div><div class="toggle-item__label">Auto reconnect</div>' +
        '<div class="toggle-item__sub">Persist the proxy across reloads via localStorage.</div></div>' +
        '<label class="switch"><input type="checkbox" id="proxy-autoreconnect"><span class="switch__track"></span></label>' +
      "</div>" +
      '<p class="field-hint" id="proxy-msg" hidden></p>';

    foot.innerHTML =
      '<button class="btn btn--ghost" type="button" data-close-modal>Close</button>' +
      '<button class="btn btn--primary" type="button" id="proxy-start">Start proxy</button>';

    dom.on(el("proxy-start"), "click", startProxy);
    EZ.ui.openModal("proxy-modal");
    var ip = el("proxy-ipport"); if (ip) { ip.focus(); }
  }

  function proxyMsg(text, isError) { EZ.ui.fieldMsg(el("proxy-msg"), text, isError); }

  function startProxy() {
    if (!currentId) { return; }
    var ipport = (el("proxy-ipport") || {}).value || "";
    ipport = ipport.trim();
    if (!ipport) { proxyMsg("Enter the proxy host:port.", true); return; }

    var body = {
      ipport: ipport,
      passorigin: !!(el("proxy-passorigin") && el("proxy-passorigin").checked),
      autoreconnect: !!(el("proxy-autoreconnect") && el("proxy-autoreconnect").checked)
    };
    proxyMsg("");
    var btn = el("proxy-start"); if (btn) { btn.disabled = true; }
    EZ.api.post("/console/proxy/" + encodeURIComponent(currentId), body).then(function (res) {
      var msg = (res.data && res.data.message) || "Proxy command queued.";
      proxyMsg(msg, false);
      appendLine('<span class="ok">' + util.escapeHtml(msg) + "</span>");
      if (body.autoreconnect) { var kr = el("sess-killreconnect"); if (kr) { kr.hidden = false; } }
      EZ.ui.toast("Reverse proxy queued.", "success");
    }).catch(function (err) {
      proxyMsg((err && err.message) || "Could not start the proxy.", true);
    }).then(function () { if (btn) { btn.disabled = false; } });
  }

  function wireChrome() {
    if (wired) { return; }
    wired = true;

    dom.qsa("[data-sess-seg] button").forEach(function (b) {
      dom.on(b, "click", function () {
        var a = Number(b.getAttribute("data-archive")) === 1 ? 1 : 0;
        if (a === listState.archive) { return; }
        listState.archive = a;
        listState.page = 1;
        clearSelection();
        dom.qsa("[data-sess-seg] button").forEach(function (x) {
          x.setAttribute("aria-pressed", String(Number(x.getAttribute("data-archive")) === a));
        });
        loadList();
      });
    });

    var payloadSel = el("sess-payload");
    fillPayloadOptions(payloadSel);
    dom.on(payloadSel, "change", function () {
      listState.payload = payloadSel.value;
      listState.page = 1;
      clearSelection();
      loadList();
    });

    var searchBox = el("sess-search");
    if (searchBox) {
      searchBox.value = listState.search;
      dom.on(searchBox, "input", util.debounce(function () {
        var v = searchBox.value.trim();
        if (v === listState.search) { return; }
        listState.search = v;
        listState.page = 1;
        clearSelection();
        loadList();
      }, 250));
    }

    var scopeToggle = el("sess-scope-toggle");
    dom.on(scopeToggle, "click", function () {
      var wrap = el("sess-scope");
      if (wrap && wrap.classList.contains("is-open")) { closeScopeMenu(); } else { openScopeMenu(); }
    });

    var clearBtn = el("sess-withbar-clear");
    if (clearBtn) {
      dom.on(clearBtn, "click", function () {
        clearSelection();
        pageCheckboxes().forEach(function (c) { c.checked = false; });
      });
    }

    dom.on(el("console-form"), "submit", submitCommand);
    var mlBtn = el("console-multiline");
    dom.on(mlBtn, "click", function () {
      var on = mlBtn.getAttribute("aria-pressed") === "true";
      mlBtn.setAttribute("aria-pressed", String(!on));
      applyConsoleMode();
      var cur = el("console-cmd"); if (cur) { try { cur.focus(); } catch (e) {} }
    });
    var cmd = el("console-cmd"); if (cmd) { dom.on(cmd, "keydown", onConsoleKeydown); }
    dom.on(el("sess-archive"), "click", doArchive);
    dom.on(el("sess-delete"), "click", doDelete);
    dom.on(el("sess-kill"), "click", doKill);
    dom.on(el("sess-killreconnect"), "click", doKillReconnect);
    dom.on(el("sess-proxy"), "click", openProxyModal);
  }

  var srId = null, srPage = 1, srSearch = "", srSearchTimer = null;
  var SR_PER_PAGE = 50;

  function srLoad() {
    var list = el("sr-list");
    if (!srId || !list) { return; }
    list.innerHTML = EZ.ui.loading();
    var q = "?page=" + srPage + "&per_page=" + SR_PER_PAGE +
      (srSearch ? "&search=" + encodeURIComponent(srSearch) : "");
    var reqId = srId;
    EZ.api.get("/sessions/requests/" + encodeURIComponent(srId) + q, { noGlobalError: true }).then(function (res) {
      if (reqId !== srId) { return; }
      srPaint(res.data || [], (res.meta && res.meta.pagination) || {});
    }).catch(function () {
      if (reqId !== srId) { return; }
      list.innerHTML = '<p class="field-hint" style="padding:var(--space-sm)">Could not load requests.</p>';
      srPaintPager({});
    });
  }

  function srPaint(rows, meta) {
    var list = el("sr-list"), count = el("sr-count");
    if (!list) { return; }
    var total = Number(meta.total || 0);
    if (count) { count.textContent = total.toLocaleString() + " request" + (total === 1 ? "" : "s"); }
    if (!rows.length) {
      list.innerHTML = '<p class="field-hint" style="padding:var(--space-sm)">' +
        (srSearch ? "No requests match your search." : "No requests captured yet.") + "</p>";
      srPaintPager({});
      return;
    }
    list.innerHTML = rows.map(reqRowHtml).join("");
    wireReqRows(list);
    srPaintPager(meta);
  }

  function srPaintPager(meta) {
    EZ.ui.renderPager(el("sr-pager"), meta, function (p) {
      srPage = p;
      srLoad();
    });
  }

  EZ.router.register("session-requests", {
    mount: function (ctx) {
      var id = (ctx && ctx.params) ? ctx.params.id : null;
      if (!id) { EZ.router.navigate("sessions"); return; }
      srId = id;
      srPage = 1;
      srSearch = "";
      var back = el("sr-back");
      if (back) { back.setAttribute("data-id", String(id)); }
      var search = el("sr-search");
      if (search) {
        search.value = "";
        if (!search._srWired) {
          search._srWired = true;
          dom.on(search, "input", function () {
            if (srSearchTimer) { clearTimeout(srSearchTimer); }
            srSearchTimer = setTimeout(function () {
              srSearch = (search.value || "").trim();
              srPage = 1;
              srLoad();
            }, 300);
          });
        }
      }
      srLoad();
    },
    unmount: function () {
      if (srSearchTimer) { clearTimeout(srSearchTimer); srSearchTimer = null; }
    }
  });

  function applySearch(q) {
    listState.search = q;
    listState.payload = "";
    listState.archive = 0;
    listState.page = 1;
    var box = el("sess-search"); if (box) { box.value = q; }
    var payloadSel = el("sess-payload"); if (payloadSel) { payloadSel.value = ""; }
    dom.qsa("[data-sess-seg] button").forEach(function (x) {
      x.setAttribute("aria-pressed", String(Number(x.getAttribute("data-archive")) === 0));
    });
    clearSelection();
  }

  EZ.router.register("sessions", {
    mount: function (ctx) {
      var id = (ctx && ctx.params) ? ctx.params.id : null;
      var q = EZ.util.readInitialSearch();

      if (mounted) {
        if (!id && q !== "" && q !== listState.search) { applySearch(q); loadList(); }
        if (id) { loadDetail(id); } else { showEmptyDetail(); }
        return;
      }

      stopPoll();
      wireChrome();
      clearSelection();
      if (q !== "") { applySearch(q); }
      loadList();
      if (id) { loadDetail(id); } else { showEmptyDetail(); }
      mounted = true;
    },
    unmount: function () { stopPoll(); mounted = false; },
    resetState: function () {
      stopPoll();
      if (srSearchTimer) { clearTimeout(srSearchTimer); srSearchTimer = null; }
      payloadOptions = null;
      listState.archive = 0;
      listState.payload = "";
      listState.search = "";
      listState.page = 1;
      selection = {};
      scopeSel = null;
      currentId = null;
      lastConsole = null;
      lastPingUnix = null;
      srId = null;
      srPage = 1;
      srSearch = "";
      mounted = false;
    }
  });
})(window.EZ);

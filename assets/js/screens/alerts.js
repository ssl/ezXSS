window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util;
  function el(id) { return EZ.dom.byId(id); }

  function collect(form) {
    var out = {};
    dom.qsa("[data-alert-method]", form).forEach(function (ctl) {
      var method = ctl.getAttribute("data-alert-method");
      var key = ctl.getAttribute("data-alert-key");
      if (!out[method]) { out[method] = {}; }
      out[method][key] = (ctl.type === "checkbox") ? ctl.checked : ctl.value;
    });
    return out;
  }

  EZ.autosave.register("alerts", function (el) {
    var form = el.closest("[data-alerts-form]");
    return collect(form);
  });

  function sw(method, key, on) {
    return '<label class="switch">' +
      '<input type="checkbox" data-field="alert" data-autosave-builder="alerts"' +
      ' data-alert-method="' + method + '" data-alert-key="' + key + '"' + (on ? " checked" : "") + ">" +
      '<span class="switch__track"></span></label>';
  }

  function txt(method, key, value, placeholder, mono, extraCls) {
    return '<input class="input' + (mono ? " mono" : "") + (extraCls ? " " + extraCls : "") + '" type="text"' +
      ' data-field="alert" data-autosave-builder="alerts"' +
      ' data-alert-method="' + method + '" data-alert-key="' + key + '"' +
      ' placeholder="' + util.escapeHtml(placeholder || "") + '"' +
      ' value="' + util.escapeHtml(value || "") + '">';
  }

  function methodRow(method, label, hint, enabled, valuesHTML, masterOff) {
    var field = '<div class="alert-field">' +
      '<label class="alert-field__toggle">' + sw(method, "enabled", enabled) +
      '<span class="faint" style="font-size:var(--text-xs)">enable this channel</span></label>' +
      valuesHTML + "</div>";
    var right = masterOff
      ? '<div class="jsoff"><div class="jsoff__content">' + field + "</div>" +
        '<div class="jsoff__note"><span>Disabled by an administrator</span></div></div>'
      : field;
    return '<div class="formrow formrow--alert">' +
      '<div><div class="formrow__label">' + util.escapeHtml(label) + "</div>" +
      '<div class="formrow__hint">' + util.escapeHtml(hint) + "</div></div>" +
      '<div class="formrow__field">' + right + "</div></div>";
  }

  function methodFields(data, gated) {
    var mail = data.mail || {}, tg = data.telegram || {}, sl = data.slack || {}, dc = data.discord || {};
    function off(m) { return !!(gated && data[m] && data[m].method_enabled === false); }
    return "" +
      methodRow("mail", "Mail", "Email address to notify on a new report.", mail.enabled,
        txt("mail", "email", mail.email, "you@example.com"), off("mail")) +
      methodRow("telegram", "Telegram", "Bot token + chat id.", tg.enabled,
        '<div class="tg-row">' +
          txt("telegram", "token", tg.token, "bot token", true, "tg-row__token") +
          '<span class="tg-derive">' +
            '<button class="tg-derive__btn" type="button" data-alerts-getchatid aria-label="Fetch chat ID from your bot token">' +
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h13M12 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
            "</button>" +
            '<span class="tg-derive__tip" role="tooltip">Send <code>/start</code> to your bot, then click to pull your chat ID in automatically.</span>' +
          "</span>" +
          txt("telegram", "chatid", tg.chatid, "chat id", true, "tg-row__chat") +
        "</div>", off("telegram")) +
      methodRow("slack", "Slack", "Incoming webhook URL.", sl.enabled,
        txt("slack", "webhook", sl.webhook, "https://hooks.slack.com/services/...", true), off("slack")) +
      methodRow("discord", "Discord", "Webhook URL.", dc.enabled,
        txt("discord", "webhook", dc.webhook, "https://discord.com/api/webhooks/...", true), off("discord"));
  }

  function bindGetChatId(container) {
    dom.qsa("[data-alerts-getchatid]", container).forEach(function (btn) {
      dom.on(btn, "click", function () {
        var form = btn.closest("[data-alerts-form]");
        if (!form) { return; }
        var tokenEl = dom.qs('[data-alert-method="telegram"][data-alert-key="token"]', form);
        var chatEl = dom.qs('[data-alert-method="telegram"][data-alert-key="chatid"]', form);
        var token = tokenEl ? tokenEl.value.trim() : "";
        if (!token) {
          EZ.ui.toast("Enter your Telegram bot token first.", "info");
          return;
        }
        btn.disabled = true;
        EZ.api.post("/alerts/chatid", { token: token }).then(function (res) {
          var chatid = (res.data && res.data.chatid) ? res.data.chatid : "";
          if (chatEl && chatid !== "") {
            chatEl.value = chatid;
            if (EZ.autosave) { EZ.autosave.persist(chatEl); }
            EZ.ui.toast("Chat ID found and saved.", "success");
          }
        }).catch(function () {  })
          .then(function () { btn.disabled = false; });
      });
    });
  }

  function renderOwn(container) {
    if (!container) { return; }
    container.innerHTML = '' + EZ.ui.loading() + '';
    EZ.api.get("/alerts").then(function (res) {
      var data = res.data || {};
      container.innerHTML =
        '<div data-alerts-form data-alerts-scope="own">' +
          methodFields(data, true) +
          '<div class="formrow" style="border-bottom:0">' +
            '<div><div class="formrow__label">Test alerts</div>' +
            '<div class="formrow__hint">Send a sample report to your enabled channels.</div></div>' +
            '<div class="formrow__field"><button class="btn" type="button" id="alerts-own-test">Send test alert</button></div>' +
          "</div>" +
        "</div>";
      if (EZ.autosave) { EZ.autosave.snapshot(container); }
      bindGetChatId(container);
      dom.on(document.getElementById("alerts-own-test"), "click", sendTest);
    }).catch(function () {
      container.innerHTML = '<p class="field-hint">Could not load your alert destinations.</p>';
    });
  }

  function sendTest(e) {
    var btn = e.currentTarget;
    btn.disabled = true;
    EZ.api.post("/alerts/test", {}).then(function (res) {
      var d = res.data || {};
      EZ.ui.toast(d.message || "Test alert dispatched.", (d.sent && d.sent.length) ? "success" : "info");
    }).catch(function () {  })
      .then(function () { btn.disabled = false; });
  }

  function renderGlobal(container, extras) {
    if (!container) { return; }
    extras = extras || {};
    container.innerHTML = '<div class="card">' + EZ.ui.loading() + '</div>';
    EZ.api.get("/alerts/settings").then(function (res) {
      var data = res.data || {};
      var dompart = (extras.dompart != null) ? extras.dompart : 0;
      var contentCard =
        '<div class="card" style="margin-top:var(--space-lg)" data-endpoint="/settings/update">' +
          '<div class="card__head"><h2 class="card__title">Alert content</h2><span class="tag tag--muted">System</span></div>' +
          '<div class="formrow" style="border-bottom:0">' +
            '<div><div class="formrow__label">DOM snippet included in alerts</div>' +
            '<div class="formrow__hint">Characters of the captured DOM included in alert messages (0 = none).</div></div>' +
            '<div class="formrow__field"><input class="input mono" type="number" min="0" data-field="dompart" style="max-width:200px" value="' +
              util.escapeHtml(String(dompart)) + '"></div>' +
          "</div>" +
        "</div>";
      container.innerHTML =
        '<div data-alerts-form data-alerts-scope="global">' +
          '<div class="card">' +
            '<div class="card__head"><h2 class="card__title">Master switches</h2><span class="tag tag--muted">System</span></div>' +
            mailRow(data.mail && data.mail.method_enabled) +
            masterRow("telegram", "Telegram", "Allow Telegram alerts platform-wide.", data.telegram && data.telegram.method_enabled) +
            masterRow("slack", "Slack", "Allow Slack alerts platform-wide.", data.slack && data.slack.method_enabled) +
            masterRow("discord", "Discord", "Allow Discord alerts platform-wide.", data.discord && data.discord.method_enabled, true) +
          "</div>" +
          '<div class="card" style="margin-top:var(--space-lg)">' +
            '<div class="card__head"><h2 class="card__title">Global destinations</h2><span class="tag tag--muted">System</span></div>' +
            methodFields(data) +
            callbackRow(data.callback || {}) +
          "</div>" +
        "</div>" +
        contentCard;
      if (EZ.autosave) { EZ.autosave.snapshot(container); }
      bindGetChatId(container);
      bindMailTransport(container);
    }).catch(function () {
      container.innerHTML = '<div class="card"><p class="field-hint">Could not load the global alert configuration.</p></div>';
    });
  }

  function callbackRow(cb) {
    return '<div class="formrow formrow--alert" style="border-bottom:0" data-callback>' +
      '<div><div class="formrow__label">Report callback</div>' +
      '<div class="formrow__hint">Forward every report (full JSON) to this URL as it arrives.</div></div>' +
      '<div class="formrow__field"><div style="display:grid;gap:var(--space-sm)">' +
        '<div style="display:flex;align-items:center;gap:var(--space-sm)">' +
          '<label class="switch"><input type="checkbox" data-field="callback" data-autosave-builder="settingsCallback" data-callback-enable' +
            (cb.method_enabled ? " checked" : "") + '><span class="switch__track"></span></label>' +
          '<span class="faint" style="font-size:var(--text-xs)">enable the callback</span></div>' +
        '<input class="input mono" type="text" data-field="callback" data-autosave-builder="settingsCallback" data-callback-url placeholder="https://hooks.example.com/ezxss" value="' +
          util.escapeHtml(cb.url || "") + '">' +
      "</div></div></div>";
  }

  function masterRow(method, label, hint, on, flush) {
    return '<div class="formrow"' + (flush ? ' style="border-bottom:0"' : "") + ">" +
      '<div><div class="formrow__label">' + util.escapeHtml(label) + "</div>" +
      '<div class="formrow__hint">' + util.escapeHtml(hint) + "</div></div>" +
      '<div class="formrow__field">' + sw(method, "method_enabled", on) + "</div></div>";
  }

  function mailRow(on) {
    return '<div class="formrow formrow--mail">' +
      '<div><div class="formrow__label">Mail</div>' +
      '<div class="formrow__hint">Allow mail alerts platform-wide.</div></div>' +
      '<div class="formrow__field">' + sw("mail", "method_enabled", on) + "</div>" +
      '<span class="mailtransport" id="alert-mail-transport" hidden>' +
        '<select class="input mailtransport__sel" id="alert-mail-transport-sel">' +
          '<option value="php">PHP mail()</option>' +
          '<option value="smtp">SMTP server</option>' +
        "</select>" +
        '<button class="btn mailtransport__btn" type="button" id="alert-smtp-config" hidden>SMTP settings</button>' +
      "</span>" +
    "</div>";
  }

  function saveSetting(body) {
    return EZ.api.post("/settings/update", body, { noGlobalError: true });
  }

  function bindMailTransport(container) {
    var row = el("alert-mail-transport"), sel = el("alert-mail-transport-sel"), cfgBtn = el("alert-smtp-config");
    var mailMaster = container.querySelector('[data-alert-method="mail"][data-alert-key="method_enabled"]');
    if (!row || !sel) { return; }
    function syncBtn() { if (cfgBtn) { cfgBtn.hidden = sel.value !== "smtp"; } }
    function syncRow() { row.hidden = !(mailMaster && mailMaster.checked); if (!row.hidden) { syncBtn(); } }

    EZ.api.get("/settings", { noGlobalError: true }).then(function (res) {
      var smtp = (res && res.data && res.data.smtp) || {};
      sel.value = smtp.enabled ? "smtp" : "php";
      syncBtn();
    }).catch(function () {});

    syncRow();
    if (mailMaster) { dom.on(mailMaster, "change", syncRow); }
    dom.on(sel, "change", function () {
      var useSmtp = sel.value === "smtp";
      syncBtn();
      saveSetting({ smtp_enabled: useSmtp }).then(function () {
        if (useSmtp) { openSmtpModal(); }
      }).catch(function () {});
    });
    if (cfgBtn) { dom.on(cfgBtn, "click", openSmtpModal); }
  }

  function smtpField(label, html, hint) {
    return '<div class="field" style="margin-top:var(--space-md)"><label class="field-label">' + util.escapeHtml(label) + "</label>" +
      html + (hint ? '<p class="field-hint" style="margin-top:var(--space-xs)">' + hint + "</p>" : "") + "</div>";
  }

  function openSmtpModal() {
    var body = el("smtp-modal-body"), foot = el("smtp-modal-foot");
    if (!body || !foot) { return; }
    body.innerHTML = '' + EZ.ui.loading() + '';
    foot.innerHTML = "";
    EZ.ui.openModal("smtp-modal");
    EZ.api.get("/settings", { noGlobalError: true }).then(function (res) {
      var smtp = (res && res.data && res.data.smtp) || {};
      var sec = smtp.security || "tls";
      var secOpts = [["tls", "STARTTLS (port 587)"], ["ssl", "SSL/TLS (port 465)"], ["none", "None"]]
        .map(function (o) { return '<option value="' + o[0] + '"' + (sec === o[0] ? " selected" : "") + ">" + o[1] + "</option>"; }).join("");
      body.innerHTML =
        '<p class="field-hint">Send alert mail through your own mail server.</p>' +
        smtpField("Host", '<input class="input" id="smtp-host" placeholder="smtp.example.com" autocomplete="off" value="' + util.escapeHtml(smtp.host || "") + '">') +
        '<div style="display:flex;gap:var(--space-md)">' +
          '<div style="flex:0 0 130px">' + smtpField("Port", '<input class="input mono" id="smtp-port" type="number" min="1" max="65535" value="' + util.escapeHtml(String(smtp.port != null ? smtp.port : 587)) + '">') + "</div>" +
          '<div style="flex:1 1 auto">' + smtpField("Security", '<select class="input" id="smtp-security">' + secOpts + "</select>") + "</div>" +
        "</div>" +
        smtpField("Username", '<input class="input" id="smtp-username" autocomplete="off" value="' + util.escapeHtml(smtp.username || "") + '">', "Leave blank for an unauthenticated relay.") +
        smtpField("Password", '<input class="input" id="smtp-password" type="password" autocomplete="new-password" placeholder="' + (smtp.password_set ? "••••••••" : "") + '">', (smtp.password_set ? "A password is set. " : "") + "Type a new password to change it; leave blank to keep the current one.") +
        smtpField("From address", '<input class="input" id="smtp-from" type="email" placeholder="ezxss@example.com" autocomplete="off" value="' + util.escapeHtml(smtp.from || "") + '">') +
        '<p class="field-hint" id="smtp-modal-msg" hidden></p>';
      foot.innerHTML =
        '<button class="btn btn--ghost" type="button" data-close-modal>Cancel</button>' +
        '<button class="btn btn--primary" type="button" id="smtp-save">Save SMTP settings</button>';
      dom.on(el("smtp-save"), "click", saveSmtpModal);
      var h = el("smtp-host"); if (h) { h.focus(); }
    }).catch(function () { body.innerHTML = '<p class="field-hint">Could not load SMTP settings.</p>'; });
  }

  function saveSmtpModal() {
    var host = ((el("smtp-host") || {}).value || "").trim();
    var msg = el("smtp-modal-msg");
    function showMsg(t, err) { if (msg) { msg.hidden = !t; msg.textContent = t || ""; msg.classList.toggle("msg--error", !!err); } }
    if (host === "") { showMsg("Enter the SMTP host.", true); return; }
    var body = {
      smtp_enabled: true,
      smtp_host: host,
      smtp_port: (el("smtp-port") || {}).value || "587",
      smtp_security: (el("smtp-security") || {}).value || "tls",
      smtp_username: (el("smtp-username") || {}).value || "",
      smtp_from: ((el("smtp-from") || {}).value || "").trim()
    };
    var pw = (el("smtp-password") || {}).value || "";
    if (pw !== "") { body.smtp_password = pw; }
    showMsg("");
    saveSetting(body).then(function () {
      EZ.ui.toast("SMTP settings saved.", "success");
      var m = el("smtp-modal"); if (m) { m.classList.remove("is-open"); }
    }).catch(function (e) { showMsg((e && e.message) || "Could not save the SMTP settings.", true); });
  }

  EZ.alerts = {
    renderOwn: renderOwn,
    renderGlobal: renderGlobal
  };
})(window.EZ);

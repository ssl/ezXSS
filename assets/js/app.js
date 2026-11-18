window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util;

  function el(id) { return EZ.dom.byId(id); }

  function titleCase(s) {
    s = String(s || "");
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : "";
  }

  function resetClientState() {
    EZ.state.user = null;
    EZ.state.capabilities = {};
    EZ.state.booted = false;
    if (EZ.screens) {
      Object.keys(EZ.screens).forEach(function (name) {
        var s = EZ.screens[name];
        if (s && typeof s.resetState === "function") { try { s.resetState(); } catch (e) {} }
      });
    }
  }

  function loadMe() {
    return EZ.api.get("/me").then(function (res) {
      var data = res.data || {};
      EZ.state.user = data;
      EZ.state.capabilities = data.capabilities || {};
      EZ.state.booted = true;
    });
  }

  function applyIdentity() {
    var u = EZ.state.user || {};
    if (EZ.ui && EZ.ui.applyAvatar) { EZ.ui.applyAvatar(u.github); }
    var name = el("account") ? el("account").querySelector(".menu__name") : null;
    var role = el("account") ? el("account").querySelector(".menu__role") : null;
    if (name) { name.textContent = u.username || ""; }
    if (role) { role.textContent = u.role === "admin" ? "Administrator" : titleCase(u.role || "user"); }
  }

  function applyGates() {
    dom.qsa('.menu [data-screen="users"]').forEach(function (lk) {
      lk.hidden = !util.can("user.manage");
    });
    dom.qsa('.menu [data-screen="settings"]').forEach(function (lk) {
      lk.hidden = !util.can("settings.manage");
    });
    var hasAdmin = util.can("settings.manage") || util.can("user.manage");
    dom.qsa(".menu [data-admin-label], .menu [data-admin-sep]").forEach(function (el) {
      el.hidden = !hasAdmin;
    });
    dom.qsa(".menu [data-personal-label]").forEach(function (el) {
      el.hidden = !hasAdmin;
    });
  }

  function applyThemeResolved() {
    var stored = EZ.ui.storedTheme();
    if (stored !== null) { EZ.ui.applyTheme(stored, false); return; }
    EZ.api.get("/status", { noGlobalError: true }).then(function (res) {
      var theme = (res.data && res.data.theme) || "classic";
      EZ.ui.applyTheme(theme, false);
    }).catch(function () {  });
  }

  function finishBoot() {
    document.documentElement.classList.remove("is-booting");
  }

  function showApp() {
    var auth = el("auth"), app = el("app");
    if (app) { app.hidden = false; }
    if (auth) { auth.hidden = true; }
    finishBoot();
  }

  function onReady() {
    try { applyGates(); } catch (e) {}
    try { applyIdentity(); } catch (e) {}
    try { EZ.router.start(); } catch (e) {}
    showApp();
    try { applyThemeResolved(); } catch (e) {}
    try { if (EZ.notepad) { EZ.notepad.init(); } } catch (e) {}
  }

  function resume() {
    if (EZ.api.getToken()) {
      loadMe().then(onReady).catch(function () { finishBoot(); EZ.auth.showLogin(); });
    } else {
      finishBoot();
      EZ.auth.showLogin();
    }
  }

  function start() {
    EZ.ui.init();
    EZ.autosave.init();
    EZ.auth.init();

    EZ.api.get("/status", { noGlobalError: true }).then(function (res) {
      var state = (res.data && res.data.state) || "ready";
      if (state !== "ready") { window.location.href = "/"; return; }
      resume();
    }).catch(function () { resume(); });
  }

  EZ.boot = {
    start: start,
    resetState: resetClientState,
    afterLogin: function () {
      resetClientState();
      loadMe().then(onReady).catch(function () { EZ.auth.showLogin(); });
    }
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})(window.EZ);

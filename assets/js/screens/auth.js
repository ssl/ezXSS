window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom;
  var pendingChallenge = null;

  function el(id) { return EZ.dom.byId(id); }

  function showError(id, message) {
    var box = el(id);
    if (!box) { return; }
    if (message) { box.textContent = message; box.hidden = false; }
    else { box.textContent = ""; box.hidden = true; }
  }

  function setStep(step) {
    dom.qsa(".auth__step").forEach(function (s) {
      s.classList.toggle("is-active", s.getAttribute("data-step") === step);
    });
  }

  function showLogin() {
    var auth = el("auth"), app = el("app");
    if (auth) { auth.hidden = false; }
    if (app) { app.hidden = true; }
    pendingChallenge = null;
    setStep("login");
    showError("login-error", "");
    showError("twofa-error", "");
    if (EZ.ui) { EZ.ui.clearCode(); }
    var loginForm = el("login-form");
    if (loginForm && loginForm.reset) { loginForm.reset(); }
    var u = el("username"); if (u) { u.value = ""; }
    var pw = el("password"); if (pw) { pw.value = ""; }
    var signup = el("auth-signup");
    if (signup) {
      EZ.api.get("/status", { noGlobalError: true })
        .then(function (res) { signup.hidden = !(res && res.data && res.data.signup); })
        .catch(function () { signup.hidden = true; });
    }
  }

  function enterApp() {
    var auth = el("auth"), app = el("app");
    if (auth) { auth.hidden = true; }
    if (app) { app.hidden = false; }
    if (EZ.boot && EZ.boot.afterLogin) { EZ.boot.afterLogin(); }
  }

  function doLogin(username, password) {
    showError("login-error", "");
    return EZ.api.post("/auth/login", { username: username, password: password }, { noGlobalError: true })
      .then(function (res) {
        var data = res.data || {};
        if (data.twofa_required) {
          pendingChallenge = data.challenge;
          setStep("twofa");
          if (EZ.ui) { EZ.ui.clearCode(); }
          var first = document.querySelector(".code-input input");
          if (first) { setTimeout(function () { first.focus(); }, 60); }
          return;
        }
        if (data.token) {
          EZ.api.setToken(data.token);
          enterApp();
        }
      })
      .catch(function (err) {
        showError("login-error", err.message || "Sign in failed.");
      });
  }

  function doTwofa(code) {
    showError("twofa-error", "");
    if (!pendingChallenge) { showLogin(); return Promise.resolve(); }
    return EZ.api.post("/auth/twofa", { challenge: pendingChallenge, code: code }, { noGlobalError: true })
      .then(function (res) {
        var data = res.data || {};
        if (data.token) {
          EZ.api.setToken(data.token);
          pendingChallenge = null;
          enterApp();
        }
      })
      .catch(function (err) {
        showError("twofa-error", err.message || "Verification failed.");
        if (EZ.ui) { EZ.ui.clearCode(); }
        var first = document.querySelector(".code-input input");
        if (first) { first.focus(); }
      });
  }

  function doLogout() {
    EZ.api.post("/auth/logout", {}, { noGlobalError: true })["catch"](function () {})
      .then(function () {
        EZ.api.clearToken();
        if (EZ.boot && EZ.boot.resetState) { EZ.boot.resetState(); }
        else { EZ.state.user = null; EZ.state.capabilities = {}; }
        showLogin();
      });
  }

  function init() {
    var loginForm = el("login-form");
    if (loginForm && loginForm.reset) { loginForm.reset(); }
    var uInit = el("username"); if (uInit) { uInit.value = ""; }
    var pwInit = el("password"); if (pwInit) { pwInit.value = ""; }
    dom.on(loginForm, "submit", function (e) {
      e.preventDefault();
      var u = (el("username") || {}).value || "";
      var p = (el("password") || {}).value || "";
      doLogin(u.trim(), p);
    });

    var twofaForm = el("twofa-form");
    dom.on(twofaForm, "submit", function (e) {
      e.preventDefault();
      doTwofa(EZ.ui ? EZ.ui.readCode() : "");
    });

    var twofaBack = el("twofa-back");
    dom.on(twofaBack, "click", function () {
      pendingChallenge = null;
      setStep("login");
      showError("twofa-error", "");
    });

    dom.qsa("[data-logout]").forEach(function (b) {
      dom.on(b, "click", function (e) { e.preventDefault(); doLogout(); });
    });
  }

  EZ.auth = {
    init: init,
    showLogin: showLogin
  };
})(window.EZ);

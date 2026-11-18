(function () {
  "use strict";

  function el(id) { return document.getElementById(id); }

  function showError(msg) {
    var box = el("register-error");
    if (!box) { return; }
    if (msg) { box.textContent = msg; box.hidden = false; }
    else { box.textContent = ""; box.hidden = true; }
  }

  function validUsername(u) {
    return /^[A-Za-z0-9]{2,25}$/.test(u);
  }
  function validPassword(p) {
    return (p || "").length >= 12;
  }

  function initRegister() {
    var form = el("register-form");
    if (!form) { return; }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      showError("");

      var u = ((el("username") || {}).value || "").trim();
      var p = (el("password") || {}).value || "";
      var p2 = (el("password2") || {}).value || "";
      var code = ((el("code") || {}).value || "").trim();

      if (!validUsername(u)) {
        showError("Username must be 2-25 characters, letters and numbers only.");
        return;
      }
      if (!validPassword(p)) {
        showError("Password must be at least 12 characters.");
        return;
      }
      if (p !== p2) {
        showError("Passwords do not match.");
        return;
      }

      var btn = el("register-submit");
      if (btn) { btn.disabled = true; }

      var body = { username: u, password: p };
      if (code !== "") { body.code = code; }

      fetch("/api/register", {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify(body)
      }).then(function (res) {
        return res.text().then(function (text) {
          var json = null;
          if (text) { try { json = JSON.parse(text); } catch (err) { json = null; } }

          if (res.status === 201 && json && json.success) {
            window.location.href = "/manage";
            return;
          }

          var msg = (json && json.error) ? json.error : ("Sign up failed (" + res.status + ").");
          showError(msg);
          if (btn) { btn.disabled = false; }
        });
      }).catch(function () {
        showError("Could not reach the server. Check your connection and try again.");
        if (btn) { btn.disabled = false; }
      });
    });
  }

  function initCodeField() {
    var field = el("code-field");
    if (!field) { return; }
    fetch("/api/status", { headers: { "Accept": "application/json" } })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (json && json.data && json.data.signup_code_required) { field.hidden = false; }
      })["catch"](function () {  });
  }

  function start() {
    document.documentElement.classList.remove("is-booting");
    initRegister();
    initCodeField();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();

(function () {
  "use strict";

  function el(id) { return document.getElementById(id); }

  function showError(boxId, msg) {
    var box = el(boxId);
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

  function initInstall() {
    var form = el("install-form");
    if (!form) { return; }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      showError("install-error", "");

      var u = ((el("username") || {}).value || "").trim();
      var p = (el("password") || {}).value || "";
      var p2 = (el("password2") || {}).value || "";

      if (!validUsername(u)) {
        showError("install-error", "Username must be 2-25 characters, letters and numbers only.");
        return;
      }
      if (!validPassword(p)) {
        showError("install-error", "Password must be at least 12 characters.");
        return;
      }
      if (p !== p2) {
        showError("install-error", "Passwords do not match.");
        return;
      }

      var btn = el("install-submit");
      if (btn) { btn.disabled = true; }

      fetch("/api/install", {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify({ username: u, password: p })
      }).then(function (res) {
        return res.text().then(function (text) {
          var json = null;
          if (text) { try { json = JSON.parse(text); } catch (err) { json = null; } }

          if (res.status === 201 && json && json.success) {
            window.location.href = document.documentElement.getAttribute("data-panel") || "/manage";
            return;
          }

          var msg = (json && json.error) ? json.error : ("Install failed (" + res.status + ").");
          showError("install-error", msg);
          if (btn) { btn.disabled = false; }
        });
      }).catch(function () {
        showError("install-error", "Could not reach the server. Check your connection and try again.");
        if (btn) { btn.disabled = false; }
      });
    });
  }

  function storedToken() {
    try { return sessionStorage.getItem("ezxss_token"); } catch (e) { return null; }
  }

  function text(id, value) {
    var node = el(id);
    if (node) { node.textContent = value; }
  }

  function initUpdate() {
    var form = el("update-form");
    if (!form) { return; }

    var confirm = el("update-confirm");
    var submit = el("update-submit");

    if (confirm && submit) {
      confirm.addEventListener("change", function () { submit.disabled = !confirm.checked; });
    }

    fetch("/api/update", { headers: { "Accept": "application/json" } })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        var d = (json && json.data) || {};
        text("update-versions", (d.current_version || "unknown") + "  \u2192  " + (d.code_version || ""));

        if (d.legacy) {
          var lg = el("update-legacy");
          if (lg) { lg.hidden = false; }
        }

        var pending = d.pending || [];
        if (pending.length) {
          var wrap = el("update-pending-wrap");
          if (wrap) { wrap.hidden = false; }
          text("update-pending", pending.join("\n"));
        }
      })
      .catch(function () {  });

    var spinner = el("update-submit-spinner");
    var submitLabel = el("update-submit-label");

    function setBusy(on, label) {
      if (form) { form.setAttribute("aria-busy", on ? "true" : "false"); }
      if (submit) {
        submit.disabled = on ? true : (confirm ? !confirm.checked : false);
        submit.setAttribute("aria-busy", on ? "true" : "false");
      }
      if (spinner) { spinner.hidden = !on; }
      if (submitLabel) { submitLabel.textContent = on ? (label || "Upgrading...") : "Run update"; }
      var busy = el("update-busy");
      if (busy) { busy.hidden = !on; if (on) busy.style.display = "flex"; }
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      showError("update-error", "");

      if (confirm && !confirm.checked) {
        showError("update-error", "Please confirm you have backed up your database.");
        return;
      }

      setBusy(true, "Upgrading...");

      var headers = { "Content-Type": "application/json", "Accept": "application/json" };
      var token = storedToken();
      if (token) { headers["Authorization"] = "Bearer " + token; }

      fetch("/api/update", { method: "POST", headers: headers, body: "{}" })
        .then(function (res) {
          return res.text().then(function (body) {
            var json = null;
            if (body) { try { json = JSON.parse(body); } catch (err) { json = null; } }

            if (res.status >= 200 && res.status < 300 && json && json.success) {
              if (submitLabel) { submitLabel.textContent = "Done - redirecting..."; }
              var busyText = el("update-busy-text");
              if (busyText) { busyText.textContent = "Update complete. Taking you to the panel..."; }
              window.location.href = document.documentElement.getAttribute("data-panel") || "/manage";
              return;
            }

            var msg = (json && json.error) ? json.error : ("Update failed (" + res.status + ").");
            if (res.status === 401 || res.status === 403) {
              msg = "This update needs an admin. Log in to the panel first, then retry.";
            }
            setBusy(false);
            showError("update-error", msg);
          });
        })
        .catch(function () {
          setBusy(false);
          showError("update-error", "Could not reach the server. Check your connection and try again.");
        });
    });
  }

  function start() {
    document.documentElement.classList.remove("is-booting");
    initInstall();
    initUpdate();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();

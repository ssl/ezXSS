(function () {
  "use strict";
  var root = document.documentElement;

  function sanitize(name) {
    return String(name == null ? "" : name).toLowerCase().replace(/[^a-z0-9]/g, "");
  }
  function apply(name) {
    var t = sanitize(name);
    if (t === "default") { t = "dark"; }
    if (t && t !== "dark") { root.setAttribute("data-theme", t); }
    else { root.removeAttribute("data-theme"); }
  }

  var token = null;
  try { token = sessionStorage.getItem("ezxss_token"); } catch (e) {}

  var stored = null;
  try { stored = localStorage.getItem("ezxss_theme"); } catch (e) {}

  if (token && stored !== null) {
    apply(stored);
  }

  root.className += (root.className ? " " : "") + "is-booting";
})();

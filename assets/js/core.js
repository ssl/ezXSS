window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  EZ.config = {
    apiBase: "/api",
    managePrefix: "/manage",
    tokenKey: "ezxss_token",
    themeKey: "ezxss_theme"
  };

  EZ.state = {
    user: null,
    capabilities: {},
    booted: false
  };

  EZ.dom = {
    byId: function (id) { return document.getElementById(id); },
    qs: function (sel, ctx) { return (ctx || document).querySelector(sel); },
    qsa: function (sel, ctx) {
      return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
    },
    on: function (el, ev, fn, opts) { if (el) el.addEventListener(ev, fn, opts); }
  };

  EZ.util = {
    escapeHtml: function (s) {
      return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
      });
    },
    debounce: function (fn, wait) {
      var t;
      return function () {
        var ctx = this, args = arguments;
        clearTimeout(t);
        t = setTimeout(function () { fn.apply(ctx, args); }, wait);
      };
    },
    can: function (cap) {
      return !!(EZ.state.capabilities && EZ.state.capabilities[cap]);
    },
    readInitialSearch: function () {
      var q = "";
      try {
        q = new URLSearchParams(location.search).get("search") || "";
      } catch (e) {
        var m = /[?&]search=([^&]*)/.exec(location.search || "");
        if (m) { try { q = decodeURIComponent(m[1].replace(/\+/g, " ")); } catch (e2) { q = ""; } }
      }
      return q;
    }
  };

  var FMT_DASH = "-";
  var FMT_MID = "·";
  function fmtAgo(unix) {
    if (unix == null || unix === "") { return FMT_DASH; }
    var secs = Math.floor(Date.now() / 1000) - Number(unix);
    if (!isFinite(secs)) { return FMT_DASH; }
    if (secs < 0) { secs = 0; }
    if (secs < 5) { return "just now"; }
    if (secs < 60) { return secs + "s ago"; }
    var mins = Math.floor(secs / 60);
    if (mins < 60) { return mins + "m ago"; }
    var hrs = Math.floor(mins / 60);
    if (hrs < 24) { return hrs + "h ago"; }
    var days = Math.floor(hrs / 24);
    if (days < 30) { return days + "d ago"; }
    var months = Math.floor(days / 30);
    if (months < 12) { return months + "mo ago"; }
    return Math.floor(months / 12) + "y ago";
  }
  function fmtDateTime(unix) {
    if (unix == null || unix === "") { return FMT_DASH; }
    var d = new Date(Number(unix) * 1000);
    if (isNaN(d.getTime())) { return FMT_DASH; }
    return d.toLocaleString([], {
      year: "numeric", month: "short", day: "numeric",
      hour: "2-digit", minute: "2-digit", second: "2-digit"
    });
  }
  function uaVer(ua, re) { var m = ua.match(re); return m ? " " + m[1] : ""; }
  function parseBrowser(ua) {
    ua = String(ua || "");
    if (!ua) { return FMT_DASH; }
    var browser = "";
    if (/Edg\//.test(ua)) { browser = "Edge" + uaVer(ua, /Edg\/(\d+)/); }
    else if (/OPR\//.test(ua)) { browser = "Opera" + uaVer(ua, /OPR\/(\d+)/); }
    else if (/Firefox\//.test(ua)) { browser = "Firefox" + uaVer(ua, /Firefox\/(\d+)/); }
    else if (/Edge\//.test(ua)) { browser = "Edge" + uaVer(ua, /Edge\/(\d+)/); }
    else if (/Chrome\//.test(ua)) { browser = "Chrome" + uaVer(ua, /Chrome\/(\d+)/); }
    else if (/Version\/.*Safari/.test(ua)) { browser = "Safari" + uaVer(ua, /Version\/(\d+)/); }
    else if (/MSIE |Trident/.test(ua)) { browser = "Internet Explorer"; }
    var os = "";
    if (/iPhone|iPad|iPod/.test(ua)) { os = "iOS"; }
    else if (/Android/.test(ua)) { os = "Android"; }
    else if (/Windows/.test(ua)) { os = "Windows"; }
    else if (/Mac OS X|Macintosh/.test(ua)) { os = "macOS"; }
    else if (/CrOS/.test(ua)) { os = "ChromeOS"; }
    else if (/Linux/.test(ua)) { os = "Linux"; }
    var parts = [];
    if (browser) { parts.push(browser); }
    if (os) { parts.push(os); }
    return parts.length ? parts.join(" " + FMT_MID + " ") : "Unknown";
  }
  EZ.format = {
    fmtAgo: fmtAgo,
    fmtDateTime: fmtDateTime,
    parseBrowser: parseBrowser
  };
})(window.EZ);

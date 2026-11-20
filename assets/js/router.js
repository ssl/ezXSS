window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom;
  EZ.screens = EZ.screens || {};

  var ROUTES = [
    { segs: [], screen: "dashboard" },
    { segs: ["reports"], screen: "reports" },
    { segs: ["reports", ":id"], screen: "report" },
    { segs: ["payloads"], screen: "payloads" },
    { segs: ["payloads", ":id"], screen: "payloads" },
    { segs: ["sessions"], screen: "sessions" },
    { segs: ["sessions", ":id"], screen: "sessions" },
    { segs: ["sessions", ":id", "requests"], screen: "session-requests" },
    { segs: ["users"], screen: "users" },
    { segs: ["search"], screen: "search" },
    { segs: ["settings"], screen: "settings" },
    { segs: ["account"], screen: "account" },
    { segs: ["docs"], screen: "docs" }
  ];

  var SCREEN_PATH = {
    dashboard: function () { return []; },
    reports: function () { return ["reports"]; },
    report: function (p) { return ["reports", (p && p.id) || ""]; },
    payloads: function (p) { return (p && p.id) ? ["payloads", p.id] : ["payloads"]; },
    sessions: function (p) { return (p && p.id) ? ["sessions", p.id] : ["sessions"]; },
    "session-requests": function (p) { return ["sessions", (p && p.id) || "", "requests"]; },
    users: function () { return ["users"]; },
    search: function () { return ["search"]; },
    settings: function () { return ["settings"]; },
    account: function () { return ["account"]; },
    docs: function () { return ["docs"]; }
  };

  var current = null;

  function pathSegments() {
    var path = location.pathname || "";
    var prefix = EZ.config.managePrefix;
    if (path.indexOf(prefix) === 0) { path = path.slice(prefix.length); }
    path = path.replace(/^\/+|\/+$/g, "");
    return path === "" ? [] : path.split("/");
  }

  function match(segs) {
    for (var i = 0; i < ROUTES.length; i++) {
      var r = ROUTES[i];
      if (r.segs.length !== segs.length) { continue; }
      var params = {}, ok = true;
      for (var j = 0; j < r.segs.length; j++) {
        if (r.segs[j].charAt(0) === ":") { params[r.segs[j].slice(1)] = decodeURIComponent(segs[j]); }
        else if (r.segs[j] !== segs[j]) { ok = false; break; }
      }
      if (ok) { return { screen: r.screen, params: params }; }
    }
    return { screen: "dashboard", params: {} };
  }

  function buildPath(screen, params) {
    var fn = SCREEN_PATH[screen];
    var segs = fn ? fn(params) : [];
    segs = segs.filter(function (s) { return s !== "" && s != null; });
    return EZ.config.managePrefix + (segs.length ? "/" + segs.join("/") : "");
  }

  function resolve() {
    var m = match(pathSegments());
    var def = EZ.screens[m.screen] || null;

    if (current && current.def && current.def.unmount && current.screen !== m.screen) {
      try { current.def.unmount(); } catch (e) {}
    }

    if (EZ.ui) { EZ.ui.showScreen(m.screen); }

    var el = document.getElementById("screen-" + m.screen);
    current = { screen: m.screen, def: def };
    if (def && def.mount) {
      try { def.mount({ screen: m.screen, params: m.params, el: el }); } catch (e2) { console.error("[router] mount " + m.screen + " failed:", e2); }
    }
  }

  function go(path) {
    if (path !== location.pathname + location.search) {
      history.pushState(null, "", path);
    }
    resolve();
  }

  function navigate(screen, params) { go(buildPath(screen, params)); }

  var started = false;
  function start() {
    if (!started) {
      started = true;
      dom.on(window, "popstate", function () { resolve(); });
      dom.on(document, "click", function (e) {
        var t = e.target.closest("[data-screen]");
        if (!t) { return; }
        var app = document.getElementById("app");
        if (app && app.hidden) { return; }
        e.preventDefault();
        var params = {};
        if (t.getAttribute("data-id")) { params.id = t.getAttribute("data-id"); }
        navigate(t.getAttribute("data-screen"), params);
      });
    }
    resolve();
  }

  EZ.router = {
    register: function (name, def) { EZ.screens[name] = def; },
    start: start,
    resolve: resolve,
    go: go,
    navigate: navigate
  };
})(window.EZ);

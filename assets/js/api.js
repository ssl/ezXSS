window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var cfg = EZ.config;

  function getToken() {
    try { return sessionStorage.getItem(cfg.tokenKey); } catch (e) { return null; }
  }
  function setToken(token) {
    try {
      if (token) { sessionStorage.setItem(cfg.tokenKey, token); }
      else { sessionStorage.removeItem(cfg.tokenKey); }
    } catch (e) {  }
  }
  function clearToken() { setToken(null); }

  function rawCall(method, path, body) {
    var headers = { "Accept": "application/json" };
    var token = getToken();
    if (token) { headers["Authorization"] = "Bearer " + token; }

    var opts = { method: method, headers: headers };
    if (body !== undefined && body !== null) {
      headers["Content-Type"] = "application/json";
      opts.body = JSON.stringify(body);
    }

    return fetch(cfg.apiBase + path, opts).then(function (res) {
      return res.text().then(function (text) {
        var json = null;
        if (text) { try { json = JSON.parse(text); } catch (e) { json = null; } }

        if (res.ok && json && json.success) {
          return { data: json.data, meta: json.meta || {} };
        }

        var message = (json && json.error)
          ? json.error
          : ("Request failed (" + res.status + ")");
        var err = new Error(message);
        err.status = res.status;
        err.payload = json;
        throw err;
      });
    });
  }

  function withGlobalError(promise) {
    return promise.catch(function (err) {
      if (err.status === 401) {
        clearToken();
        if (EZ.ui && EZ.ui.toast) { EZ.ui.toast("Your session has expired. Please sign in again.", "error"); }
        if (EZ.auth && EZ.auth.showLogin) { EZ.auth.showLogin(); }
      } else if (EZ.ui && EZ.ui.toast) {
        EZ.ui.toast(err.message, "error");
      }
      throw err;
    });
  }

  function call(method, path, body, opts) {
    var p = rawCall(method, path, body);
    return (opts && opts.noGlobalError) ? p : withGlobalError(p);
  }

  EZ.api = {
    base: cfg.apiBase,
    getToken: getToken,
    setToken: setToken,
    clearToken: clearToken,
    get: function (path, opts) { return call("GET", path, null, opts); },
    post: function (path, body, opts) { return call("POST", path, body, opts); },
    del: function (path, opts) { return call("DELETE", path, null, opts); }
  };
})(window.EZ);

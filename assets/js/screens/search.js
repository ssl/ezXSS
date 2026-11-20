window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util, api = EZ.api;
  var PER = 5;
  var DASH = "-";

  function canUsers() { return !!(util && util.can && util.can("user.manage")); }

  function readQ() {
    var s = location.search || "";
    try {
      var p = new URLSearchParams(s);
      return (p.get("q") || p.get("search") || "").trim();
    } catch (e) {
      var m = /[?&](?:q|search)=([^&]*)/.exec(s);
      if (m) { try { return decodeURIComponent(m[1].replace(/\+/g, " ")).trim(); } catch (e2) {} }
      return "";
    }
  }

  function joinMeta(parts) {
    return parts.filter(function (x) { return x != null && x !== ""; }).join(" · ");
  }

  function rowHTML(screen, id, title, meta, when, search) {
    return '<button type="button" class="sresult" data-go="' + screen + '"' +
      (id != null ? ' data-id="' + util.escapeHtml(String(id)) + '"' : "") +
      (search ? ' data-search="' + util.escapeHtml(search) + '"' : "") + ">" +
      '<span class="sresult__main">' +
        '<span class="sresult__title truncate">' + util.escapeHtml(title) + "</span>" +
        (meta ? '<span class="sresult__meta truncate">' + util.escapeHtml(meta) + "</span>" : "") +
      "</span>" +
      (when ? '<span class="sresult__when">' + util.escapeHtml(when) + "</span>" : "") +
    "</button>";
  }

  var SCOPES = [
    {
      key: "reports", label: "Reports", endpoint: "/reports",
      row: function (r) {
        return rowHTML("report", r.id,
          r.uri || r.origin || "(no URL)",
          joinMeta([r.ip, r.origin]),
          EZ.format.fmtAgo(r.created_at));
      }
    },
    {
      key: "sessions", label: "Sessions", endpoint: "/sessions",
      row: function (r) {
        return rowHTML("sessions", r.id,
          r.origin || "(no origin)",
          joinMeta([r.client_id, r.browser, (r.requests != null ? r.requests + " req" : null)]),
          EZ.format.fmtAgo(r.last));
      }
    },
    {
      key: "users", label: "Users", endpoint: "/users", adminOnly: true,
      row: function (r) {
        return rowHTML("users", null, r.username || "(no name)", r.role || "user", "", r.username);
      }
    }
  ];

  function scopeCard(s) {
    return '<div class="card" data-scope="' + s.key + '" style="margin-bottom:var(--space-lg)">' +
      '<div class="card__head"><h2 class="card__title">' + s.label +
        ' <span class="tag tag--muted" data-count>...</span></h2></div>' +
      '<div data-body>' + EZ.ui.loading("Searching...") + '</div>' +
      '<div class="pagination" data-pager></div>' +
    "</div>";
  }

  function bindRows(body) {
    dom.qsa("[data-go]", body).forEach(function (b) {
      dom.on(b, "click", function () {
        var screen = b.getAttribute("data-go");
        var id = b.getAttribute("data-id");
        var search = b.getAttribute("data-search");
        if (screen === "users") {
          EZ.router.go(EZ.config.managePrefix + "/users" + (search ? "?search=" + encodeURIComponent(search) : ""));
        } else if (screen === "report") {
          EZ.router.navigate("report", { id: id });
        } else if (screen === "sessions") {
          EZ.router.navigate("sessions", { id: id });
        }
      });
    });
  }

  function loadScope(root, s, q, page) {
    var card = root.querySelector('[data-scope="' + s.key + '"]');
    if (!card) { return; }
    var body = card.querySelector("[data-body]");
    var pager = card.querySelector("[data-pager]");
    var count = card.querySelector("[data-count]");
    if (body && body.querySelector(".sresults")) {
      body.style.position = "relative";
      body.insertAdjacentHTML("beforeend", EZ.ui.loading("Searching...", "overlay"));
      if (pager) { dom.qsa("button", pager).forEach(function (b) { b.disabled = true; }); }
    }
    var qstr = "?search=" + encodeURIComponent(q) + "&per_page=" + PER + "&page=" + page;
    api.get(s.endpoint + qstr, { noGlobalError: true }).then(function (res) {
      var rows = res.data || [];
      var meta = (res.meta && res.meta.pagination) || {};
      if (count) { count.textContent = String(meta.total != null ? meta.total : rows.length); }
      if (!rows.length) {
        body.innerHTML = '<p class="field-hint">No ' + s.label.toLowerCase() +
          " match “" + util.escapeHtml(q) + "”.</p>";
        if (pager) { pager.innerHTML = ""; }
        return;
      }
      body.innerHTML = '<div class="sresults">' + rows.map(s.row).join("") + "</div>";
      bindRows(body);
      if (pager && EZ.ui && EZ.ui.renderPager) {
        EZ.ui.renderPager(pager, meta, function (p) { loadScope(root, s, q, p); });
      }
    }).catch(function () {
      if (body) { body.innerHTML = '<p class="field-hint">Could not search ' + s.label.toLowerCase() + ".</p>"; }
      if (count) { count.textContent = "0"; }
      if (pager) { pager.innerHTML = ""; }
    });
  }

  EZ.router.register("search", {
    mount: function (ctx) {
      var root = (ctx && ctx.el) ? ctx.el.querySelector("[data-search-root]")
        : dom.qs("#screen-search [data-search-root]");
      if (!root) { return; }
      var q = readQ();
      if (!q) {
        root.innerHTML = '<p class="field-hint">Type a search in the top bar to look across reports, sessions' +
          (canUsers() ? " and users" : "") + ".</p>";
        return;
      }
      var scopes = SCOPES.filter(function (s) { return !s.adminOnly || canUsers(); });
      root.innerHTML =
        '<p class="field-hint" style="margin-bottom:var(--space-lg)">Results for “<b>' +
          util.escapeHtml(q) + "</b>”</p>" +
        scopes.map(scopeCard).join("");
      scopes.forEach(function (s) { loadScope(root, s, q, 1); });
    }
  });
})(window.EZ);

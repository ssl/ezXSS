window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var util = EZ.util;
  var esc = util.escapeHtml;
  var spec = null;

  function verbClass(m) {
    if (m === "GET") { return "verb--get"; }
    if (m === "POST" || m === "PUT" || m === "PATCH") { return "verb--post"; }
    if (m === "DELETE") { return "verb--delete"; }
    return "verb--other";
  }

  function introCard(c) {
    return '<div class="card docs-card">' +
      '<div class="card__head"><h2 class="card__title">' + esc(c.title) + '</h2></div>' +
      c.html +
      '</div>';
  }

  function endpointRow(r) {
    var hasParams = r.params && r.params !== "-";
    return '<div class="ep">' +
      '<div class="ep__head">' +
        '<span class="verb ' + verbClass(r.m) + '">' + esc(r.m) + '</span>' +
        '<span class="ep__path mono">' + esc(r.p) + '</span>' +
        '<span class="ep__auth">' + esc(r.a) + '</span>' +
      '</div>' +
      '<p class="ep__desc">' + esc(r.d) + '</p>' +
      (hasParams ? '<p class="ep__params mono">' + esc(r.params) + '</p>' : "") +
      '</div>';
  }

  function groupCard(g) {
    var rows = g.rows.map(endpointRow).join("");
    return '<div class="card docs-card">' +
      '<div class="card__head"><h2 class="card__title">' + esc(g.title) + '</h2></div>' +
      (g.note ? '<p class="field-hint" style="margin-bottom:var(--space-md)">' + esc(g.note) + '</p>' : "") +
      '<div class="ep-list">' + rows + '</div>' +
      '</div>';
  }

  function renderSpec(root, data) {
    var html = "";
    html += (data.intro || []).map(introCard).join("");
    html += (data.groups || []).map(groupCard).join("");
    root.innerHTML = html;
    root.dataset.rendered = "1";
  }

  function render(root) {
    if (!root || root.dataset.rendered === "1") { return; }
    if (spec) { renderSpec(root, spec); return; }
    root.innerHTML = '<p class="field-hint">Loading API reference…</p>';
    fetch("/assets/js/api-spec.json").then(function (res) {
      return res.json();
    }).then(function (data) {
      spec = data;
      renderSpec(root, data);
    }).catch(function () {
      root.innerHTML = '<p class="field-hint">Could not load the API reference.</p>';
    });
  }

  EZ.router.register("docs", {
    mount: function (ctx) {
      var root = (ctx && ctx.el) ? ctx.el.querySelector("[data-docs-root]") : null;
      render(root);
    }
  });
})(window.EZ);

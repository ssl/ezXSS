window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util;

  var DASH = "-";

  function fmtNum(n) {
    var v = Number(n);
    return isFinite(v) ? v.toLocaleString() : DASH;
  }

  function setStat(name, text) {
    dom.qsa('[data-stat="' + name + '"]').forEach(function (el) { el.textContent = text; });
  }

  function setDelta(name, week) {
    var v = Number(week);
    dom.qsa('[data-delta="' + name + '"]').forEach(function (el) {
      if (isFinite(v) && v > 0) {
        el.textContent = "+" + v.toLocaleString() + " · 7d";
        el.hidden = false;
      } else {
        el.textContent = "";
        el.hidden = true;
      }
    });
  }

  function loadStats() {
    EZ.api.get("/dashboard").then(function (res) {
      var d = res.data || {};
      setStat("reports", fmtNum(d.reports));
      setStat("report_domains", fmtNum(d.report_domains));
      setStat("collected_pages", fmtNum(d.collected_pages));
      setStat("last_report", EZ.format.fmtAgo(d.last_report));
      setStat("session_clients", fmtNum(d.session_clients));
      setStat("session_requests", fmtNum(d.session_requests));
      setStat("session_domains", fmtNum(d.session_domains));
      setStat("last_client", EZ.format.fmtAgo(d.last_client));
      setDelta("reports_week", d.reports_week);
      setDelta("report_domains_week", d.report_domains_week);
      setDelta("session_clients_week", d.session_clients_week);
      setDelta("session_requests_week", d.session_requests_week);
    }).catch(function () {  });
  }


  function renderCommon(slot, field) {
    var list = dom.qs('[data-common-list="' + slot + '"]');
    if (!list) { return; }
    list.innerHTML = '' + EZ.ui.loading() + '';
    EZ.api.get("/dashboard/common?field=" + encodeURIComponent(field)).then(function (res) {
      var rows = res.data || [];
      if (!rows.length) {
        list.innerHTML = '<p class="faint toplist__note">No data yet.</p>';
        return;
      }
      list.innerHTML = '<div class="toplist">' + rows.map(function (r, i) {
        return '<div class="toprow">' +
          '<div class="toprow__head">' +
            '<span class="toprow__rank">' + (i + 1) + "</span>" +
            '<span class="toprow__label mono" title="' + util.escapeHtml(r.value) + '">' + util.escapeHtml(r.value) + "</span>" +
            '<span class="toprow__count">' + fmtNum(r.count) + "</span>" +
          "</div>" +
          "</div>";
      }).join("") + "</div>";
    }).catch(function () {
      list.innerHTML = '<p class="faint toplist__note">Could not load.</p>';
    });
  }

  function loadVersion() {
    var root = dom.qs("[data-version-root]");
    if (!root) { return; }
    var current = root.querySelector("[data-version-current]");
    var latestField = root.querySelector("[data-version-latest-field]");
    var latest = root.querySelector("[data-version-latest]");
    var status = root.querySelector("[data-version-status]");
    var changelog = root.querySelector("[data-version-changelog]");


    EZ.api.get("/dashboard/version").then(function (res) {
      var d = res.data || {};
      if (current) { current.textContent = d.current ? "v" + d.current : DASH; }

      if (!d.latest) {
        if (latestField) { latestField.hidden = true; }
        if (changelog) { changelog.hidden = true; }
        if (status) { status.textContent = "Latest release information is unavailable right now."; }
        return;
      }

      if (latest) { latest.textContent = "v" + String(d.latest); }
      if (latestField) { latestField.hidden = false; }

      var state = d.state || (d.update_available ? "update_available" : "up_to_date");
      if (status) {
        if (state === "update_available") {
          status.textContent = "A newer version (v" + String(d.latest) + ") is available.";
        } else if (state === "development") {
          status.textContent = "Development build (ahead of latest release v" + String(d.latest) + ").";
        } else {
          status.textContent = "You are running the latest version.";
        }
      }

      if (changelog) {
        if (d.changelog) {
          changelog.textContent = String(d.changelog);
          changelog.hidden = false;
        } else {
          changelog.hidden = true;
        }
      }
    }).catch(function () {
      if (status) { status.textContent = "Could not check for updates."; }
    });
  }

  function hasOption(sel, value) {
    var opts = sel.options || [];
    for (var i = 0; i < opts.length; i++) {
      if (opts[i].value === value) { return true; }
    }
    return false;
  }

  var PREFS_KEY = "ez.dash.prefs";

  function loadPrefs() {
    try { return JSON.parse(localStorage.getItem(PREFS_KEY)) || {}; }
    catch (e) { return {}; }
  }

  function savePrefs() {
    var prefs = {};
    dom.qsa("[data-common]").forEach(function (sel) {
      prefs["row" + sel.getAttribute("data-common")] = sel.value;
    });
    try { localStorage.setItem(PREFS_KEY, JSON.stringify(prefs)); } catch (e) {}
  }

  var VERSION_HIDE_KEY = "ez.dash.versioncard.hidden";

  function versionCardDismissed() {
    try { return sessionStorage.getItem(VERSION_HIDE_KEY) === "1"; }
    catch (e) { return false; }
  }
  function dismissVersionCard() {
    try { sessionStorage.setItem(VERSION_HIDE_KEY, "1"); } catch (e) {}
    var card = dom.qs("#version-card");
    if (card) { card.hidden = true; }
  }
  function isAdmin() {
    return util.can("settings.manage");
  }

  var dismissBound = false;
  function bindVersionDismiss() {
    if (dismissBound) { return; }
    var btn = dom.qs("#version-card-dismiss");
    if (!btn) { return; }
    dismissBound = true;
    dom.on(btn, "click", dismissVersionCard);
  }

  EZ.router.register("dashboard", {
    mount: function () {
      loadStats();

      var card = dom.qs("#version-card");
      if (card) {
        bindVersionDismiss();
        if (isAdmin() && !versionCardDismissed()) {
          card.hidden = false;
          loadVersion();
        } else {
          card.hidden = true;
        }
      }

      var prefs = loadPrefs();
      dom.qsa("[data-common]").forEach(function (sel) {
        var slot = sel.getAttribute("data-common");
        var saved = prefs["row" + slot];
        if (saved && hasOption(sel, saved)) { sel.value = saved; }
        renderCommon(slot, sel.value);
        if (!sel.dataset.bound) {
          sel.dataset.bound = "1";
          dom.on(sel, "change", function () {
            renderCommon(slot, sel.value);
            savePrefs();
          });
        }
      });
    },
    unmount: function () {
      var card = dom.qs("#version-card");
      if (card) { card.hidden = true; }
    }
  });
})(window.EZ);

window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util;
  var DEBOUNCE_MS = 550;
  var builders = {};
  var seq = 0;
  var lastSeqByControl = new WeakMap();
  var lastGoodByControl = new WeakMap();

  function isTextual(el) {
    if (el.tagName === "TEXTAREA") { return true; }
    if (el.tagName !== "INPUT") { return false; }
    var t = (el.getAttribute("type") || "text").toLowerCase();
    return ["checkbox", "radio", "range", "color", "file"].indexOf(t) === -1;
  }
  function isToggleOrSelect(el) {
    if (el.tagName === "SELECT") { return true; }
    if (el.tagName !== "INPUT") { return false; }
    var t = (el.getAttribute("type") || "text").toLowerCase();
    return t === "checkbox" || t === "radio" || t === "range" || t === "color";
  }

  function readValue(el) {
    if (el.tagName === "INPUT" && el.getAttribute("type") === "checkbox") { return el.checked; }
    return el.value;
  }
  function writeValue(el, value) {
    if (el.tagName === "INPUT" && el.getAttribute("type") === "checkbox") { el.checked = !!value; }
    else { el.value = value; }
  }

  function resolve(el) {
    if (!el || !el.getAttribute) { return null; }
    var field = el.getAttribute("data-field");
    if (!field) { return null; }
    var scope = el.closest("[data-autosave]");
    if (!scope) { return null; }
    var endpointEl = el.hasAttribute("data-endpoint") ? el : el.closest("[data-endpoint]");
    var endpoint = endpointEl ? endpointEl.getAttribute("data-endpoint") : null;
    if (!endpoint) { return null; }
    return {
      field: field,
      endpoint: endpoint,
      scope: scope,
      builder: el.getAttribute("data-autosave-builder") || scope.getAttribute("data-autosave-builder") || null
    };
  }

  function buildBody(el, ctx) {
    if (ctx.builder && builders[ctx.builder]) {
      return builders[ctx.builder](el, ctx);
    }
    var body = {};
    body[ctx.field] = readValue(el);
    return body;
  }

  function persist(el) {
    var ctx = resolve(el);
    if (!ctx || !EZ.api) { return; }

    var mySeq = ++seq;
    lastSeqByControl.set(el, mySeq);

    var body;
    try { body = buildBody(el, ctx); }
    catch (e) { return; }
    if (!body) { return; }

    EZ.api.post(ctx.endpoint, body).then(function () {
      if (lastSeqByControl.get(el) !== mySeq) { return; }
      lastGoodByControl.set(el, readValue(el));
      if (EZ.ui) { EZ.ui.flashSaved(); }
    }).catch(function () {
      if (lastSeqByControl.get(el) !== mySeq) { return; }
      if (lastGoodByControl.has(el)) { writeValue(el, lastGoodByControl.get(el)); }
      try { el.focus(); } catch (e2) {}
    });
  }

  function snapshot(el) {
    if (resolve(el) && !lastGoodByControl.has(el)) {
      lastGoodByControl.set(el, readValue(el));
    }
  }

  function init() {
    dom.qsa("[data-autosave] [data-field]").forEach(snapshot);

    dom.on(document, "change", function (e) {
      var el = e.target;
      if (isToggleOrSelect(el)) { persist(el); }
    });

    dom.on(document, "focusout", function (e) {
      var el = e.target;
      if (isTextual(el) && resolve(el)) { persist(el); }
    }, true);

    var debouncedInput = util.debounce(function (el) { persist(el); }, DEBOUNCE_MS);
    dom.on(document, "input", function (e) {
      var el = e.target;
      if (isTextual(el) && resolve(el)) { snapshot(el); debouncedInput(el); }
    });
  }

  EZ.autosave = {
    init: init,
    register: function (name, fn) { builders[name] = fn; },
    snapshot: function (rootEl) { dom.qsa("[data-field]", rootEl || document).forEach(snapshot); },
    persist: persist
  };
})(window.EZ);

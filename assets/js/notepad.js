window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util;
  var DEBOUNCE_MS = 550;

  var LS_POS = "ezxss_notepad_pos";
  var LS_SIZE = "ezxss_notepad_size";

  var overlay, textarea, header;
  var built = false;
  var openState = false;
  var inited = false;
  var suspended = false;
  var saveTimer = null;

  function readLS(key) {
    try {
      var raw = localStorage.getItem(key);
      return raw ? JSON.parse(raw) : null;
    } catch (e) { return null; }
  }
  function writeLS(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) {}
  }

  function save() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () { saveTimer = null; saveNow(); }, DEBOUNCE_MS);
  }

  function saveNow() {
    if (!textarea || !EZ.api || suspended) { return; }
    EZ.api.post("/account/notepad", { notepad: textarea.value }).then(function () {
      if (EZ.ui) { EZ.ui.flashSaved(); }
    }).catch(function () {});
  }

  function clamp(left, top) {
    var w = overlay.offsetWidth || 320;
    var h = 48;
    var maxLeft = Math.max(0, window.innerWidth - w);
    var maxTop = Math.max(0, window.innerHeight - h);
    return {
      left: Math.min(Math.max(0, left), maxLeft),
      top: Math.min(Math.max(0, top), maxTop)
    };
  }

  function applyStored() {
    var size = readLS(LS_SIZE);
    if (size && size.w && size.h) {
      overlay.style.width = size.w + "px";
      overlay.style.height = size.h + "px";
    }
    var pos = readLS(LS_POS);
    if (pos && typeof pos.left === "number" && typeof pos.top === "number") {
      var c = clamp(pos.left, pos.top);
      overlay.style.left = c.left + "px";
      overlay.style.top = c.top + "px";
      overlay.style.right = "auto";
      overlay.style.bottom = "auto";
    }
  }

  function initDrag() {
    var dragging = false, startX = 0, startY = 0, baseLeft = 0, baseTop = 0;

    function onMove(e) {
      if (!dragging) { return; }
      var c = clamp(baseLeft + (e.clientX - startX), baseTop + (e.clientY - startY));
      overlay.style.left = c.left + "px";
      overlay.style.top = c.top + "px";
      overlay.style.right = "auto";
      overlay.style.bottom = "auto";
    }
    function onUp() {
      if (!dragging) { return; }
      dragging = false;
      overlay.classList.remove("is-dragging");
      writeLS(LS_POS, { left: overlay.offsetLeft, top: overlay.offsetTop });
      document.removeEventListener("pointermove", onMove);
      document.removeEventListener("pointerup", onUp);
    }
    dom.on(header, "pointerdown", function (e) {
      if (e.target.closest("[data-notepad-close]")) { return; }
      dragging = true;
      overlay.classList.add("is-dragging");
      var rect = overlay.getBoundingClientRect();
      baseLeft = rect.left; baseTop = rect.top;
      startX = e.clientX; startY = e.clientY;
      overlay.style.left = baseLeft + "px";
      overlay.style.top = baseTop + "px";
      overlay.style.right = "auto";
      overlay.style.bottom = "auto";
      document.addEventListener("pointermove", onMove);
      document.addEventListener("pointerup", onUp);
      e.preventDefault();
    });
  }

  function initResizePersist() {
    var persist = util.debounce(function () {
      writeLS(LS_SIZE, { w: overlay.offsetWidth, h: overlay.offsetHeight });
    }, 300);
    if (typeof ResizeObserver !== "undefined") {
      var ro = new ResizeObserver(function () { if (openState) { persist(); } });
      ro.observe(overlay);
    }
  }

  function build() {
    if (built) { return; }
    overlay = document.createElement("section");
    overlay.className = "notepad";
    overlay.id = "notepad";
    overlay.setAttribute("aria-label", "Notepad");
    overlay.hidden = true;
    overlay.innerHTML =
      '<div class="notepad__head">' +
        '<span class="notepad__title">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
            '<path d="M5 4h9l5 5v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/>' +
            '<path d="M13 4v5h5M8 13h8M8 17h5" stroke-linecap="round" stroke-linejoin="round"/>' +
          "</svg>Notepad</span>" +
        '<button class="iconbtn notepad__close" type="button" data-notepad-close aria-label="Close notepad">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>' +
        "</button>" +
      "</div>" +
      '<textarea class="notepad__area input" spellcheck="false" placeholder="Jot anything down - it saves as you type."></textarea>';
    document.body.appendChild(overlay);

    textarea = overlay.querySelector(".notepad__area");
    header = overlay.querySelector(".notepad__head");

    seed();

    dom.on(textarea, "input", save);
    dom.on(textarea, "blur", saveNow);
    dom.on(overlay.querySelector("[data-notepad-close]"), "click", close);

    initDrag();
    initResizePersist();

    built = true;
  }

  function seed() {
    if (!textarea) { return; }
    var u = EZ.state && EZ.state.user;
    if (u && typeof u.notepad === "string" && document.activeElement !== textarea) {
      textarea.value = u.notepad;
    }
  }

  function open() {
    build();
    suspended = false;
    seed();
    applyStored();
    overlay.hidden = false;
    openState = true;
    if (EZ.ui && EZ.ui.closeAccount) { EZ.ui.closeAccount(); }
    try { textarea.focus(); } catch (e) {}
  }

  function close() {
    if (!overlay) { return; }
    overlay.hidden = true;
    openState = false;
  }

  function toggle() { if (openState) { close(); } else { open(); } }

  function reset() {
    clearTimeout(saveTimer);
    saveTimer = null;
    suspended = true;
    close();
    if (textarea) { textarea.value = ""; }
  }

  function init() {
    if (inited) { return; }
    inited = true;
    dom.on(document, "click", function (e) {
      var trigger = e.target.closest("[data-notepad-open]");
      if (!trigger) { return; }
      e.preventDefault();
      toggle();
    });
    dom.on(document, "keydown", function (e) {
      if (e.key === "Escape" && openState) { close(); }
    });
  }

  EZ.notepad = { init: init, reset: reset };
})(window.EZ);

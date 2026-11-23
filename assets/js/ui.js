window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  document.addEventListener("error", function (e) {
    var img = e.target;
    if (!img || img.tagName !== "IMG" || !img.hasAttribute("data-img-fallback")) { return; }
    img.style.display = "none";
    var raw = img.parentNode && img.parentNode.nextElementSibling;
    if (raw) { raw.hidden = false; }
  }, true);

  var dom = EZ.dom, util = EZ.util;
  var root = document.documentElement;

  var NAVITEMS = [
    { id: "dashboard", label: "Dashboard", icon: '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>' },
    { id: "payloads", label: "Payloads", icon: '<path d="m8 7-5 5 5 5M16 7l5 5-5 5M14 4l-4 16" stroke-linecap="round" stroke-linejoin="round"/>' },
    { id: "reports", label: "Reports", icon: '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3" stroke-linecap="round"/>' },
    { id: "sessions", label: "Sessions", icon: '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M7 20h10M8 9l2.5 2L8 13" stroke-linecap="round" stroke-linejoin="round"/>' }
  ];

  var NAV_FOR_SCREEN = { dashboard: "dashboard", reports: "reports", report: "reports", payloads: "payloads", sessions: "sessions", "session-requests": "sessions" };

  var THEME_LABELS = { classic: "Classic", light: "Light", dark: "Carbon", ocean: "Ocean" };

  var THEMES = ["classic", "light", "dark", "ocean"];

  function sanitizeTheme(name) {
    var t = String(name == null ? "" : name).toLowerCase().replace(/[^a-z0-9]/g, "");
    if (t === "default") { t = "dark"; }
    return t === "" ? "classic" : t;
  }
  function themeLabel(name) {
    var t = sanitizeTheme(name);
    return THEME_LABELS[t] || (t.charAt(0).toUpperCase() + t.slice(1));
  }
  function currentTheme() {
    var t = "classic";
    try { t = localStorage.getItem(EZ.config.themeKey) || "classic"; } catch (e) {}
    return sanitizeTheme(t);
  }
  function storedTheme() {
    try {
      var v = localStorage.getItem(EZ.config.themeKey);
      return v === null ? null : sanitizeTheme(v);
    } catch (e) { return null; }
  }
  function applyTheme(name, persist) {
    var theme = sanitizeTheme(name);
    if (theme === "dark") { root.removeAttribute("data-theme"); }
    else { root.setAttribute("data-theme", theme); }
    if (persist !== false) {
      try { localStorage.setItem(EZ.config.themeKey, theme); } catch (e) {}
    }
    dom.qsa("[data-theme-btn]").forEach(function (b) {
      b.setAttribute("aria-pressed", String(b.getAttribute("data-theme-btn") === theme));
    });
    return theme;
  }

  var TOAST_ICONS = {
    success: '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6" stroke-linecap="round" stroke-linejoin="round"/>',
    error: '<circle cx="12" cy="12" r="9"/><path d="m9.2 9.2 5.6 5.6M14.8 9.2l-5.6 5.6" stroke-linecap="round"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11.5v4.5M12 8h.01" stroke-linecap="round"/>'
  };
  function toast(message, type) {
    var stack = document.getElementById("toast-stack");
    if (!stack) { return; }
    type = type || "info";
    var el = document.createElement("div");
    el.className = "toast toast--" + type;
    el.setAttribute("role", type === "error" ? "alert" : "status");
    el.innerHTML =
      '<span class="toast__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
        (TOAST_ICONS[type] || TOAST_ICONS.info) + "</svg></span>" +
      '<span class="toast__msg">' + util.escapeHtml(message) + "</span>";
    stack.appendChild(el);
    void el.offsetWidth;
    el.classList.add("is-on");
    setTimeout(function () {
      el.classList.remove("is-on");
      setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 260);
    }, type === "error" ? 5200 : 3200);
  }

  function loading(label, variant) {
    var cls = "ezload" + (variant ? " ezload--" + variant : "");
    return '<div class="' + cls + '" role="status" aria-label="' + util.escapeHtml(label || "Loading...") + '">' +
      '<svg class="ezload__logo" viewBox="0 0 44 44" aria-hidden="true" focusable="false"><use href="#ezlogo"/></svg>' +
      "</div>";
  }

  var saveTimer;
  function flashSaved() {
    var pill = document.getElementById("savetoast");
    if (!pill) { return; }
    pill.classList.add("is-on");
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () { pill.classList.remove("is-on"); }, 1600);
  }

  function showScreen(id) {
    dom.qsa(".screen").forEach(function (s) {
      s.classList.toggle("is-active", s.id === "screen-" + id);
    });
    var navKey = NAV_FOR_SCREEN[id] || null;
    dom.qsa(".topnav__link[data-screen]").forEach(function (l) {
      l.setAttribute("aria-current", l.getAttribute("data-screen") === navKey ? "page" : "false");
    });
    closeAccount();
    closeX();
    window.scrollTo({ top: 0, behavior: "auto" });
  }

  var account, accountToggle;
  function closeAccount() {
    if (account) { account.classList.remove("is-open"); }
    if (accountToggle) { accountToggle.setAttribute("aria-expanded", "false"); }
  }

  function initSearch() {
    var topsearch = document.getElementById("topsearch");
    var searchToggle = document.getElementById("searchtoggle");
    var searchInput = document.getElementById("topsearch-input");
    if (!topsearch || !searchToggle || !searchInput) { return; }
    function closeSearch() {
      topsearch.classList.remove("is-open");
      searchToggle.setAttribute("aria-expanded", "false");
    }
    function canUsers() {
      return !!(EZ.util && EZ.util.can && EZ.util.can("user.manage"));
    }
    function syncPlaceholder() {
      var where = canUsers() ? "reports, sessions and users" : "reports and sessions";
      searchInput.placeholder = "Search " + where + "...";
      searchInput.setAttribute("aria-label", "Search " + where);
    }
    function run() {
      var q = searchInput.value.trim();
      var path = EZ.config.managePrefix + "/search" + (q ? "?q=" + encodeURIComponent(q) : "");
      if (EZ.router && EZ.router.go) { EZ.router.go(path); }
      closeSearch();
    }
    dom.on(searchToggle, "click", function (e) {
      e.stopPropagation();
      var open = topsearch.classList.toggle("is-open");
      searchToggle.setAttribute("aria-expanded", String(open));
      if (open) {
        syncPlaceholder();
        searchInput.focus();
      } else if (searchInput.value.trim()) { run(); } else { searchInput.blur(); }
    });
    dom.on(document, "click", function (e) {
      if (!topsearch.contains(e.target) && !searchInput.value) { closeSearch(); }
    });
    dom.on(document, "keydown", function (e) { if (e.key === "Escape") { closeSearch(); } });
    dom.on(searchInput, "keydown", function (e) {
      if (e.key !== "Enter") { return; }
      e.preventDefault();
      run();
    });
  }

  function initTabs() {
    dom.qsa("[data-tabs]").forEach(function (group) {
      var btns = dom.qsa("[role=tab]", group);
      btns.forEach(function (btn) {
        dom.on(btn, "click", function () {
          var target = btn.getAttribute("data-tab");
          btns.forEach(function (b) { b.setAttribute("aria-selected", String(b === btn)); });
          dom.qsa("[data-tabpanel]", group.parentNode).forEach(function (p) {
            p.classList.toggle("is-active", p.getAttribute("data-tabpanel") === target);
          });
        });
      });
    });
  }


  var openX = null;

  function fireChange(el) {
    var ev;
    if (typeof Event === "function") { ev = new Event("change", { bubbles: true }); }
    else { ev = document.createEvent("HTMLEvents"); ev.initEvent("change", true, false); }
    el.dispatchEvent(ev);
  }
  function closeX() {
    if (!openX) { return; }
    openX.classList.remove("is-open");
    openX.__btn.setAttribute("aria-expanded", "false");
    openX = null;
  }
  function placeMenu(xs) {
    var menu = xs.__menu, r = xs.__btn.getBoundingClientRect();
    menu.style.position = "fixed";
    menu.style.minWidth = Math.round(r.width) + "px";
    var below = window.innerHeight - r.bottom, h = menu.offsetHeight;
    if (below < h + 12 && r.top > below) {
      menu.style.top = "auto"; menu.style.bottom = Math.round(window.innerHeight - r.top + 5) + "px";
    } else {
      menu.style.bottom = "auto"; menu.style.top = Math.round(r.bottom + 5) + "px";
    }
    var m = 8, vw = document.documentElement.clientWidth, w = menu.offsetWidth, left = r.left;
    if (left + w > vw - m) { left = r.right - w; }
    if (left < m) { left = m; }
    if (left + w > vw - m) { left = vw - m - w; }
    menu.style.left = Math.round(left) + "px";
  }
  function syncXLabel(xs) {
    var o = xs.__sel.options[xs.__sel.selectedIndex];
    xs.__label.textContent = o ? o.textContent : "";
  }
  function buildXOptions(xs) {
    var sel = xs.__sel, menu = xs.__menu, i, o, b;
    menu.innerHTML = "";
    for (i = 0; i < sel.options.length; i++) {
      o = sel.options[i];
      b = document.createElement("button");
      b.type = "button"; b.className = "xselect__opt";
      b.setAttribute("role", "option"); b.setAttribute("data-i", i);
      b.setAttribute("aria-selected", String(i === sel.selectedIndex));
      b.textContent = o.textContent;
      if (o.disabled) { b.disabled = true; }
      menu.appendChild(b);
    }
  }
  function pickX(xs, i) {
    var sel = xs.__sel;
    if (i < 0 || i >= sel.options.length || sel.options[i].disabled) { return; }
    if (i !== sel.selectedIndex) {
      sel.selectedIndex = i; syncXLabel(xs); fireChange(sel);
    }
  }
  function enhanceSelect(sel) {
    if (sel.__xenh) { return; }
    sel.__xenh = true;
    var xs = document.createElement("span");
    xs.className = "xselect" + (sel.classList.contains("miniselect") ? " xselect--mini" : "");
    xs.style.cssText = sel.style.cssText;
    sel.style.cssText = "";
    sel.setAttribute("tabindex", "-1"); sel.setAttribute("aria-hidden", "true");
    sel.parentNode.insertBefore(xs, sel);
    xs.appendChild(sel);

    var btn = document.createElement("button");
    btn.type = "button"; btn.className = "xselect__btn";
    btn.setAttribute("aria-haspopup", "listbox"); btn.setAttribute("aria-expanded", "false");
    if (sel.getAttribute("aria-label")) { btn.setAttribute("aria-label", sel.getAttribute("aria-label")); }
    if (sel.disabled) { btn.disabled = true; }
    var lbl = document.createElement("span"); lbl.className = "xselect__label";
    btn.appendChild(lbl);
    btn.insertAdjacentHTML("beforeend", '<svg class="xselect__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>');
    var menu = document.createElement("div");
    menu.className = "xselect__menu"; menu.setAttribute("role", "listbox");
    xs.appendChild(btn); xs.appendChild(menu);
    xs.__sel = sel; xs.__btn = btn; xs.__label = lbl; xs.__menu = menu;
    sel.__xs = xs;
    syncXLabel(xs);

    dom.on(sel, "change", function () { syncXLabel(xs); });

    dom.on(btn, "click", function (e) {
      e.stopPropagation();
      if (openX === xs) { closeX(); return; }
      closeX();
      buildXOptions(xs);
      xs.classList.add("is-open"); btn.setAttribute("aria-expanded", "true"); openX = xs;
      placeMenu(xs);
      var cur = menu.querySelector('[aria-selected="true"]');
      if (cur) { cur.scrollIntoView({ block: "nearest" }); }
    });
    dom.on(menu, "click", function (e) {
      var opt = e.target.closest ? e.target.closest(".xselect__opt") : null;
      if (!opt || opt.disabled) { return; }
      pickX(xs, +opt.getAttribute("data-i"));
      closeX(); btn.focus();
    });
    dom.on(btn, "keydown", function (e) {
      if (e.key === "ArrowDown" || e.key === "ArrowUp") {
        e.preventDefault();
        var d = e.key === "ArrowDown" ? 1 : -1, n = sel.selectedIndex;
        do { n += d; } while (n >= 0 && n < sel.options.length && sel.options[n].disabled);
        pickX(xs, n);
      } else if (e.key === "Enter" || e.key === " ") {
        if (openX !== xs) { e.preventDefault(); btn.click(); }
      } else if (e.key === "Escape") { closeX(); }
    });
  }
  function enhanceSelects(scope) {
    var sels = (scope || document).querySelectorAll("select.input, select.miniselect"), i, s;
    for (i = 0; i < sels.length; i++) {
      s = sels[i];
      if (s.__xenh) { if (s.__xs) { syncXLabel(s.__xs); } } else { enhanceSelect(s); }
    }
  }
  function initSelects() {
    enhanceSelects(document);
    if (window.MutationObserver) {
      var mo = new MutationObserver(util.debounce(function () { enhanceSelects(document); }, 40));
      mo.observe(document.body, { childList: true, subtree: true });
    }
    dom.on(document, "click", function (e) {
      if (openX && !openX.contains(e.target)) { closeX(); }
    });
    dom.on(window, "resize", function () { if (openX) { closeX(); } });
    dom.on(document, "scroll", function (e) {
      if (openX && !openX.contains(e.target)) { closeX(); }
    }, true);
  }

  var closeCallbacks = {};
  function openModal(id, onClose) {
    var m = document.getElementById(id);
    if (m) {
      m.classList.add("is-open");
      if (onClose) { closeCallbacks[id] = onClose; }
    }
  }
  function closeModal(m) {
    if (!m) { return; }
    m.classList.remove("is-open");
    if (closeCallbacks[m.id]) {
      var cb = closeCallbacks[m.id];
      delete closeCallbacks[m.id];
      cb();
    }
  }
  function initModals() {
    dom.qsa("[data-open-modal]").forEach(function (b) {
      dom.on(b, "click", function () { openModal(b.getAttribute("data-open-modal")); });
    });
    dom.qsa(".modal-scrim").forEach(function (scrim) {
      dom.on(scrim, "click", function (e) {
        if (e.target === scrim || e.target.closest("[data-close-modal]")) { closeModal(scrim); }
      });
    });
    dom.on(document, "keydown", function (e) {
      if (e.key === "Escape") { dom.qsa(".modal-scrim.is-open").forEach(closeModal); }
    });
  }

  var confirmResolver = null;
  function escHtml(s) { return util.escapeHtml(s); }
  function settle(v) {
    var m = document.getElementById("confirm-modal");
    if (m) { m.classList.remove("is-open"); }
    var r = confirmResolver;
    confirmResolver = null;
    if (r) { r(v); }
  }
  function openConfirm(opts, isAlert) {
    opts = opts || {};
    var m = document.getElementById("confirm-modal");
    if (!m) {
      return Promise.resolve(isAlert ? true : false);
    }
    var titleEl = document.getElementById("confirm-title");
    var msgEl = document.getElementById("confirm-message");
    var okBtn = document.getElementById("confirm-ok");
    var cancelBtn = document.getElementById("confirm-cancel");
    if (titleEl) { titleEl.innerHTML = escHtml(opts.title || (isAlert ? "Notice" : "Confirm")); }
    if (msgEl) { msgEl.innerHTML = escHtml(opts.message || ""); }
    if (okBtn) {
      okBtn.textContent = opts.confirmLabel || (isAlert ? "OK" : "Confirm");
      if (opts.danger) { okBtn.classList.add("btn--danger"); okBtn.classList.remove("btn--primary"); }
      else { okBtn.classList.add("btn--primary"); okBtn.classList.remove("btn--danger"); }
    }
    if (cancelBtn) { cancelBtn.style.display = isAlert ? "none" : ""; }
    m.classList.add("is-open");
    var focusEl = (opts.danger && !isAlert && cancelBtn) ? cancelBtn : okBtn;
    if (focusEl && focusEl.focus) { try { focusEl.focus(); } catch (e) {} }
    return new Promise(function (resolve) {
      confirmResolver = isAlert ? function () { resolve(true); } : resolve;
    });
  }
  function confirmDialog(opts) { return openConfirm(opts, false); }
  function initConfirm() {
    var m = document.getElementById("confirm-modal");
    if (!m) { return; }
    var okBtn = document.getElementById("confirm-ok");
    var cancelBtn = document.getElementById("confirm-cancel");
    if (okBtn) { dom.on(okBtn, "click", function () { settle(true); }); }
    if (cancelBtn) { dom.on(cancelBtn, "click", function () { settle(false); }); }
    dom.on(m, "click", function (e) {
      if (e.target === m || e.target.closest("[data-close-modal]")) { settle(false); }
    });
    dom.on(m, "keydown", function (e) {
      if (e.key === "Escape") { settle(false); }
      else if (e.key === "Enter") {
        var ok = document.getElementById("confirm-ok");
        if (ok && !ok.classList.contains("btn--danger")) { settle(true); }
      }
    });
  }

  function fieldMsg(box, text, isError) {
    if (!box) { return; }
    if (text) {
      box.hidden = false;
      box.textContent = text;
      box.classList.remove("msg--error", "msg--ok");
      box.classList.add(isError ? "msg--error" : "msg--ok");
    } else {
      box.hidden = true;
      box.textContent = "";
    }
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text).catch(function () {});
    }
    try {
      var ta = document.createElement("textarea");
      ta.value = text; ta.style.position = "fixed"; ta.style.opacity = "0";
      document.body.appendChild(ta); ta.select();
      document.execCommand("copy");
      document.body.removeChild(ta);
    } catch (e) {}
    return Promise.resolve();
  }
  var COPY_GLYPHS =
    '<svg class="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8" stroke-linecap="round"/></svg>' +
    '<svg class="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 13 4 4 10-10" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  function copyBtn(targetSel) {
    return '<button class="copyfield__btn" type="button" data-copy data-target="' + targetSel + '" aria-label="Copy">' + COPY_GLYPHS + "</button>";
  }
  function copyHead(label, targetSel, extra) {
    return '<div class="field--copy__head"><span class="field-label">' + label + "</span>" +
      '<span class="field--copy__actions">' + (extra || "") +
      '<button class="copyfield__btn" type="button" data-copy data-target="' + targetSel + '" aria-label="Copy ' + label + '">' + COPY_GLYPHS + "</button></span></div>";
  }

  function openDomViewer(html) {
    var scrim = document.createElement("div");
    scrim.className = "modal-scrim modal-scrim--top domview is-open";
    var frame = document.createElement("iframe");
    frame.className = "domview__frame";
    frame.setAttribute("sandbox", "");
    frame.setAttribute("title", "Rendered DOM");
    var modal = document.createElement("div");
    modal.className = "modal modal--wide domview__modal";
    modal.innerHTML =
      '<div class="domview__head">' +
      '<span class="field-label">Rendered DOM - sandboxed, scripts disabled</span>' +
      '<button class="btn btn--sm" type="button" data-close-modal>Close</button></div>';
    modal.appendChild(frame);
    scrim.appendChild(modal);

    function onKey(e) { if (e.key === "Escape") { close(); } }
    function close() {
      document.removeEventListener("keydown", onKey);
      if (scrim.parentNode) { scrim.parentNode.removeChild(scrim); }
    }
    dom.on(scrim, "click", function (e) {
      if (e.target === scrim || e.target.closest("[data-close-modal]")) { close(); }
    });
    document.addEventListener("keydown", onKey);

    document.body.appendChild(scrim);
    frame.srcdoc = String(html || "");
  }

  function initCopy() {
    dom.on(document, "click", function (e) {
      var b = e.target.closest("[data-copy]");
      if (!b) { return; }
      var target = b.getAttribute("data-target");
      if (target) {
        var src = document.querySelector(target);
        if (src) { copyText(src.value !== undefined ? src.value : src.textContent); }
      }
      b.classList.add("is-copied");
      var lbl = b.querySelector("[data-copy-label]");
      var prev = lbl ? lbl.textContent : null;
      if (lbl) { lbl.textContent = "Copied"; }
      if (b._copyTimer) { clearTimeout(b._copyTimer); }
      b._copyTimer = setTimeout(function () {
        b.classList.remove("is-copied");
        if (lbl && prev !== null) { lbl.textContent = prev; }
      }, 1200);
    });
  }

  function pageCells(page, pages) {
    var cells = [], i;
    if (pages <= 5) {
      for (i = 1; i <= pages; i++) { cells.push(i); }
      return cells;
    }
    var start = page - 1, end = page + 1;
    if (start < 2) { start = 2; end = start + 2; }
    if (end > pages - 1) { end = pages - 1; start = end - 2; }
    if (start < 2) { start = 2; }
    cells.push(1);
    if (start > 2) { cells.push("gap"); }
    for (i = start; i <= end; i++) { cells.push(i); }
    if (end < pages - 1) { cells.push("gap"); }
    cells.push(pages);
    return cells;
  }

  function renderPager(box, meta, onPage) {
    if (!box) { return; }
    meta = meta || {};
    var total = Number(meta.total || 0);
    var page = Number(meta.page || 1);
    var pages = Number(meta.pages || 0);
    var perPage = Number(meta.per_page || 0);

    if (!total) { box.innerHTML = ""; return; }

    var from = perPage ? (page - 1) * perPage + 1 : 1;
    var to = perPage ? Math.min(total, page * perPage) : total;

    var btns = "";
    if (pages > 1) {
      var cells = "";
      pageCells(page, pages).forEach(function (c) {
        if (c === "gap") {
          cells += '<span class="pagination__gap" aria-hidden="true">...</span>';
        } else {
          cells += '<button type="button" data-page="' + c + '" aria-current="' + (c === page ? "true" : "false") + '">' + c + "</button>";
        }
      });
      btns += '<button type="button" class="pagination__step" data-page="' + (page - 1) + '"' + (page <= 1 ? " disabled" : "") + ">Prev</button>" +
        '<div class="pagination__scroll">' + cells + "</div>" +
        '<button type="button" class="pagination__step" data-page="' + (page + 1) + '"' + (page >= pages ? " disabled" : "") + ">Next</button>";
    }

    box.innerHTML =
      '<span class="faint" style="font-size:var(--text-sm)">Showing ' + from + "-" + to +
        " of " + total.toLocaleString() + (meta.capped ? "+" : "") + "</span>" +
      '<div class="pagination__pages">' + btns + "</div>";

    dom.qsa("button[data-page]", box).forEach(function (b) {
      if (b.disabled) { return; }
      dom.on(b, "click", function () {
        var p = Number(b.getAttribute("data-page"));
        if (p >= 1 && p <= pages && p !== page) { onPage(p); }
      });
    });
  }

  function initCodeInputs() {
    var inputs = dom.qsa(".code-input input");
    inputs.forEach(function (inp, i) {
      dom.on(inp, "input", function () {
        inp.value = inp.value.replace(/[^0-9]/g, "").slice(0, 1);
        if (inp.value && inputs[i + 1]) { inputs[i + 1].focus(); }
      });
      dom.on(inp, "keydown", function (e) {
        if (e.key === "Backspace" && !inp.value && inputs[i - 1]) { inputs[i - 1].focus(); }
      });
      dom.on(inp, "paste", function (e) {
        var cd = e.clipboardData || window.clipboardData;
        if (!cd) { return; }
        var digits = String(cd.getData("text") || "").replace(/[^0-9]/g, "");
        if (!digits) { return; }
        e.preventDefault();
        for (var j = 0; j < inputs.length; j++) {
          inputs[j].value = j < digits.length ? digits.charAt(j) : "";
        }
        var last = Math.min(digits.length, inputs.length) - 1;
        if (last >= 0 && inputs[last]) { inputs[last].focus(); }
      });
    });
  }

  function readCode() {
    return dom.qsa(".code-input input").map(function (i) { return i.value; }).join("");
  }
  function clearCode() {
    dom.qsa(".code-input input").forEach(function (i) { i.value = ""; });
  }

  function init() {
    var stored = storedTheme();
    if (stored !== null) { applyTheme(stored, false); }

    var navHTML = NAVITEMS.map(function (n) {
      return '<a class="topnav__link" href="#" data-screen="' + n.id + '" aria-label="' + n.label + '" title="' + n.label + '">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' + n.icon + "</svg>" +
        '<span class="topnav__label">' + n.label + "</span></a>";
    }).join("");
    dom.qsa("[data-topnav]").forEach(function (slot) { slot.innerHTML = navHTML; });

    account = document.getElementById("account");
    accountToggle = document.getElementById("accounttoggle");
    if (account && accountToggle) {
      dom.on(accountToggle, "click", function (e) {
        e.stopPropagation();
        var open = account.classList.toggle("is-open");
        accountToggle.setAttribute("aria-expanded", String(open));
      });
      dom.on(document, "click", function (e) { if (!account.contains(e.target)) { closeAccount(); } });
      dom.on(document, "keydown", function (e) { if (e.key === "Escape") { closeAccount(); } });
    }

    initSearch();
    initTabs();
    initModals();
    initConfirm();
    initCopy();
    initCodeInputs();
    initSelects();
  }

  function avatarInitials(name) {
    name = (name || "").trim();
    if (!name) { return "?"; }
    var parts = name.split(/[\s._-]+/).filter(Boolean);
    var str = parts.length >= 2 ? (parts[0][0] + parts[1][0]) : name.slice(0, 2);
    return str.toUpperCase();
  }
  function githubAvatarUrl(github) {
    github = (github || "").trim();
    return github ? "https://avatars.githubusercontent.com/" + encodeURIComponent(github) + "?size=80" : "";
  }
  function paintAvatar(a, github, ini) {
    a.textContent = ini;
    var url = githubAvatarUrl(github);
    if (!url) { return; }
    var img = document.createElement("img");
    img.className = "avatar__img"; img.alt = ""; img.src = url;
    img.onerror = function () {
      if (img.parentNode) { img.parentNode.removeChild(img); }
    };
    a.appendChild(img);
  }
  function applyAvatar(github) {
    var u = EZ.state.user || {};
    var ini = avatarInitials(u.username);
    dom.qsa(".avatar").forEach(function (a) { paintAvatar(a, github, ini); });
  }

  function extPickRow(x, checked, builder, disabled, tipText) {
    var ver = x.version ? '<span class="extpick__ver">' + util.escapeHtml(x.version) + "</span>" : "";
    var desc = x.description ? '<div class="extpick__desc">' + util.escapeHtml(x.description) + "</div>" : "";
    var sw = '<span class="switch"><input type="checkbox" data-field="extensions" data-autosave-builder="' + builder + '" data-ext-id="' + x.id + '"' +
      (checked ? " checked" : "") + (disabled ? " disabled" : "") + '><span class="switch__track"></span></span>';
    if (disabled && tipText) {
      sw = '<span class="tip">' + sw + '<span class="tip__bubble" role="tooltip">' + util.escapeHtml(tipText) + "</span></span>";
    }
    return '<label class="extpick' + (disabled ? " is-disabled" : "") + '">' +
      '<div class="extpick__main"><div class="extpick__name">' + util.escapeHtml(x.name || "Untitled") + ver + "</div>" + desc + "</div>" +
      sw +
      "</label>";
  }

  EZ.ui = {
    init: init,
    toast: toast,
    loading: loading,
    extPickRow: extPickRow,
    flashSaved: flashSaved,
    showScreen: showScreen,
    closeAccount: closeAccount,
    applyTheme: applyTheme,
    currentTheme: currentTheme,
    storedTheme: storedTheme,
    themeLabel: themeLabel,
    themes: THEMES,
    openModal: openModal,
    closeModalById: function (id) { var m = document.getElementById(id); if (m) { closeModal(m); } },
    confirm: confirmDialog,
    renderPager: renderPager,
    fieldMsg: fieldMsg,
    copyBtn: copyBtn,
    copyHead: copyHead,
    openDomViewer: openDomViewer,
    readCode: readCode,
    clearCode: clearCode,
    copyText: copyText,
    applyAvatar: applyAvatar,
    lockMsg: function () {
      return '<div class="empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><p>You don’t have access to this.</p></div>';
    }
  };
})(window.EZ);

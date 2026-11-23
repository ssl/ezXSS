(function () {
  "use strict";

  var DASH = "-";
  var MID = "·";

  var COPY_GLYPHS =
    '<svg class="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8" stroke-linecap="round"/></svg>' +
    '<svg class="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 13 4 4 10-10" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function slugFromPath() {
    var m = /\/s\/([0-9a-z]{25})(?:\.json)?\/?$/.exec(location.pathname || "");
    return m ? m[1] : "";
  }

  function fmtDateTime(unix) {
    if (unix == null || unix === "") { return DASH; }
    var d = new Date(Number(unix) * 1000);
    if (isNaN(d.getTime())) { return DASH; }
    return d.toLocaleString([], {
      year: "numeric", month: "short", day: "numeric",
      hour: "2-digit", minute: "2-digit", second: "2-digit"
    });
  }

  function fmtAgo(unix) {
    if (unix == null || unix === "") { return DASH; }
    var secs = Math.floor(Date.now() / 1000) - Number(unix);
    if (!isFinite(secs)) { return DASH; }
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

  function uaVer(ua, re) { var m = ua.match(re); return m ? " " + m[1] : ""; }
  function parseBrowser(ua) {
    ua = String(ua || "");
    if (!ua) { return DASH; }
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
    return parts.length ? parts.join(" " + MID + " ") : "Unknown";
  }

  function stringifyStore(v) {
    if (v == null || v === "") { return ""; }
    if (typeof v === "object") { try { return JSON.stringify(v, null, 2); } catch (e) { return ""; } }
    return String(v);
  }

  var copySeq = 0;
  function cid() { return "sv-f" + (++copySeq); }

  function copyBtn(targetId) {
    return '<button class="copyfield__btn" type="button" data-copy data-target="#' + targetId + '" aria-label="Copy">' + COPY_GLYPHS + "</button>";
  }
  function copyHead(label, targetId, extra) {
    return '<div class="field--copy__head"><span class="field-label">' + esc(label) + "</span>" +
      '<span class="field--copy__actions">' + (extra || "") +
      '<button class="copyfield__btn" type="button" data-copy data-target="#' + targetId + '" aria-label="Copy ' + esc(label) + '">' + COPY_GLYPHS + "</button></span></div>";
  }

  function infoRow(label, valueHtml, opts) {
    opts = opts || {};
    var id = cid();
    var valCls = "frow__val" + (opts.mono ? " mono" : "");
    var inner = '<span class="frow__valwrap"><span class="' + valCls + '" id="' + id + '">' + valueHtml + "</span>" +
      (opts.suffix ? ' <span class="frow__sub">' + opts.suffix + "</span>" : "") + "</span>" +
      copyBtn(id);
    return '<div class="frow"><div class="frow__k">' + esc(label) + "</div>" +
      '<div class="frow__v frow__v--inline">' + inner + "</div></div>";
  }

  function dataBlock(label, text) {
    var id = cid();
    var has = text !== null && text !== undefined && String(text) !== "";
    var body = has
      ? '<div class="datablk__body mono" id="' + id + '" tabindex="0">' + esc(String(text)) + "</div>"
      : '<div class="datablk__body datablk__body--empty" id="' + id + '">Nothing captured.</div>';
    return '<div class="frow frow--block"><div class="field--copy">' +
      copyHead(label, id, "") +
      '<div class="datablk">' + body + "</div></div></div>";
  }

  function dataRow(label, valueHtml, extraClass) {
    return '<div class="frow' + (extraClass ? " " + extraClass : "") + '">' +
      '<div class="frow__k">' + esc(label) + "</div>" +
      '<div class="frow__v">' + valueHtml + "</div></div>";
  }

  function isDataImage(v) {
    return typeof v === "string" &&
      /^data:image\/(png|jpe?g|gif|webp|bmp|svg\+xml);base64,[a-z0-9+/=\s]+$/i.test(v) &&
      v.length <= 5 * 1024 * 1024;
  }

  function imageHtml(v) {
    var id = cid();
    return '<div class="data-image-wrap">' +
        '<figure class="data-image">' +
          '<img src="' + esc(v) + '" alt="Collected image" loading="lazy" data-img-fallback>' +
        "</figure>" +
        '<code class="mono data-image__raw" id="' + id + '" hidden>' + esc(v) + "</code>" +
        copyBtn(id) +
      "</div>";
  }

  function kvCell(k, valueHtml) {
    return '<div class="kv"><div class="kv__k">' + esc(k) + "</div>" +
      '<div class="kv__v">' + valueHtml + "</div></div>";
  }

  function kvCells(obj, prefix, depth) {
    var out = [];
    Object.keys(obj).forEach(function (k) {
      var key = prefix ? prefix + "." + k : k;
      var v = obj[k];
      if (isDataImage(v)) {
        out.push(kvCell(key, imageHtml(v)));
      } else if (v !== null && typeof v === "object") {
        if (depth >= 3) {
          out.push(kvCell(key, '<span class="mono">' + esc(JSON.stringify(v)) + "</span>"));
        } else {
          out = out.concat(kvCells(v, key, depth + 1));
        }
      } else {
        out.push(kvCell(key, '<span class="mono">' + esc(String(v)) + "</span>"));
      }
    });
    return out;
  }

  function toObj(extra) {
    if (extra !== null && typeof extra === "object") { return extra; }
    try { return JSON.parse(String(extra)); } catch (e) { return null; }
  }

  function renderData(extra) {
    var obj = toObj(extra);
    if (!obj || typeof obj !== "object" || Array.isArray(obj)) {
      if (extra == null || extra === "") { return ""; }
      var raw = (typeof extra === "object") ? JSON.stringify(extra) : String(extra);
      return dataBlock("Extra", raw);
    }
    var keys = Object.keys(obj);
    if (!keys.length) { return ""; }
    return keys.map(function (k) {
      var v = obj[k];
      if (isDataImage(v)) { return dataRow(k, imageHtml(v), "frow--custom"); }
      if (v !== null && typeof v === "object") {
        var cells = kvCells(v, "", 1).join("");
        if (!cells) { return ""; }
        var id = cid();
        return '<div class="frow frow--block">' +
          copyHead(k, id) +
          '<textarea id="' + id + '" readonly hidden>' + esc(JSON.stringify(v)) + "</textarea>" +
          '<div class="kvgrid">' + cells + "</div>" +
          "</div>";
      }
      return '<div class="frow frow--custom"><div class="frow__k">' + esc(k) + "</div>" +
        '<div class="frow__v"><span class="mono">' + esc(String(v)) + "</span></div></div>";
    }).join("");
  }

  function render(data) {
    var report = (data && data.report) || {};
    var present = {};
    ((data && data.columns) || []).forEach(function (c) { present[c] = true; });

    var rows = "";
    if (present.uri) { rows += infoRow("URL", esc(report.uri || DASH), { mono: true }); }
    if (present.origin) { rows += infoRow("Origin", esc(report.origin || DASH), { mono: true }); }
    if (present.ip) { rows += infoRow("IP", esc(report.ip || DASH), { mono: true }); }
    if (present.referer) { rows += infoRow("Referer", esc(report.referer || DASH), { mono: true }); }
    if (present.useragent) {
      var bparts = parseBrowser(report.useragent).split(" " + MID + " ");
      rows += infoRow("Browser", esc(bparts[0] || DASH), { suffix: bparts[1] ? esc(bparts[1]) : "" });
      rows += infoRow("User Agent", esc(report.useragent || DASH), { mono: true });
    }
    if (present.cookies) { rows += dataBlock("Cookies", report.cookies || ""); }
    if (present.localstorage) { rows += dataBlock("Local Storage", stringifyStore(report.localstorage)); }
    if (present.sessionstorage) { rows += dataBlock("Session Storage", stringifyStore(report.sessionstorage)); }
    if (present.dom) { rows += dataBlock("DOM", report.dom || ""); }
    if (present.extra) { rows += renderData(report.extra); }
    if (present.csp) { rows += dataBlock("Content-Security-Policy", report.csp || ""); }
    if (present.created_at) {
      rows += infoRow("Captured", esc(fmtDateTime(report.created_at)), { suffix: esc(fmtAgo(report.created_at)) });
    }

    if (rows === "") {
      rows = '<p class="field-hint">This share exposes no fields.</p>';
    }

    var card =
      '<div class="card">' +
        '<div class="card__head freport__head">' +
          '<h2 class="card__title">Shared report</h2>' +
          (present.payload
            ? '<span class="chip-id" title="' + esc(report.payload || "") + '">' + esc(report.payload || DASH) + "</span>"
            : "") +
        "</div>" +
        '<p class="field-hint" style="margin-bottom:var(--space-md)">A read-only snapshot shared from ezXSS. Only the fields below were included.</p>' +
        '<div class="freport">' + rows + "</div>" +
      "</div>";

    var shot = report.screenshot;
    var shotCard = "";
    if (present.screenshot && shot && (shot.url || shot.value)) {
      var src = shot.type === "file" ? shot.url : shot.value;
      shotCard =
        '<div class="card" style="margin-top:var(--space-lg)">' +
          '<div class="card__head"><h2 class="card__title">Screenshot</h2></div>' +
          '<div class="screenshot-frame"><img src="' + esc(src) + '" alt="Captured screenshot" style="display:block;width:100%;height:auto"></div>' +
        "</div>";
    }

    var root = document.getElementById("share-root");
    root.className = "shareview__report";
    root.innerHTML = card + shotCard;

    wireCopy(root);
  }

  function wireCopy(root) {
    root.addEventListener("click", function (e) {
      var b = e.target.closest("[data-copy]");
      if (!b) { return; }
      var sel = b.getAttribute("data-target");
      var src = sel ? document.querySelector(sel) : null;
      if (src) {
        var text = src.value !== undefined ? src.value : src.textContent;
        if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text)["catch"](function () {}); }
      }
      b.classList.add("is-copied");
      if (b._t) { clearTimeout(b._t); }
      b._t = setTimeout(function () { b.classList.remove("is-copied"); }, 1200);
    });
  }

  function fail(message) {
    var root = document.getElementById("share-root");
    if (!root) { return; }
    root.className = "shareview__card card";
    root.innerHTML =
      '<div class="card__head"><h1 class="card__title">Share unavailable</h1></div>' +
      '<p class="field-hint">' + esc(message) + "</p>";
  }

  function start() {
    var slug = slugFromPath();
    if (!slug) { return fail("This share link is not valid."); }

    fetch("/s/" + slug + ".json", { headers: { "Accept": "application/json" } })
      .then(function (res) {
        return res.text().then(function (text) {
          var json = null;
          if (text) { try { json = JSON.parse(text); } catch (e) { json = null; } }
          if (res.ok && json && json.success) { render(json.data || {}); return; }
          if (res.status === 410) { return fail("This share has expired."); }
          fail((json && json.error) ? json.error : "This share could not be found.");
        });
      })
      .catch(function () { fail("Could not load this share. Please try again later."); });
  }

  document.addEventListener("error", function (e) {
    var img = e.target;
    if (!img || img.tagName !== "IMG" || !img.hasAttribute("data-img-fallback")) { return; }
    img.style.display = "none";
    var raw = img.parentNode && img.parentNode.nextElementSibling;
    if (raw) { raw.hidden = false; }
  }, true);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();

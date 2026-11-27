window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  EZ.extend = EZ.extend || {};

  function onReady(fn) {
    if (document.readyState !== "loading") { fn(); }
    else { document.addEventListener("DOMContentLoaded", fn); }
  }

  EZ.extend.page = function (opts) {
    if (!opts || !opts.name || !EZ.router) { return; }
    var name = opts.name;
    var segs = opts.segs || [name];

    EZ.router.addRoute(segs, name);
    EZ.router.register(name, {
      mount: function (ctx) {
        if (opts.mount) {
          opts.mount({ el: document.getElementById("screen-" + name), params: (ctx && ctx.params) || {}, screen: name });
        }
      },
      unmount: opts.unmount || null,
      resetState: opts.resetState || null
    });

    onReady(function () {
      if (!document.getElementById("screen-" + name)) {
        var anchor = document.querySelector(".screen");
        if (anchor && anchor.parentNode) {
          var sec = document.createElement("section");
          sec.className = "screen";
          sec.id = "screen-" + name;
          anchor.parentNode.appendChild(sec);
        }
      }

      if (opts.label) {
        var menu = document.querySelector("#account .menu") || document.querySelector(".menu");
        if (menu && !menu.querySelector('[data-screen="' + name + '"]')) {
          var link = document.createElement("a");
          link.className = "menu__link";
          link.setAttribute("role", "menuitem");
          link.setAttribute("href", "#");
          link.setAttribute("data-screen", name);
          link.innerHTML = (opts.icon || "") + "<span>" + EZ.util.escapeHtml(opts.label) + "</span>";
          var personal = menu.querySelector("[data-personal-label]");
          var danger = menu.querySelector(".menu__link--danger");
          if (personal && personal.nextSibling) { menu.insertBefore(link, personal.nextSibling); }
          else if (danger && danger.parentNode === menu) { menu.insertBefore(link, danger); }
          else { menu.appendChild(link); }
        }
      }
    });
  };
})(window.EZ);

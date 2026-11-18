window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var HOSTABLE = [
    "*.amazonaws.com", "*.cloudfront.net", "*.github.io", "*.githubusercontent.com",
    "*.herokuapp.com", "*.azurewebsites.net", "*.blob.core.windows.net", "*.firebaseapp.com",
    "*.web.app", "*.appspot.com", "storage.googleapis.com", "*.netlify.app", "*.vercel.app",
    "*.pages.dev", "*.workers.dev", "*.glitch.me", "*.ngrok.io", "*.ngrok-free.app",
    "*.googletagmanager.com"
  ];
  var GADGET = [
    "cdnjs.cloudflare.com", "cdn.jsdelivr.net", "unpkg.com", "ajax.googleapis.com",
    "code.angularjs.org", "ajax.aspnetcdn.com"
  ];
  var JSONP = [
    "*.google.com", "*.youtube.com", "*.yandex.ru", "*.facebook.com", "*.twitter.com",
    "translate.googleapis.com", "maps.googleapis.com"
  ];

  var NOJS_ORDER = ["img-src", "style-src", "media-src", "frame-src", "object-src", "font-src", "manifest-src", "prefetch", "connect-src"];

  function splitPolicies(text) {
    var out = [];
    String(text || "").split(/\r?\n/).forEach(function (line) {
      line = line.trim();
      if (!line) { return; }
      var reportOnly = false;
      var m = line.match(/^content-security-policy(-report-only)?\s*:\s*/i);
      if (m) {
        reportOnly = !!m[1];
        line = line.slice(m[0].length);
      } else if (/http-equiv/i.test(line) && /content-security-policy/i.test(line)) {
        var content = line.match(/\scontent\s*=\s*(["'])([\s\S]*?)\1/i);
        reportOnly = /content-security-policy-report-only/i.test(line);
        line = content ? content[2] : "";
      }
      line.split(",").forEach(function (chunk) {
        if (chunk.trim()) { out.push({ text: chunk, reportOnly: reportOnly }); }
      });
    });
    return out;
  }

  function parseOne(text) {
    var dirs = {};
    String(text || "").split(";").forEach(function (part) {
      var toks = part.replace(/\s+/g, " ").trim().split(" ");
      var name = (toks.shift() || "").toLowerCase();
      if (name && !Object.prototype.hasOwnProperty.call(dirs, name)) {
        dirs[name] = toks.filter(function (t) { return t !== ""; });
      }
    });
    return dirs;
  }

  function parse(text) {
    return splitPolicies(text).map(function (p) {
      return { dirs: parseOne(p.text), reportOnly: p.reportOnly };
    }).filter(function (p) { return Object.keys(p.dirs).length > 0; });
  }

  function chainFor(dir) {
    if (dir === "script-elem") { return ["script-src-elem", "script-src", "default-src"]; }
    if (dir === "script-attr") { return ["script-src-attr", "script-src", "default-src"]; }
    if (dir === "frame-src") { return ["frame-src", "child-src", "default-src"]; }
    if (dir === "prefetch") { return ["prefetch-src", "default-src"]; }
    return [dir, "default-src"];
  }

  function sources(dirs, dir) {
    var chain = chainFor(dir);
    for (var i = 0; i < chain.length; i++) {
      if (dirs[chain[i]]) { return dirs[chain[i]]; }
    }
    return null;
  }

  function lower(list) {
    return (list || []).map(function (t) { return t.toLowerCase(); });
  }

  function has(list, tok) {
    return lower(list).indexOf(tok) !== -1;
  }

  function hasNonceOrHash(list) {
    return lower(list).some(function (t) { return /^'(nonce|sha256|sha384|sha512)-/.test(t); });
  }

  function srcHost(tok) {
    return tok.replace(/^[a-z][a-z0-9+.-]*:\/\//, "").split("/")[0].split(":")[0];
  }

  function hostMatches(pattern, host) {
    if (pattern === host) { return true; }
    return pattern.indexOf("*.") === 0 && host.length > pattern.length - 1 &&
      host.slice(-(pattern.length - 1)) === pattern.slice(1);
  }

  function allowsHost(list, host) {
    if (list === null) { return true; }
    var toks = lower(list);
    for (var i = 0; i < toks.length; i++) {
      var tok = toks[i];
      if (tok === "'none'") { return false; }
      if (tok === "*" || tok === "https:" || tok === "http:") { return true; }
      if (tok.charAt(0) === "'" || tok.charAt(tok.length - 1) === ":") { continue; }
      if (hostMatches(srcHost(tok), host)) { return true; }
    }
    return false;
  }

  function scriptVerdict(dirs, host) {
    if (dirs.sandbox && !has(dirs.sandbox, "allow-scripts")) {
      return { external: false, inline: false, why: "sandbox without allow-scripts blocks every script" };
    }
    var elem = sources(dirs, "script-elem");
    var attr = sources(dirs, "script-attr");
    var strict = elem !== null && has(elem, "'strict-dynamic'");
    var external, why;
    if (elem === null) {
      external = true; why = "no script-src or default-src, so a script loads freely";
    } else if (strict) {
      external = false; why = "strict-dynamic ignores host allow-lists; an external script needs a valid nonce";
    } else if (allowsHost(elem, host)) {
      external = true; why = "script-src allows loading from " + host;
    } else {
      external = false; why = "script-src does not allow loading from " + host;
    }
    var inline = attr === null || (has(attr, "'unsafe-inline'") && !hasNonceOrHash(attr) && !has(attr, "'strict-dynamic'"));
    var inlineElem = elem === null || (has(elem, "'unsafe-inline'") && !hasNonceOrHash(elem) && !strict);
    return { external: external, inline: inline && inlineElem, why: why };
  }

  function covers(src, entry) {
    return src === entry || hostMatches(src, entry.replace(/^\*\./, "x.")) || hostMatches(entry, src.replace(/^\*\./, "x."));
  }

  function listHits(list, set) {
    var hits = [];
    lower(list).forEach(function (tok) {
      if (tok.charAt(0) === "'" || tok.charAt(tok.length - 1) === ":" || tok === "*") { return; }
      var h = srcHost(tok);
      for (var i = 0; i < set.length; i++) {
        if (covers(h, set[i])) { if (hits.indexOf(tok) === -1) { hits.push(tok); } return; }
      }
    });
    return hits;
  }

  function findings(dirs) {
    var out = [];
    var elem = sources(dirs, "script-elem");
    var strict = elem !== null && has(elem, "'strict-dynamic'");
    var nonced = elem !== null && hasNonceOrHash(elem);
    if (elem !== null && !strict) {
      var hostable = listHits(elem, HOSTABLE);
      if (hostable.length) {
        out.push({ level: "high", dir: "script-src", text: "Allows user-hostable " + hostable.join(", ") + ". Host the payload there and load it as a script." });
      }
      var gadget = listHits(elem, GADGET);
      if (gadget.length) {
        out.push({ level: "high", dir: "script-src", text: "Allows " + gadget.join(", ") + ", which serves libraries with script gadgets (AngularJS and similar). Load one and run the payload through a template." });
      }
      var jsonp = listHits(elem, JSONP);
      if (jsonp.length) {
        out.push({ level: "high", dir: "script-src", text: "Allows " + jsonp.join(", ") + ", which exposes JSONP endpoints. A callback parameter can run the payload." });
      }
      if (has(elem, "data:")) {
        out.push({ level: "high", dir: "script-src", text: "Allows data: URLs. <script src=data:,...> runs inline code." });
      }
    }
    if (elem !== null && has(elem, "'unsafe-inline'") && !nonced && !strict) {
      out.push({ level: "med", dir: "script-src", text: "'unsafe-inline' without a nonce or hash: inline handlers and script blocks run." });
    }
    if (elem !== null && has(elem, "'unsafe-eval'")) {
      out.push({ level: "med", dir: "script-src", text: "'unsafe-eval' is allowed: eval and new Function work." });
    }
    if ((nonced || strict) && !dirs["base-uri"]) {
      out.push({ level: "med", dir: "base-uri", text: "Not set. An injected <base href> redirects relative script URLs, including nonce'd ones." });
    }
    if (!dirs["object-src"] && !dirs["default-src"]) {
      out.push({ level: "info", dir: "object-src", text: "Not restricted. <object> and <embed> load from any host." });
    }
    if (!dirs["form-action"]) {
      out.push({ level: "info", dir: "form-action", text: "Not set (no default-src fallback). A form can post to any host." });
    }
    if (dirs["require-trusted-types-for"]) {
      var tt = dirs["trusted-types"] || null;
      var names = tt ? lower(tt).filter(function (t) { return t.charAt(0) !== "'" && t !== "*"; }) : [];
      var ttText = "Enforced. script.src strings, eval, encoded vectors and the Inline loader throw; Image, SVG and details (import()) still load.";
      if (tt && has(tt, "'none'")) {
        ttText += " trusted-types 'none' blocks every policy, so the Trusted Types vector fails too.";
      } else if (names.length && names.indexOf("ez") === -1 && !has(tt, "*")) {
        ttText += " Allowed policy names: " + names.join(", ") + ". Rename the Trusted Types vector's policy to one of them.";
      } else {
        ttText += " The Trusted Types vector can create its policy.";
      }
      out.push({ level: "info", dir: "trusted-types", text: ttText });
    }
    var reports = (dirs["report-uri"] || []).concat(dirs["report-to"] || []);
    if (reports.length) {
      out.push({ level: "info", dir: "reporting", text: "Violations are reported to " + reports.join(", ") + ". Blocked attempts are visible to the site owner." });
    }
    return out;
  }

  function analyze(text, host) {
    host = String(host || "").toLowerCase();
    var policies = parse(text);
    var enforced = policies.filter(function (p) { return !p.reportOnly; });
    var res = {
      policies: policies.length, enforced: enforced.length, reportOnly: policies.length > 0 && enforced.length === 0,
      script: "external", scriptWhy: "", open: [], best: "", connectOk: true, imgOk: true, findings: []
    };
    if (!policies.length) { return res; }
    if (!enforced.length) {
      res.scriptWhy = "only a report-only policy is present, so nothing is enforced";
      res.open = NOJS_ORDER.concat(["navigation"]);
      res.best = res.open[0];
      res.findings.push({ level: "info", dir: "report-only", text: "Report-only: violations are logged, nothing is blocked." });
      return res;
    }
    var external = true, inline = true, okWhy = "", blockWhy = "";
    enforced.forEach(function (p) {
      var v = scriptVerdict(p.dirs, host);
      if (v.external && !okWhy) { okWhy = v.why; }
      if (!v.external && !blockWhy) { blockWhy = v.why; }
      external = external && v.external;
      inline = inline && v.inline;
    });
    var why = external ? okWhy : blockWhy;
    res.connectOk = enforced.every(function (p) { return allowsHost(sources(p.dirs, "connect-src"), host); });
    res.imgOk = enforced.every(function (p) { return allowsHost(sources(p.dirs, "img-src"), host); });
    res.trustedTypes = enforced.some(function (p) { return !!p.dirs["require-trusted-types-for"]; });
    res.script = external ? "external" : (inline ? "inline" : "blocked");
    res.scriptWhy = why;
    NOJS_ORDER.forEach(function (dir) {
      if (enforced.every(function (p) { return allowsHost(sources(p.dirs, dir), host); })) { res.open.push(dir); }
    });
    res.open.push("navigation");
    res.best = res.open[0];
    var seen = {};
    enforced.forEach(function (p) {
      findings(p.dirs).forEach(function (f) {
        var key = f.level + f.text;
        if (!seen[key]) { seen[key] = true; res.findings.push(f); }
      });
    });
    if (policies.length > enforced.length) {
      res.findings.push({ level: "info", dir: "report-only", text: "A report-only policy is also present. It only logs." });
    }
    var rank = { high: 0, med: 1, info: 2 };
    res.findings.sort(function (a, b) { return rank[a.level] - rank[b.level]; });
    return res;
  }

  function findingsHtml(list) {
    if (!list || !list.length) { return ""; }
    var esc = EZ.util.escapeHtml;
    var label = { high: "Bypass", med: "Weak", info: "Note" };
    return '<ul class="cspfind">' + list.map(function (f) {
      return '<li class="cspfind__i cspfind__i--' + esc(f.level) + '"><span class="cspfind__l">' + esc(label[f.level] || f.level) + "</span>" +
        '<span class="cspfind__t"><span class="mono">' + esc(f.dir) + "</span> " + esc(f.text) + "</span></li>";
    }).join("") + "</ul>";
  }

  function verdictHtml(a, host) {
    var esc = EZ.util.escapeHtml;
    if (a.script === "external") {
      return '<div class="cspres__v cspres__v--ok">Script runs</div>' +
        '<p class="cspres__d">' + esc(a.scriptWhy) + ".</p>";
    }
    if (a.script === "inline") {
      return '<div class="cspres__v cspres__v--ok">Inline only</div>' +
        '<p class="cspres__d">' + esc(a.scriptWhy) + ", but 'unsafe-inline' lets an inline handler run" +
        (a.trustedTypes ? ". Trusted Types block the Inline loader, so the payload has to be pasted inline in full."
          : a.connectOk ? ". The Inline loader fetches the payload and runs it inline."
          : ". connect-src blocks " + esc(host) + ", so the payload has to be pasted inline in full.") + "</p>";
    }
    return '<div class="cspres__v cspres__v--bad">Script blocked</div>' +
      '<p class="cspres__d">' + esc(a.scriptWhy) + ".</p>";
  }

  EZ.csp = { analyze: analyze, findingsHtml: findingsHtml, verdictHtml: verdictHtml };
})(window.EZ);

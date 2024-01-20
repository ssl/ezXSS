<p align="center">
  <img src="https://i.imgur.com/oPtfbDG.png"><br>
  ezXSS is an easy way for penetration testers and bug 
  bounty hunters to test (blind) Cross Site Scripting.<br><br>
  <img src="https://img.shields.io/github/release/ssl/ezXSS?style=flat">
  <img src="https://img.shields.io/github/issues/ssl/ezXSS?style=flat">
  <img src="https://img.shields.io/github/forks/ssl/ezXSS?style=flat">
  <img src="https://img.shields.io/github/stars/ssl/ezXSS?style=flat">
  <img src="https://img.shields.io/github/license/ssl/ezXSS?style=flat">
</p>

### Description
ezXSS is a tool that is designed to help find and exploit cross-site scripting (XSS) vulnerabilities. One of the key features of ezXSS is its ability to identify and exploit blind XSS vulnerabilities, which can be difficult to find using traditional methods.

Once a ezXSS payload is planted, the user can wait until it is triggered, at which point ezXSS will store and alert the user all the information of the vulnerable page. These reports can then be used to identify and track important data. Payloads can even be updated to make the XSS persistent, allowing to track the victim over all visited pages and open an reverse proxy.

### Features
* Easy to use dashboard with settings, statistics, payloads, view/share/search reports
* :new: Persistent XSS sessions with reverse proxy aslong as the browser is active
* Manage unlimited users with permissions to personal payloads & their reports
* Instant alerts via mail, Telegram, Slack, Discord or custom callback URL
* Custom extra javascript payloads
* Custom payload links to distinguish insert points
* Extract additional pages, block, whitelist and other filters
* Secure your login with Two-factor (2FA)
* The following information can be collected on a vulnerable page:
    * The URL of the page
    * IP Address
    * Any page referer (or share referer)
    * The User-Agent
    * All Non-HTTP-Only Cookies
    * All Locale Storage
    * All Session Storage
    * Full HTML DOM source of the page
    * Page origin
    * Time of execution
    * Payload URL
    * Screenshot of the page
    * Extract additional defined pages
* Triggers in all browsers, starting from Chrome 3+, IE 8+, Firefox 4+, Opera 10.5+, Safari 4+
* much much more, and, its just ez :-)

### Required
* Server or shared web hosting with PHP 7.1 or up
* Domain name (consider a short one or check out [shortboost](https://github.com/ssl/shortboost))
* SSL Certificate to test on https websites (consider Cloudflare or Let's Encrypt for a free SSL)

### Installation
ezXSS is ez to install with Apache, NGINX or Docker

visit the [wiki](https://github.com/ssl/ezXSS/wiki) for installation instructions.


### Explore ezXSS hassle free
Interested in using ezXSS but don't want to install it yet? Worry not! You can access and start using ezXSS with a free account on [ez.pe](https://ez.pe). Simply sign up and get started without any installation hassle.

Additionally, if you'd like to explore and test the tool before committing, there is a demo environment with admin account available at [demo.ezxss.com/manage](https://demo.ezxss.com/manage).

Please note that some features might be disabled or limited in both the free account on ez.pe and the demo environment. These limitations are in place to maintain the integrity and security of the platforms. However, you can still get a good grasp of the tool's capabilities and decide after to install it yourself.

### Sponsors
Maintenance of this project is made possible by all the contributors and sponsors. 
I've personally worked for over 8 years on this project, taking hundreds of hours from my time. Please kindly consider becoming a sponsor, so I can continue maintaining and improving ezXSS as well as creating and releasing new projects. Our current sponsors:

<p align="center">
<a href="https://github.com/geeknik"><img src="https://github.com/geeknik.png" width="50px" alt="geeknik" /></a>&nbsp;&nbsp;
<a href="https://github.com/GlitchSecure"><img src="https://github.com/GlitchSecure.png" width="50px" alt="GlitchSecure" /></a>&nbsp;&nbsp;
<a href="https://github.com/vaadata"><img src="https://github.com/vaadata.png" width="50px" alt="VAADATA" /></a>&nbsp;&nbsp;
</p>

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
<hr>
ezXSS is a tool that is designed to help find and exploit cross-site scripting (XSS) vulnerabilities. One of the key features of ezXSS is its ability to identify and exploit blind XSS vulnerabilities, which can be difficult to find using traditional methods.
<br><br>
Once an ezXSS payload is placed, the user must wait until it is triggered, at which point ezXSS will store and alert the user all the information of the vulnerable page. These reports can then be used to further identify and track important data. Payloads can even be updated to make the XSS persistent, allowing to track the infected user over all visited pages and open a reverse proxy.

## Features
* Easy to use dashboard with settings, statistics, payloads, view/share/search reports
* Persistent XSS sessions with reverse proxy aslong as the browser is active
* Manage unlimited users with permissions to personal payloads & their reports
* Instant alerts via mail, Telegram, Slack, Discord or custom callback URL
* Custom extra javascript payloads
* Custom payload links to distinguish insert points
* Extract additional paths, automatic (recursive) spider, block, whitelist and more filters
* :new: Extensions that can add or edit ezXSS payload functions (check out [ezXSS-extensions](https://github.com/ssl/ezXSS-extensions))
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
    * Extracted additional defined paths
    * Extracted content of links from (recursive) spider
    * Any extra custom fields (via extensions or custom JS)
* Triggers in all browsers, starting from Chrome 1+, IE 6+, Firefox 1+, Opera 10+, Safari 4+
* much much more, and, its just ez :-)

## Required
* Server or shared web hosting with PHP 7.1 or up
* Domain name (consider a short one or check out [shortboost](https://github.com/ssl/shortboost))
* SSL Certificate to test on https websites (consider Cloudflare or Let's Encrypt for a free SSL)

## Installation
ezXSS is ez to install with Apache, NGINX or Docker

visit the [wiki](https://github.com/ssl/ezXSS/wiki) for installation instructions.


## Explore ezXSS hassle free
Interested in using ezXSS but don't want to install it yet? Worry not! You can access and start using ezXSS with a free account without the need of a domainname or a webserver. Simply sign up and get started without any installation hassle.

[Create account on ezxss.com](https://ezxss.com)

## Contribute
Maintenance of this project is made possible by all the contributors and sponsors. 
I've personally worked for over 8 years on this project, taking hundreds of hours from my time. Please kindly consider becoming a sponsor, so I can continue maintaining and improving ezXSS as well as creating and releasing new projects. Current sponsors and (past) sponsors/contributors with a big impact on the project:

<p align="center">
<!-- sponsors --><a href="https://github.com/GlitchSecure"><img src="https:&#x2F;&#x2F;github.com&#x2F;GlitchSecure.png" width="60px" alt="GlitchSecure" /></a>&nbsp;&nbsp;<!-- sponsors -->
</p>
<p align="center">
<!--loveforever-->
<a href="https://github.com/geeknik"><img src="https:&#x2F;&#x2F;github.com&#x2F;geeknik.png" width="40px" alt="geeknik" /></a>&nbsp;&nbsp;<a href="https://github.com/dev"><img src="https:&#x2F;&#x2F;github.com&#x2F;dev.png" width="40px" alt="dev" /></a>&nbsp;&nbsp;<a href="https://github.com/mounssif"><img src="https:&#x2F;&#x2F;github.com&#x2F;mounssif.png" width="40px" alt="mounssif" /></a>&nbsp;&nbsp;
<!--loveforever-->
<br><br><a href="https://github.com/sponsors/ssl">Become a sponsor</a>
  <br>or, leave a star!<br><br>
</p>

<p align="center">
  <img src="https://api.star-history.com/svg?repos=ssl/ezxss&type=date&legend=top-left" alt="Star History Chart">
</p>



# ezXSS
ezXSS is an easy way for penetration testers and bug bounty hunters to test (blind) Cross Site Scripting.

## Current features
Some features ezXSS has

* Easy to use dashboard with statics, payloads, view/share/search reports and more
* Payload generator
* Instant email alert on payload
* Custom javascript payload
* Custom payload links to distinguish insert points
* Enable/Disable screenshots
* Prevent double payloads from saving or alerting
* Block domains
* Share reports with a direct link, via email or with other ezXSS users
* Easily manage and view (multiple) reports
* Secure your login with extra protection (2FA)
* Killswitch
* The following information is collected on a vulnerable page:
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
    * Screenshot of the page
* its just ez :-)

## Required
* A host with PHP 7.1 or up
* A domain name (consider a short one)
* An SSL if you want to test on https websites (consider Cloudflare or Let's Encrypt for a free SSL)

## Installation
ezXSS is ez to install with Apache, NGINX or Docker

visit the [wiki](https://github.com/ssl/ezXSS/wiki/Installation) for installation instructions.

## Demo
For a demo visit [demo.ezxss.com/manage](https://demo.ezxss.com/manage) with password *demo1234*. Please note that some features might be disabled in the demo version.

## Screenshots

![Dashboard](https://i.imgur.com/RnCelmA.png)
![Settings](https://i.imgur.com/NYP1yBN.png)
![Payload](https://i.imgur.com/WCE7TC9.png)
![Reports](https://i.imgur.com/TdwA7OZ.png)
![Login](https://i.imgur.com/jOIPjvt.png)

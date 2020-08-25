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
* Share reports with a direct link or with other ezXSS users
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
ezXSS is ez to install

* Clone the repository and put the files in the document root
* Rename `.env.example` to `.env`
* Create an empty database and provide your database information in `.env`
* Visit /manage/install in your browser and setup a password and email
* Done! That was ez right?

Make sure you have Apache with headers, rewrite and curl modules enabled (with a2enmod, apt-get curl+php-curl) and that .htaccess file is correctly uploaded and allowed in vhost config (AllowOverride All).


## Installation with docker-compose
* Clone the repository and cd into it
* Rename `.env.example` to `.env` and update it with a secure randomly generated password
* Update SMTP info in `php/msmtprc`
* run `docker-compose build && docker-compose up -d`
* Visit /manage/install in your browser and setup a password and email
* Done! That was ez right?

## Demo
For a demo visit [demo.ezxss.com/manage](https://demo.ezxss.com/manage) with password *demo1234*. Please note that some features might be disabled in the demo version.

## Screenshots

![Dashboard](https://i.imgur.com/RnCelmA.png)
![Settings](https://i.imgur.com/NYP1yBN.png)
![Payload](https://i.imgur.com/WCE7TC9.png)
![Reports](https://i.imgur.com/TdwA7OZ.png)
![Login](https://i.imgur.com/jOIPjvt.png)

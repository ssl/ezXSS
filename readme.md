# ezXSS
ezXSS is an easy way to test (blind) XSS.

## Current features
Some features ezXSS has

* Easy to use dashboard with statics, payloads, view reports, search reports and more
* Payload generator on dashboard
* Email alert on payload
* Full page screenshot
* Prevent double payloads from saving or alerting
* Share reports with other ezXSS users
* The following information is collected everytime a probe fires on a vulnerable page:
    * The URL of the page
    * IP Address 
    * Any page referer
    * The User-Agent
    * All Non-HTTP-Only Cookies
    * Full HTML DOM source of the page
    * Page origin
    * Time of execution
* its just ez :-)

## Required
* PHP 5.3 or newer
* A domain name (consider a short one)
* An SSL (consider Cloudflare or Let's Encrypt for a free SSL)

## Installation
ezXSS is ez to install

* Download the 'files' folder and put all the files inside your root
* Create an empty database and provide your database information in '/manage/src/Database.php' (also set isSet on true)
* Go to yoursite.com/install.php and setup a username, password and alert email
* Make sure the install.php file is deleted and the XSS works, try the XSS on [w3schools](https://www.w3schools.com/html/tryit.asp?filename=tryhtml_intro) or [codepen](https://codepen.io).

## Todo
Some things I am planning to add/change in a future version. This list is sorted on how important/fast it is going to be added.

Adding in the new 2.0 version:
* ~~New design~~
* New email design
* Making installation ez with a installation page
* ~~Cleaning up some bad-practice code~~
* ~~Fix searching~~
* ~~New way of searching~~
* Delete screenshot image if report is deleted
* Page alerts or Google Chrome alerts on new report
* A lot of small features added, updated or deleted.
* ~~A new favicon~~
* ~~Password only login (removing username)~~
* Adding 2FA Google Auth for people who want extra security

Adding in a future version:
* Page grabbing (& on regex)
* Live JS - send JS code LIVE while the person is on the page
* Callback API for alerts on Telegram etc.
* You got ideas?

## Why?
If you want to host [xsshunter](https://github.com/mandatoryprogrammer/xsshunter) yourself you need a linux server and a Mailgun account. I wanted to create a just PHP version which you can even host on shared hostings or localhost. ezXSS has almost all features that xsshunter has and even more (and adding).

## Screenshots
> ![Dashboard](https://camo.githubusercontent.com/475465b4e3eabc40e66f02881c70176b8cac60d3/687474703a2f2f692e696d6775722e636f6d2f674e70497a51642e706e67)
> ![Settings](https://camo.githubusercontent.com/ba79d75005cd98fdefeabeb4b5dea41cbe134622/687474703a2f2f692e696d6775722e636f6d2f39596e716b62582e706e67)
> ![Payload](https://camo.githubusercontent.com/2a13fd993cc9d12670dc0db203e4eb080c5dd11b/68747470733a2f2f67792e65652f61786f)
> ![Filters](https://camo.githubusercontent.com/ec377979979536b320c14f7e6f128ed524cf60c0/68747470733a2f2f67792e65652f617869)
> ![Share](https://camo.githubusercontent.com/a62ed168c6becd39c330e04fa886cab874a89b3e/68747470733a2f2f67792e65652f617875)
> ![All reports](https://camo.githubusercontent.com/251872dc3fe809a159750342b0adc0f97ef11a5c/68747470733a2f2f67792e65652f617871)
> ![View report](https://camo.githubusercontent.com/5e12587698c36dab841205f7d68bc26e2644b50f/68747470733a2f2f67792e65652f616d75)
> ![Search](https://camo.githubusercontent.com/bf0e25bb00f84d5fd4f524faaa012734275fe573/68747470733a2f2f67792e65652f617877)

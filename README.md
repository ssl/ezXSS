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

* Making installation ez with a installation page
* Making updating ez with 1 click update page
* Cleaning up some bad-practice code
* Easy payload tester
* Page alerts
* Live JS - send JS code LIVE while the person is on the page
* Page grabbing (& on regex)
* You got ideas?

## Why?
If you want to host [xsshunter](https://github.com/mandatoryprogrammer/xsshunter) yourself you need a linux server and a Mailgun account. I wanted to create a just PHP version which you can even host on shared hostings or localhost. ezXSS has almost all features that xsshunter has and even more (and adding).

## Screenshots
> ![View report](http://i.imgur.com/FXbaFkD.png)

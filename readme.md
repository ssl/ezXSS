# ezXSS
ezXSS is an easy way to test (blind) Cross Site Scripting.

## Current features
Some features ezXSS has

* Easy to use dashboard with statics, payloads, view/share/search reports and more
* Payload generator
* Instant email alert on payload
* Custom javascript for extra testing
* Prevent double payloads from saving or alerting
* Share reports with other ezXSS users
* Easily manage and view reports in the system
* Search for reports in no time
* Secure your system account with extra protection (2FA)
* The following information is collected on a vulnerable page:
    * The URL of the page
    * IP Address
    * Any page referer (or share referer)
    * The User-Agent
    * All Non-HTTP-Only Cookies
    * Full HTML DOM source of the page
    * Page origin
    * Time of execution
* its just ez :-)

## Required
* PHP 5.5 or up
* A domain name (consider a short one)
* An SSL if you want to test on https websites (consider Cloudflare or Let's Encrypt for a free SSL)

## Installation
ezXSS is ez to install

* Download the 'files' folder and put all the files inside your root
* Create an empty database and provide your database information in '/manage/src/Database.php'
* Go to /manage/install in your browser and setup a password and email
* Done! That was ez right?

## To do list
Some things I am planning to add/change in future versions. This list is sorted on how important/fast it is going to be added.

I'm currently busy with ezXSS 3.0.

Adding in a future versions:
* Finishing the API
* Make all files OOP (1 file left)
* Page grabbing
* Live JS - send JS code LIVE while the person is on the page
* You got ideas?

## Why?
If you want to host [xsshunter](https://github.com/mandatoryprogrammer/xsshunter) yourself you need a linux server and a Mailgun account. I wanted to create a just PHP version which you can even host on shared hostings or local. ezXSS has almost all features that xsshunter has and even more (and adding).

## Screenshots

![Dashboard](https://i.imgur.com/0us9M4M.png)
![Settings](https://i.imgur.com/5BbdyYQ.png)
![Payload](https://i.imgur.com/5nKDqcQ.png)
![Reports](https://i.imgur.com/6TTXOw3.png)
![Login](https://i.imgur.com/I9W7jxU.png)

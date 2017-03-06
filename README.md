# ezXSS
ezXSS is an easy way to test (blind) XSS.

## Current features
Some features ezXSS has

* Easy to use dashboard with statics, payloads, view reports, search reports and more
* Payload generator on dashboard
* Email alert on payload
* Full page screenshot
* The following information is collected everytime a probe fires on a vulnerable page: (c xsshunter)
    * The vulnerable page's URI 
    * Origin of Execution 
    * The Victim's IP Address (and proxy IPs)
    * The Page Referer 
    * The Victim's User Agent 
    * All Non-HTTP-Only Cookies 
    * The Page's Full HTML DOM 
    * Full Screenshot of the Affected Page 
* its just ez :-)

## Installation
ezXSS is ez to install

* Download the 'files' folder and put all the files inside your root (without the 'files' folder)
* Create an empty database and provide your database information in '/manage/src/Database.php' (also set isSet on true)
* Go to yoursite.com/install.php and setup a username, password and alert email
* Make sure the install.php file is deleted and the XSS works, try the XSS on [w3schools](https://www.w3schools.com/html/tryit.asp?filename=tryhtml_intro) or codepen.
* Login to your account via yoursite.com/manage/login to view stats, reports, seach reports, get payloads and update settings.

## Todo
Some things I am planning to add/change in a future version

* Page alerts
* Live JS - send JS code LIVE while the person is on the page
* Fixing some responsive things (dashboard is responsive but some bugs)
* Page grabbing (& on regex)
* Cleanup code in Components
* Cleanup code overal, there is some bad-practice code thats need to be fixed
* You got ideas?

## Why?
If you want to host [xsshunter](https://github.com/mandatoryprogrammer/xsshunter) yourself you need a linux server and a Mailgun account. I wanted to create a just PHP version which you can even host on shared hostings or localhost. ezXSS has the most important features that xsshunter has (and more ezXSS-only adding). The idea and the JS file of ezXSS is based on xsshunter, all other files are self made.

## Screenshots
> ![View report](http://i.imgur.com/FXbaFkD.png)

# ezXSS docker-compose
ezXSS is an easy way for penetration testers and bug bounty hunters to test (blind) Cross Site Scripting.

Now even better running inside two containers (app+db) for easy deployment!

## Current features
Some features ezXSS has

* Easy to use dashboard with statics, payloads, view/share/search reports and more
* Payload generator
* Instant email alert on payload
* Custom javascript payload
* Enable/Disable screenshots
* Prevent double payloads from saving or alerting
* Block domains
* Share reports with a direct link or with other ezXSS users
* Easily manage and view reports in the dashboard
* Secure your login with extra protection (2FA)
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
* A host with docker and docker-compose
* A domain name (consider a short one)
* nginx-proxy-manager for reverse proxy stuff

## Installation
ezXSS is now even easier to use:

### Clone the repository
`git clone https://github.com/GlitchWitchSec/ezXSS-docker.git`

### Change the credentials
Set a random passwords in `./docker-compose.yml`

Update SMTP info in `/php/msmtprc`

### Build and run
```
docker-compose build
docker-compose up -d
```


Visit `/manage/install` in your browser and setup a password and email


## Demo
For a demo visit [demo.ezxss.com/manage](https://demo.ezxss.com/manage) with password *demo1234*. Please note that some features might be disabled in the demo version.

## Screenshots

![Dashboard](https://i.imgur.com/79wSggJ.png)
![Settings](https://i.imgur.com/oybLHTn.png)
![Payload](https://i.imgur.com/Aibuvzz.png)
![Reports](https://i.imgur.com/xT1MmO1.png)
![Login](https://i.imgur.com/bEzskKo.png)

# ezXSS
ezXSS is an easy way for penetration testers and bug bounty hunters to test (blind) Cross Site Scripting.

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
* A host with PHP 7.1 or up
* A domain name (consider a short one)
* An SSL if you want to test on https websites (consider Cloudflare or Let's Encrypt for a free SSL)

## Installation
ezXSS is ez to install

Apache:
    * Clone the repository and put the files in the document root ( for example - /var/www/html/ezxss )
    * Create an empty database and provide your database information in 'src/Database.php'
    * Visit /manage/install in your browser and setup a password and email
    * Done! That was ez right?

Nginx:
    * Clone the repository and put the files in the document root
    * Create an empty database and provide your database information in 'src/Database.php'
    * Edit nginx site-enabled file, create file named ezxss.conf
    * Add below code, change server_name to your domain name
    * Restart your nginx server ( service nginx restart )
    * Done! Enjoy your new ezXSS 3.0!

    server {
        listen 80;
        listen [::]:80;

        root /var/www/html/ezxss;
        client_max_body_size 150m;
        index index.php index.html index.htm index.nginx-debian.html;

        server_name 'YOUR DOMAIN NAME';
        add_header 'Access-Control-Allow-Origin' '*';
        add_header 'Access-Control-Allow-Methods' 'GET, POST';
        add_header 'Access-Control-Allow-Headers' 'origin, x-requested-with, content-type';

        autoindex off; 
        location /
        {
                if ($uri !~ "assets")
                {
                        set $rule_0 1$rule_0;
                }

                if ($rule_0 = "1")
                {
                        rewrite ^/(.*)$ /index.php;
                }
        }

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
        }   
    }



## Demo
For a demo visit [demo.ezxss.com/manage](https://demo.ezxss.com/manage) with password *demo1234*. Please note that some features might be disabled in the demo version.

## Screenshots

![Dashboard](https://i.imgur.com/79wSggJ.png)
![Settings](https://i.imgur.com/oybLHTn.png)
![Payload](https://i.imgur.com/Aibuvzz.png)
![Reports](https://i.imgur.com/xT1MmO1.png)
![Login](https://i.imgur.com/bEzskKo.png)

<p align="center">
  <img src="https://i.imgur.com/oPtfbDG.png"><br>
  ezXSS is an easy way for penetration testers and bug 
  bounty hunters to test (blind) Cross Site Scripting.<br><br>
  <img src="https://img.shields.io/github/release/ssl/ezXSS">
  <img src="https://img.shields.io/github/issues/ssl/ezXSS">
  <img src="https://img.shields.io/github/forks/ssl/ezXSS">
  <img src="https://img.shields.io/github/stars/ssl/ezXSS">
  <img src="https://img.shields.io/github/license/ssl/ezXSS">
</p>

## Features
* Easy to use dashboard with settings, statistics, payloads, view/share/search reports
* Unlimited users with permissions to personal payloads & their reports
* Instant alerts via mail, Telegram, Slack, Discord or custom callback URL
* Custom javascript payloads
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
* much much more, and, its just ez :-)

## Required
* Server or hosting with PHP 7.1 or up
* Domain name (consider a short one)
* SSL Certificate to test on https websites (consider Cloudflare or Let's Encrypt for a free SSL)

## Installation
ezXSS is ez to install with Apache, NGINX or Docker

visit the [wiki](https://github.com/ssl/ezXSS/wiki/Installation) for installation instructions.

## Live demo
For a demo visit [demo.ezxss.com/manage](https://demo.ezxss.com/manage) with password *demo1234*. Please note that some features might be disabled in the demo version.

<?php

class Basic
{

    public function __construct()
    {
        $this->base32Characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    }

    public function screenshotPath($screenshotName)
    {
        return '<img style="max-width: 100%;" src="http://' . $this->domain() . '/assets/img/report-' . $screenshotName . '.png">';
    }

    public function domain()
    {
        return htmlspecialchars($_SERVER['SERVER_NAME']);
    }

    public function getCode($secret)
    {
        $secretKey = $this->baseDecode($secret);
        $hash = hash_hmac('SHA1', chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', floor(time() / 30)), $secretKey, true);
        $value = unpack('N', substr($hash, ord(substr($hash, -1)) & 0x0F, 4));
        $value = $value[1] & 0x7FFFFFFF;
        return str_pad($value % pow(10, 6), 6, '0', STR_PAD_LEFT);
    }

    private function baseDecode($data)
    {
        $characters = $this->base32Characters;
        $buffer = 0;
        $bufferSize = 0;
        $result = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $position = strpos($characters, $data[$i]);
            $buffer = ($buffer << 5) | $position;
            $bufferSize += 5;
            if ($bufferSize > 7) {
                $bufferSize -= 8;
                $position = ($buffer & (0xff << $bufferSize)) >> $bufferSize;
                $result .= chr($position);
            }
        }
        return $result;
    }

    public function htmlBlocks($htmlBlock)
    {
        if ($htmlBlock == 'menu') {
            return <<<HTML
        <div class="navbar-header">
          <div id="mobile-menu">
            <div class="left-nav-toggle"><a href="#"><i class="fa fa-bars"></i></a></div>
          </div>
        </div>

        <aside class="navigation">
          <nav>
            <ul class="nav ezxss-nav">

              <li class="nav-info">
                <i class="pe pe-7s-shield text-accent"></i>
                <div class="m-t-xs"><span class="c-white">ezXSS v{{version}}</span> github.com/ssl/ezxss</div>
              </li>

              <li class="nav-category">Main</li>
              <li><a href="/manage/dashboard">Dashboard</a></li>
              <li><a href="/manage/settings">Settings</a></li>
              <li><a href="/manage/payload">Payload</a></li>

              <li class="nav-category">Reports</li>
              <li><a href="/manage/reports">Reports</a></li>
              <li><a href="/manage/archive">Archived reports</a></li>
            </ul>
          </nav>
        </aside>
HTML;
        }

        if ($htmlBlock == 'menuHidden') {
            return <<<HTML
        <div class="navbar-header">
          <div id="mobile-menu">
            <div class="left-nav-toggle"><a href="#"><i class="fa fa-bars"></i></a></div>
          </div>
        </div>

        <aside class="navigation">
          <nav>
            <ul class="nav ezxss-nav">

              <li class="nav-info">
                <i class="pe pe-7s-shield text-accent"></i>
                <div class="m-t-xs"><span class="c-white">ezXSS v{{version}}</span> github.com/ssl/ezxss</div>
              </li>

            </ul>
          </nav>
        </aside>
HTML;
        }

        if ($htmlBlock == 'main') {
            return <<<HTML
        <!DOCTYPE html>
        <html>
          <head>
            <meta charset="utf-8">
            <title>ezXSS ~ {{title}}</title>
            <link rel="stylesheet" href="https://ssl.github.io/cdn/ezXSS/css/font-awesome.css" integrity="sha384-CA7nicOiG9xLJZ8K81i/oOvxFmpce86FdhD3mkdgvfuGMigwTwBElOMVQvEjkV9X" crossorigin="anonymous">
            <link rel="stylesheet" href="https://ssl.github.io/cdn/ezXSS/css/bootstrap.css" integrity="sha384-GdlB5PJOMUfj80P5h0H9An3utYRUtyihpjksDyocWu1+XptBb/QB/sPgCrgYJCuZ" crossorigin="anonymous">
            <link rel="stylesheet" href="https://ssl.github.io/cdn/ezXSS/css/3.0/style.css" integrity="sha384-Iny6sL805ZtFOrZ5JEzmYazKDtzoxw0CRXvMw7jLE/FqN3ehYg+RM2BTb1G2q+XY" crossorigin="anonymous">
            <link rel="stylesheet" href="/assets/css/new.css">
            <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon" />
            <meta name="MobileOptimized" content="width">
            <meta name="HandheldFriendly" content="true">
            <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
          </head>
          <body>
            {{template}}

            <script src="https://ssl.github.io/cdn/ezXSS/js/jquery.js" integrity="sha384-3ceskX3iaEnIogmQchP8opvBy3Mi7Ce34nWjpBIwVTHfGYWQS9jwHDVRnpKKHJg7" crossorigin="anonymous"></script>
            <script src="https://ssl.github.io/cdn/ezXSS/js/bootstrap.js" integrity="sha384-hFgRcCfdoHZzpNxRIokVxKLVvuKCFoY3CNDrnFkk7pGwsQNKvflHATtmGxcYcgbs" crossorigin="anonymous"></script>
            <script src="/assets/js/ezxss.js" charset="utf-8"></script>
          </body>
        </html>
HTML;
        }

        if ($htmlBlock == 'searchBar') {
            return <<<HTML
        <form method="get" action="../reports">
          <input type="text" value="{{searchQuery}}" name="search" placeholder="Search for domain, IP or URL" class="form-control" style="float: left;width: 260px;">
          <button type="submit" class="btn" style="float:right;">Search</button>
        </form>
HTML;
        }

        if ($htmlBlock == 'twofactorEnable') {
            return <<<HTML
        <div class="form-group">
          <label class="control-label" for="secret">Secret code</label>
          <div class="input-group">
            <input type="text" name="secret" id="secret" value="{{secret}}" disabled class="form-control">
            <span class="input-group-addon"><a data-toggle="modal" data-target="#openQRcode" style="cursor:pointer">open QR code</a></span>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label" for="code">Activate with code</label>
          <input type="text" name="code" id="code" value="" class="form-control">
        </div>

        <button class="btn">Save</button>
HTML;
        }

        if ($htmlBlock == 'twofactorDisable') {
            return <<<HTML
        <div class="form-group">
          <p>You already enabled 2FA. Enter the code to disable it.</p>
        </div>

        <div class="form-group">
          <label class="control-label" for="code">Disable with code</label>
          <input type="hidden"  hidden id="secret" value="0">
          <input type="text" name="code" id="code" value="" class="form-control">
        </div>

        <button class="btn">Save</button>
HTML;
        }

        if ($htmlBlock == 'twofactorLogin') {
            return <<<HTML
        <div class="form-group">
          <label class="control-label" for="password">2FA Code</label>
          <input type="text" name="code" id="code" class="form-control">
        </div>
HTML;
        }

        if ($htmlBlock == 'reportList') {
            return <<<HTML

        <tr id="{{report[id]}}">
          <td scope=row style="width:50px;max-width:50px;border-color:#5b6187">
             <label class="checkbox-label">
                <input class="chkbox" type="checkbox" name="selected" value="{{report[id]}}" id="chk_{{report[id]}}" report-id="{{report[id]}}">
                <span class="checkbox-custom rectangular"></span>
            </label>           
          </td>
          <td><b>{{report[id]}}</b></td>
          <td>
            <div class="btn-group btn-view" style="width:100px;max-width:100px;" role=group>
              <a href="report/{{report[id]}}" class=btn>View</a>
              <div class=btn-group role=group>
                <button type=button class="btn btn-default dropdown-toggle" data-toggle=dropdown aria-haspopup=true aria-expanded=true>
                  <span class=caret></span>
                </button>

                <div class=dropdown-backdrop></div>

                <ul class=dropdown-menu>
                  <li><a class="share" report-id="{{report[id]}}" share-id="{{report[shareid]}}" data-toggle="modal" data-target="#shareModal">Share</a></li>
                  <li><a class="delete" report-id="{{report[id]}}">Delete</a></li>
                  <li><a class="archive" report-id="{{report[id]}}">Archive</a></li>
                </ul>

              </div>
            </div>
          </td>
          <td>{{report[uri]}}</td>
          <td>{{report[ip]}}</td>
        </tr>
HTML;
        }

        if ($htmlBlock == 'mail') {
            return <<<HTML
        <!DOCTYPE html>
        <html>
          <head>
            <meta charset="utf-8">
            <title>ezXSS</title>
            <style media="screen">
            .mail{background-color:#23284b;font-family:Roboto Mono,monospace;color:#6e749b;padding:5px;border-radius:5px;padding-left:25px;padding-right:25px}.h{color:#fff;font-size:24px;margin-bottom:2px}small{font-size:85%}hr{margin-top:20px;margin-bottom:20px;border:0;border-top:1px solid #2b3157}.panel{background-color:#2b3157;box-shadow:none;color:#5e648b;border-radius:3px;margin-bottom:20px;border:1px solid transparent}.panel-heading{background-color:#343a60;border-bottom:#30355c 3px solid;color:#fff;padding:10px 15px;border-top-left-radius:3px;border-top-right-radius:3px}.panel-body{padding-top:10px;padding:5px 15px 15px 15px}table{width:100%;max-width:100%;margin-bottom:20px;background-color:transparent;border-spacing:0;border-collapse:collapse}tr{display:table-row}th{border-top:0;border-color:#5e648b;border-bottom:none;padding:8px;vertical-align:middle;color:#fff;width:150px;text-align:left}td{border-color:#5e648b;border-top:1px solid #5e648b;padding:8px;vertical-align:middle;line-height:1.42857143;word-wrap:break-word;max-width:800px}a{color:#5e648b!important}
            </style>
          </head>
          <body>
            <div class="mail">
              <div class="view-header">
                <div class="header-title">
                  <h3 class="h">XSS Report #{{id}}</h3>
                  <small>Get a fast view below or view the whole report on https://{{domain}}/manage/report/{{id}}</small>
                </div>
              </div>
              <hr>
              <div class="panel">
                <div class="panel-heading">View report</div>
                <div class="panel-body">
                  <table class="table">
                    <thead>
                      <tr>
                        <th>Key</th>
                        <th>Value</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>URL</td>
                        <td style="color: #5e648b;">{{url}}</td>
                      </tr>
                      <tr>
                        <td>IP</td>
                        <td>{{ip}}</td>
                      </tr>
                      <tr>
                        <td>Referer</td>
                        <td style="color: #5e648b;">{{referer}}</td>
                      </tr>
                      <tr>
                        <td>Payload</td>
                        <td style="color: #5e648b;">{{payload}}</td>
                      </tr>
                      <tr>
                        <td>User Agent</td>
                        <td>{{user-agent}}</td>
                      </tr>
                      <tr>
                        <td>Cookies</td>
                        <td style="color: #5e648b;">{{cookies}}</td>
                      </tr>
                      <tr>
                        <td>Local Storage</td>
                        <td><textarea spellcheck="false" style="width: 100%;color: #5e648b;height: 100px;background-color: transparent;border-color: #52587e;resize:vertical">{{localstorage}}</textarea></td>
                      </tr>
                      <tr>
                        <td>Session Storage</td>
                        <td><textarea spellcheck="false" style="width: 100%;color: #5e648b;height: 100px;background-color: transparent;border-color: #52587e;resize:vertical">{{sessionstorage}}</textarea></td>
                      </tr>

                      <tr>
                        <td>DOM</td>
                        <td><textarea spellcheck="false" style="width: 100%;color: #5e648b;height: 100px;background-color: transparent;border-color: #52587e;resize:vertical">{{dom}}</textarea></td>
                      </tr>
                      <tr>
                        <td>Origin</td>
                        <td>{{origin}}</td>
                      </tr>
                      <tr>
                        <td>Time</td>
                        <td>{{time}}</td>
                      </tr>
                      <tr>
                        <td>Screenshot</td>
                        <td>{{screenshot}}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </body>
        </html>
HTML;
        }

        return '';
    }
}

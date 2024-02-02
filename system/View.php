<?php

class View
{
    /**
     * Current page content
     * 
     * @var string
     */
    private $content;

    /**
     * Current page title
     * 
     * @var string
     */
    public $title;


    /**
     * Constructor which adds certain headers
     */
    public function __construct()
    {
        // Add security headers
        header('X-XSS-Protection: 1; mode=block');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Add CSP header to manage
        if (explode('/', $_SERVER['REQUEST_URI'] ?? '/')[1] === 'manage') {
            header("Content-Security-Policy: default-src 'self'; img-src 'self' data: chart.googleapis.com; font-src fonts.gstatic.com; script-src 'self' 'nonce-csrf'; style-src 'self' 'unsafe-inline'; frame-ancestors 'none';");
        }
    }

    /**
     * Makes a render of a page with only an error message
     *
     * @param string $message The error message
     * @return string
     */
    public function renderErrorPage($message)
    {
        $this->content = '';
        $this->renderTemplate('system/error');
        $this->renderMessage($message);
        $this->content = str_replace('{theme}', '', $this->content);
        return $this->showContent();
    }

    /**
     * Updates the message template with the given message
     *
     * @param string $message The message
     * @return void
     */
    public function renderMessage($message)
    {
        $content = $this->getContent();

        $content = str_replace('{message}', '<div class="alert" role="alert">' . nl2br(e($message)) . '</div>', $content);

        $this->content = $content;
    }

    /**
     * Updates all the data parameters with the correct data
     *
     * @param string $param The param
     * @param string $value The value
     * @param bool $plain Text or HTML
     * @return void
     */
    public function renderData($param, $value, $plain = false)
    {
        $content = $this->getContent();

        if ($plain) {
            $content = str_replace('{%data ' . $param . '}', $value ?? '', $content);
        } else {
            $content = str_replace('{%data ' . $param . '}', e($value ?? ''), $content);
        }

        $this->content = $content;
    }

    /**
     * Updates all the data parameters with the correct data with newlines
     *
     * @param string $param The param
     * @param string $value The value
     * @param bool $plain Text or HTML
     * @return void
     */
    public function renderDataWithLines($param, $value, $plain = false)
    {
        $content = $this->getContent();
        $value = $plain ? ($value ?? '') : (e($value) ?? '');

        if ($value === '') {
            $content = preg_replace("/{%data $param.*?\n/", '', $content);
        } else {
            $content = str_replace('{%data ' . $param . '}', "\n" . $value, $content);
        }

        $this->content = $content;
    }

    /**
     * Updates all if statements in the view template based on the given boolean
     *
     * @param string $condition The condition name
     * @param bool $bool The condition value
     * @param bool $falseCondition Whether condition is true or false
     * @return void
     */
    public function renderCondition($condition, $bool, $falseCondition = false)
    {
        $content = $this->getContent();

        $search = !$falseCondition ? '/{%if ' . $condition . '}(.*?){%\/if}/s' : '/{%!if ' . $condition . '}(.*?){%\/!if}/s';
        preg_match_all($search, $content, $matches);
        foreach ($matches[1] as $key => $value) {
            // Shows the content if the given boolean is true
            if ($bool === true) {
                $content = str_replace(
                    $matches[0][$key],
                    $matches[1][$key],
                    $content
                );
                // Removes the content block when false
            } else {
                $content = str_replace(
                    $matches[0][$key],
                    '',
                    $content
                );
            }
        }
        $this->content = $content;

        // Render the other side of the boolean (also known as `else`)
        if (!$falseCondition) {
            $this->renderCondition($condition, !$bool, true);
        }
    }

    /**
     * Renders a checked checkbox if checked
     * 
     * @param mixed $name The checkbox name
     * @param mixed $checked Whether checked or not
     * @return void
     */
    public function renderChecked($name, $checked)
    {
        $content = $this->getContent();

        $content = str_replace('{%checked ' . $name . '}', $checked ? 'checked' : '', $content);

        $this->content = $content;
    }

    /**
     * Renders a dataset in a foreach block, for example for in tables
     *
     * @param string $name The data set name
     * @param array $data The data to go in
     * @param bool $plain Text or HTML
     * @return void
     */
    public function renderDataset($name, $data, $plain = false)
    {
        $content = $this->getContent();

        // Finds all foreach blocks in a page
        preg_match_all('/{%foreach ' . $name . '}(.*?){%\/foreach}/s', $content, $blockMatches);
        foreach ($blockMatches[1] as $blockKey => $blockValue) {
            $template = $blockMatches[1][$blockKey];
            $htmlDump = '';

            // Find all parameters in the block
            preg_match_all('/{' . $name . '->(.*?)}/', $template, $templateMatches);

            foreach ($data as $item) {
                $template = $blockMatches[1][$blockKey];
                foreach ($templateMatches[1] as $templateKey => $templateValue) {
                    // Replace all parameters with the correct values from $data
                    $template = str_replace(
                        $templateMatches[0][$templateKey],
                        $plain ? $item[$templateMatches[1][$templateKey]] : e($item[$templateMatches[1][$templateKey]]),
                        $template
                    );
                }
                $htmlDump .= $template;
            }

            // Makes an complete new block with all data
            $content = str_replace(
                $blockMatches[0][$blockKey],
                $htmlDump,
                $content
            );
        }

        $this->content = $content;
    }

    /**
     * Renders the content of a function given
     *
     * @param string $view The view name
     * @return void
     */
    public function renderTemplate($view)
    {
        $content = $this->surroundBody($view);

        // Search and replace template content
        preg_match_all('/{(.*?)\[(.*?)]}/', $content, $matches);
        foreach ($matches[1] as $key => $value) {
            if (method_exists($this, $value)) {
                $content = str_replace(
                    $matches[0][$key],
                    e($this->$value((string) ($matches[2][$key]))),
                    $content
                );
            }
        }

        $this->content = $content;
        $this->renderCondition('userIsAdmin', $this->session('rank') == 7);
        $this->renderCondition('isLoggedIn', $this->session('rank') > 0);
    }

    /**
     * Renders the content of a payload
     * 
     * @param mixed $payload The payload name
     * @return void
     */
    public function renderPayload($payload)
    {
        $content = $this->getPayload($payload);

        // Search and replace payload content
        preg_match_all('/{{(.*?)}}/', $content, $matches);
        foreach ($matches[1] as $key => $value) {
            if (method_exists($this, $value)) {
                $content = str_replace(
                    $matches[0][$key],
                    e($this->$value()),
                    $content
                );
            }
        }

        $this->content = $content;
    }

    /**
     * Get payload by payload name
     * 
     * @param string $payload The payload name
     * @throws Exception
     * @return string
     */
    public function getPayload($payload)
    {
        $file = __DIR__ . "/../app/views/payloads/$payload.js";
        if (!is_file($file)) {
            throw new Exception('Payload not found');
        }
        return file_get_contents($file);
    }

    /**
     * Get alert by alert name
     * 
     * @param string $alert The alert name
     * @throws Exception
     * @return string
     */
    public function getAlert($alert)
    {
        $file = __DIR__ . "/../app/views/alerts/$alert";
        if (!is_file($file)) {
            throw new Exception('Alert not found');
        }
        return file_get_contents($file);
    }

    /**
     * Renders the data within an alert template
     * 
     * @param string $template The template
     * @param string|object $data The data
     * @return string
     */
    public function renderAlertData($template, $data)
    {
        $content = $template;
        preg_match_all('/{{(.*?)}}/', $template, $matches);
        foreach ($matches[1] as $key => $value) {
            if (is_object($data->{$matches[1][$key]})) {
                $data->{$matches[1][$key]} = json_encode($data->{$matches[1][$key]});
            }
            $content = str_replace(
                $matches[0][$key],
                substr(!empty($data->{$matches[1][$key]}) ? $data->{$matches[1][$key]} : '', 0, 1024),
                $content
            );
        }
        return $content;
    }

    /**
     * Surrounds the body content with the header and footer
     *
     * @param string $view The view name
     * @return string
     */
    public function surroundBody($view)
    {
        $content = $this->getHtml('system/header');
        $content .= $this->getHtml($view);
        $content .= $this->getHtml('system/footer');
        $content = str_replace('{menu}', $this->getHtml('system/menu'), $content);
        return $content;
    }

    /**
     * Returns HTML block of the given view
     *
     * @param string $file The file name
     * @return string
     */
    private function getHtml($file)
    {
        $file = __DIR__ . "/../app/views/$file.html";
        if (is_file($file)) {
            return file_get_contents($file);
        }
        return '404';
    }

    /**
     * Last function in controller to return all content from how its build
     *
     * @return string
     */
    public function showContent()
    {
        return str_replace('{message}', '', $this->content);
    }

    /**
     * Returns correct page title with site name
     *
     * @return string
     */
    public function title()
    {
        return 'ezXSS ~ ' . $this->title;
    }

    /**
     * Get's session data
     *
     * @param string $param The param
     * @return string
     */
    public function session($param)
    {
        return isset($_SESSION[$param]) ? e($_SESSION[$param]) : '';
    }

    /**
     * Returns current content
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Returns title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Returns ezXSS version
     *
     * @return string
     */
    public function version()
    {
        return e(version);
    }

    /**
     * Returns current file name
     * 
     * @return string
     */
    public function fileName()
    {
        return e(ltrim($_SERVER['REQUEST_URI'], '/'));
    }

    /**
     * Returns menu-active for the menu when its the current page
     * 
     * @param string $page The page to check
     * @return string
     */
    public function currentPage($page)
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $uriParts = explode('/', $uri);

        // Check current page for reporting pages
        if ((substr($page, -2) === '*0' || substr($page, -2) === '*1') && isset($uriParts[2]) && $uriParts[2] == 'reports') {
            if (isset($_GET['archive']) && $_GET['archive'] == '1') {
                if (substr($page, -2) === '*0') {
                    return 'menu-active';
                }
            } else {
                if (substr($page, -2) === '*1') {
                    return 'menu-active';
                }
            }
        }

        // Check other type of pages
        if (substr($page, -1) === '*' && isset($uriParts[2]) && $uriParts[2] == substr($page, 0, -1)) {
            return 'menu-active';
        }
        if (strpos($page, '/') !== false && isset($uriParts[3]) && $uriParts[2] . '/' . $uriParts[3] == $page) {
            return 'menu-active';
        }
        if (isset($uriParts[2]) && $uriParts[2] == $page && (!isset($uriParts[3]) || empty($uriParts[3]))) {
            return 'menu-active';
        }
        return '';
    }

    /**
     * Returns domain
     *
     * @return string
     */
    public function domain()
    {
        return host;
    }

    /**
     * Set's title
     *
     * @param string $title The new title
     * @return void
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Set's content type
     *
     * @param string $type The new content type
     * @return void
     */
    public function setContentType($type)
    {
        header('Content-Type: ' . $type . '; charset=UTF-8');
    }
}

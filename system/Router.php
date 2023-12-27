<?php

class Router
{
    /**
     * Contoller
     * 
     * @var string
     */
    public $controller = null;

    /**
     * Routing processing. All requests go through this function and matches it to the correct functions
     *
     * @param string $uri The current url
     * @return string
     */
    public function proccess($uri)
    {
        // Clean params from URI
        $uri = explode('?', explode('&', $uri)[0])[0];
        $parts = explode('/', $uri);

        if ($parts[1] === 'manage') {
            // Set controller and action from url
            $controller = isset($parts[2]) ? ucfirst(strtolower($parts[2])) : '';
            $controller = empty($controller) ? 'dashboard' : $controller;
            $method = isset($parts[3]) ? $parts[3] : 'index';
            $method = empty($method) ? 'index' : $method;

            try {
                // Check for nasty chars
                if (
                    preg_match('/[^A-Za-z0-9]/', $controller) ||
                    preg_match('/[^A-Za-z0-9]/', $method)
                ) {
                    throw new Exception('403');
                }

                // Get controller
                if (!file_exists(__DIR__ . "/../app/controllers/$controller.php")) {
                    throw new Exception('404');
                }
                require_once(__DIR__ . "/../app/controllers/$controller.php");

                // Check for invalid controller/methods
                if (!class_exists($controller)) {
                    throw new Exception('403');
                }

                if (!method_exists($controller, $method)) {
                    throw new Exception('404');
                }
            } catch (Exception $e) {
                redirect('/manage/dashboard');
            }
            $args = isset($parts[4]) ? [$parts[4]] : [];
        } else {
            // Sends request to payloads to create a js payload or callback
            $controller = 'Payloads';
            $args = isset($parts[1]) && !empty($parts[1]) ? [$parts[1]] : [];
            $method = $args === [] ? 'index' : 'custom';
            $method = $args === ['callback'] ? 'callback' : $method;

            require_once(__DIR__ . "/../app/controllers/$controller.php");
        }

        // Good to go, send method and args to the correct controller
        $this->controller = new $controller;
        return call_user_func_array([$this->controller, $method], $args);
    }
}

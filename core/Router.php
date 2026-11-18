<?php
class Router
{
    private $controller;
    private $controllerPath;
    private $action;
    private $params = array();

    public function __construct()
    {
        $this->parseRequest();
    }

    /**
     * Parse the incoming request into controller / action / params.
     *
     * @throws HttpException
     */
    private function parseRequest()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? trim($_SERVER['REQUEST_URI'], '/') : '';

        $qpos = strpos($uri, '?');
        if ($qpos !== false) {
            $uri = substr($uri, 0, $qpos);
        }

        $segments = ($uri === '') ? array() : explode('/', $uri);

        foreach ($segments as $seg) {
            if ($seg === '.' || $seg === '..') {
                throw new HttpException(404, 'Not Found');
            }
        }

        if (count($segments) < 2 || strtolower($segments[0]) !== 'api') {
            throw new HttpException(404, 'Not Found');
        }

        $this->controllerPath = API_PATH;
        $rest = array_slice($segments, 1);

        $currentPath = $this->controllerPath;
        $used = 0;
        foreach ($rest as $i => $segment) {
            $candidate = ucfirst($segment);
            if (file_exists("$currentPath/$candidate.php")) {
                $this->controller = $candidate;
                $this->controllerPath = $currentPath;
                $used = $i + 1;
                break;
            } elseif (is_dir("$currentPath/$segment")) {
                $currentPath = "$currentPath/$segment";
                $used = $i + 1;
            } else {
                break;
            }
        }

        if (!$this->controller) {
            throw new HttpException(404, 'Not Found');
        }

        $remaining = array_slice($rest, $used);
        $this->action = isset($remaining[0]) ? $remaining[0] : 'index';
        $this->params = array_slice($remaining, 1);
    }

    /**
     * Route to the resolved controller action.
     *
     * @throws HttpException
     */
    public function route()
    {

        if (preg_match('/[^A-Za-z0-9]/', $this->controller) ||
            preg_match('/[^A-Za-z0-9]/', $this->action)) {
            throw new HttpException(404, 'Not Found');
        }
        foreach ($this->params as $param) {
            if (preg_match('/[^A-Za-z0-9_.,-]/', $param)) {
                throw new HttpException(404, 'Not Found');
            }
        }

        $controllerFile = $this->controllerPath . '/' . $this->controller . '.php';
        if (!file_exists($controllerFile)) {
            throw new HttpException(404, 'Not Found');
        }
        require_once $controllerFile;

        $controllerClass = $this->controller . 'Controller';
        if (!class_exists($controllerClass)) {
            throw new HttpException(404, 'Not Found');
        }

        $instance = new $controllerClass();

        $method = $this->action;
        if (!method_exists($instance, $method)) {
            throw new HttpException(404, 'Not Found');
        }

        $reflection = new ReflectionMethod($instance, $method);
        if (!$reflection->isPublic() || $reflection->isStatic() || $reflection->isAbstract()) {
            throw new HttpException(404, 'Not Found');
        }

        if (!property_exists($controllerClass, 'routable') ||
            !in_array($reflection->getName(), $controllerClass::$routable, true)) {
            throw new HttpException(404, 'Not Found');
        }

        $required = $reflection->getNumberOfRequiredParameters();
        $total = $reflection->getNumberOfParameters();
        $provided = count($this->params);
        if ($provided < $required || $provided > $total) {
            throw new HttpException(404, 'Not Found');
        }

        call_user_func_array(array($instance, $method), $this->params);
    }
}

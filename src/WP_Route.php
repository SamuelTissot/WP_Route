<?php

/**
 * WP_Route
 *
 * A simple class for binding
 * complex routes to functions
 * methods or WP_AJAX actions.
 *
 * @author     Anthony Budd
 */

namespace samueltissot\WP_Route;

final class WP_Route
{
    const PATH_VAR_REGEX = '/\{\s*.+?\s*\}/';
    private static $instance = null;
    private $hooked = false;
    private $routes = array(
        'ANY' => array(),
        'GET' => array(),
        'POST' => array(),
        'HEAD' => array(),
        'PUT' => array(),
        'DELETE' => array(),
    );

    // make sure no one intanciate it
    private function __construct()
    {
    }

    public static function instance()
    {

        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->hook();
        }

        return self::$instance;
    }


    // -----------------------------------------------------
    // CREATE ROUTE METHODS
    // -----------------------------------------------------
    public static function any($route, $callable, $matchParam = false)
    {
        $r = self::instance();
        $r->addRoute('ANY', $route, $callable, $matchParam);
    }

    public static function get($route, $callable, $matchParam = false)
    {
        $r = self::instance();
        $r->addRoute('GET', $route, $callable, $matchParam);
    }

    public static function post($route, $callable, $matchParam = false)
    {
        $r = self::instance();
        $r->addRoute('POST', $route, $callable, $matchParam);
    }

    public static function head($route, $callable, $matchParam = false)
    {
        $r = self::instance();
        $r->addRoute('HEAD', $route, $callable, $matchParam);
    }

    public static function put($route, $callable, $matchParam = false)
    {
        $r = self::instance();
        $r->addRoute('PUT', $route, $callable, $matchParam);
    }

    public static function delete($route, $callable, $matchParam = false)
    {
        $r = self::instance();
        $r->addRoute('DELETE', $route, $callable, $matchParam);
    }

    public static function match($methods, $route, $callable, $matchParam = false)
    {
        if (!is_array($methods)) {
            throw new \Exception("\$methods must be an array");
        }

        $r = self::instance();
        foreach ($methods as $method) {
            if (!in_array(strtoupper($method), array_keys($r->routes))) {
                throw new \Exception("Unknown method {$method}");
            }

            $r->addRoute(strtoupper($method), $route, $callable, $matchParam);
        }
    }

    public static function redirect($route, $redirect, $code = 301, $matchParam = false)
    {
        $r = self::instance();
        $r->addRoute('ANY', $route, $redirect, array(
            'code' => $code,
            'redirect' => $redirect,
            'matchParam' => $matchParam,
        ));
    }


    // -----------------------------------------------------
    // INTERNAL UTILITY METHODS
    // -----------------------------------------------------
    private function addRoute($method, $route, $callable, $matchParam = false)
    {
        $this->routes[$method][] = (object)[
            'route' => ltrim($route, '/'),
            'callable' => $callable,
            'matchParam' => $matchParam,
        ];
    }

    private function hook()
    {
        if (!$this->hooked) {
            add_filter('init', array(__class__, 'onInit'), 1, 0);
            $this->hooked = true;
        }
    }

    public static function onInit()
    {
        $r = self::instance();
        return $r->handle();
    }

    private function getPathVariables($route)
    {
        $tokenizedRoute = $this->tokenize($route);
        $tokenizedRequestURI = $this->tokenize($this->requestURI());
        preg_match_all(self::PATH_VAR_REGEX, $route, $matches);

        $return = [];
        foreach ($matches[0] as $key => $match) {
            $search = array_search($match, $tokenizedRoute);
            if ($search !== false) {
                $n = preg_replace('/\{|\}/', "", $match);
                $return[$n] = filter_var($tokenizedRequestURI[$search], FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_BACKTICK]);
            }
        }

        return $return;
    }


    // -----------------------------------------------------
    // GENERAL UTILITY METHODS
    // -----------------------------------------------------
    public static function routes()
    {
        $r = self::instance();
        return $r->routes;
    }

    public function tokenize($url)
    {
        return array_filter(explode('/', ltrim($url, '/')));
    }


    public function requestURI($withParam = false)
    {
        // TODO maybe add static vars here. but will need to null them with reflection for testing
        $uri = ltrim($_SERVER["REQUEST_URI"], '/');

        if ($withParam) {
            return $uri;
        }

        $pUrl = parse_url($uri);

        if ($pUrl === false) return "";

        return $pUrl['path'] ?? "";
    }

    public function getMethod()
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    private function getRequest($route)
    {
        $params = array_map(
            function ($value) {
                return filter_var($value, FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_BACKTICK]);
            },
            $_GET
        );

        return new Request(
            $this->getMethod(),
            $this->requestURI(),
            $params,
            $this->getPathVariables($route)
        );
    }

    private function isMatch(array $route, array $path)
    {
        $rc = count($route);
        $rp = count($path);

        // if not of same length it's not a match
        if ($rc != $rp) return false;

        // if both count 0 then it's a match
        if ($rc == 0) return true;


        // if it's a path variable then keep going
        if (preg_match(self::PATH_VAR_REGEX, reset($route))) {
            goto ctn;
        }

        // if the strings are not equal it's not a match
        if (reset($route) != reset($path)) return false;


        ctn :
        array_shift($route);
        array_shift($path);
        return $this->isMatch($route, $path);
    }

    public function handle()
    {
        $method = $this->getMethod();
        $routes = array_merge($this->routes[$method], $this->routes['ANY']);

        $route = [];
        foreach ($routes as $r) {
            $uri = $this->requestURI($r->matchParam);
            $turi = $this->tokenize($uri);
            $troute = $this->tokenize($r->route);
            if ($this->isMatch($troute, $turi)) {
                $route = $r;
                break;
            }
        }

        // return if no route found
        if (empty($route)) {
            return null;
        }

        if (isset($route->callable) && is_callable($route->callable)) {
            return call_user_func($route->callable, $this->getRequest($route->route));
        }

        if (isset($routes->redirect)) {
            $redirect = $routes[0]->redirect;
            header("Location: {$redirect}", true, $routes[0]->code);
            die(0);
        }

        throw new \Exception("route not callable");
    }

    public function __destruct()
    {
        self::$instance = null;
    }
}

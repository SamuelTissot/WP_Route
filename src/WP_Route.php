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
    private static $instance = null;
    private $hooked = false;
    private $routes = array(
        'ANY' 		=> array(),
        'GET' 		=> array(),
        'POST' 		=> array(),
        'HEAD' 		=> array(),
        'PUT' 		=> array(),
        'DELETE' 	=> array(),
    );

    // make sure no one intanciate it
    private function __construct()
    {
    }

    public static function instance()
    {

        if (self::$instance === null) {
            self::$instance = new Self();
            self::$instance->hook();
        }

        return self::$instance;
    }


    // -----------------------------------------------------
    // CREATE ROUTE METHODS
    // -----------------------------------------------------
    public static function any($route, $callable, $matchParam = false)
    {
        $r = Self::instance();
        $r->addRoute('ANY', $route, $callable, $matchParam);
    }

    public static function get($route, $callable, $matchParam = false)
    {
        $r = Self::instance();
        $r->addRoute('GET', $route, $callable, $matchParam);
    }

    public static function post($route, $callable, $matchParam = false)
    {
        $r = Self::instance();
        $r->addRoute('POST', $route, $callable, $matchParam);
    }

    public static function head($route, $callable, $matchParam = false)
    {
        $r = Self::instance();
        $r->addRoute('HEAD', $route, $callable, $matchParam);
    }

    public static function put($route, $callable, $matchParam = false)
    {
        $r = Self::instance();
        $r->addRoute('PUT', $route, $callable, $matchParam);
    }

    public static function delete($route, $callable, $matchParam = false)
    {
        $r = Self::instance();
        $r->addRoute('DELETE', $route, $callable, $matchParam);
    }

    public static function match($methods, $route, $callable, $matchParam = false)
    {
        if (!is_array($methods)) {
            throw new Exception("\$methods must be an array");
        }

        $r = Self::instance();
        foreach ($methods as $method) {
            if (!in_array(strtoupper($method), array_keys($this->routes))) {
                throw new Exception("Unknown method {$method}");
            }

            $r->addRoute(strtoupper($method), $route, $callable, $matchPara);
        }
    }

    public static function redirect($route, $redirect, $code = 301, $matchParam = false)
    {
        $r = Self::instance();
        $r->addRoute('ANY', $route, $redirect, array(
            'code'     => $code,
            'redirect' => $redirect,
            'matchParam' => $matchParam,
        ));
    }


    // -----------------------------------------------------
    // INTERNAL UTILITY METHODS
    // -----------------------------------------------------
    private function addRoute($method, $route, $callable, $matchParam = false)
    {
        $this->routes[$method][] = (object) [
            'route' 	=>  ltrim($route, '/'),
            'callable'  =>  $callable,
            'matchParam' => $matchParam,
        ];
    }

    private function hook()
    {
        if (!$this->hooked) {
            add_filter('init', array(__CLASS__, 'onInit'), 1, 0);
            $this->hooked = true;
        }
    }

    public static function onInit()
    {
        $r = Self::instance();
        return $r->handle();
    }

    private function getPathVariables($route)
    {
        $tokenizedRoute		 = $this->tokenize($route);
        $tokenizedRequestURI = $this->tokenize($this->requestURI());
        preg_match_all('/\{\s*.+?\s*\}/', $route, $matches);

        $return = array();
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
        $r = Self::instance();
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
        
	return parse_url($uri)['path'];
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

    public function handle()
    {
        $method = $this->getMethod();
        $routes = array_merge($this->routes[$method], $this->routes['ANY']);

        foreach ($routes as $key => $route) {
            $requestURI = $this->requestURI($route->matchParam);
            $tokenizedRequestURI = $this->tokenize($requestURI);
            if (count($this->tokenize($route->route)) !== count($tokenizedRequestURI)) {
                unset($routes[$key]);
                continue;
            }
        }

        $routes = array_values($routes);

        // return if no route found
        if (!isset($routes[0])) {
            return;
        }

        // use the first match
        $route = $routes[0];

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

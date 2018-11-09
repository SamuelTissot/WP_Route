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

/**
 * Class WP_Route
 * @package samueltissot\WP_Route
 */
final class WP_Route
{
    const PATH_VAR_REGEX = '/\{\s*.+?\s*\}/';
    private static $rParams = null;

    private static $instance = null;
    private $hooked = false;
    private $args = [
        "parameters" => [
            "match" => [],
            "no_match" => [],
        ]
    ];
    private $routes = array(
        'ANY' => array(),
        'GET' => array(),
        'POST' => array(),
        'HEAD' => array(),
        'PUT' => array(),
        'DELETE' => array(),
    );

    // make sure no one instantiate it
    private function __construct()
    {
    }

    /**
     * return the WP_Route singleton instance
     * @return WP_Route|null
     */
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

    /**
     * Register a route for any method, GET, POST, HEAD ....
     * @param $route
     * @param $callable
     * @param array $args
     */
    public static function any($route, $callable, $args = [])
    {
        $r = self::instance();
        $r->addRoute('ANY', $route, $callable, $args);
    }

    /**
     * Register a route for the GET method
     * @param $route
     * @param $callable
     * @param array $args
     */
    public static function get($route, $callable, $args = [])
    {
        $r = self::instance();
        $r->addRoute('GET', $route, $callable, $args);
    }

    /**
     * Register a route for POST method
     * @param $route
     * @param $callable
     * @param array $args
     */
    public static function post($route, $callable, $args = [])
    {
        $r = self::instance();
        $r->addRoute('POST', $route, $callable, $args);
    }

    /**
     * Register a route for HEAD method
     * @param $route
     * @param $callable
     * @param array $args
     */
    public static function head($route, $callable, $args = [])
    {
        $r = self::instance();
        $r->addRoute('HEAD', $route, $callable, $args);
    }

    /**
     * Register a route for PUT method
     * @param $route
     * @param $callable
     * @param array $args
     */
    public static function put($route, $callable, $args = [])
    {
        $r = self::instance();
        $r->addRoute('PUT', $route, $callable, $args);
    }

    /**
     * Register a route for DELETE method
     * @param $route
     * @param $callable
     * @param array $args
     */
    public static function delete($route, $callable, $args = [])
    {
        $r = self::instance();
        $r->addRoute('DELETE', $route, $callable, $args);
    }

    /**
     * Register a route for [METHOD-1, METHOD-2, ...] method
     * @param $methods
     * @param $route
     * @param $callable
     * @param array $args
     * @throws \Exception
     */
    public static function match($methods, $route, $callable, $args = [])
    {
        if (!is_array($methods)) {
            throw new \Exception("\$methods must be an array");
        }

        $r = self::instance();
        foreach ($methods as $method) {
            if (!in_array(strtoupper($method), array_keys($r->routes))) {
                throw new \Exception("Unknown method {$method}");
            }

            $r->addRoute(strtoupper($method), $route, $callable, $args);
        }
    }

    /**
     * Register a redirect for the url
     * @param $route
     * @param $redirect
     * @param int $code
     * @param array $args
     */
    public static function redirect($route, $redirect, $code = 301, $args = [])
    {
        $r = self::instance();
        $r->addRoute('ANY', $route, $redirect, array(
            'code' => $code,
            'redirect' => $redirect,
            'args' => $args,
        ));
    }

    // -----------------------------------------------------
    // GENERAL UTILITY METHODS
    // -----------------------------------------------------

    /**
     * Returns an array of all currently registers routes
     * @return array
     */
    public static function routes()
    {
        $r = self::instance();
        return $r->routes;
    }

    /**
     * Return the path of the current URI
     * @return string
     */
    public function requestURI()
    {
        // TODO maybe add static vars here. but will need to null them with reflection for testing
        $uri = ltrim($_SERVER["REQUEST_URI"], '/');

        $pUrl = parse_url($uri);

        if ($pUrl === false) return "";

        return $pUrl['path'] ?? "";
    }

    /**
     * return the method of the current request
     * @return string
     */
    public function getMethod()
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @param array $args
     */
    public function setArgs(array $args)
    {
        $this->args = $args;
    }


    // -----------------------------------------------------
    // INTERNAL METHODS
    // -----------------------------------------------------

    /**
     * needs to be public to work with the wp-action
     * this function should not be called
     * @return mixed|null
     * @throws \Exception
     */
    public static function onInit()
    {
        $r = self::instance();
        return $r->handle();
    }

    private function addRoute($method, $route, $callable, $args = [])
    {
        $this->routes[$method][] = (object)[
            'route' => ltrim($route, '/'),
            'callable' => $callable,
            'args' => array_merge($this->args, $args),
        ];
    }

    private function hook()
    {
        if (!$this->hooked) {
            add_filter('init', array(__class__, 'onInit'), 1, 0);
            $this->hooked = true;
        }
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

    private function getRequest($route)
    {
        $params = $this->getParams();

        return new Request(
            $this->getMethod(),
            $this->requestURI(),
            $params,
            $this->getPathVariables($route)
        );
    }

    private function handle()
    {
        $method = $this->getMethod();
        $routes = array_merge($this->routes[$method], $this->routes['ANY']);

        $route = null;
        $uri = $this->requestURI();
        foreach ($routes as $r) {
            if ($this->isMatch($r, $uri)) {
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


    private function isMatch($r, $uri)
    {
        // first check if the path match, if not return
        $turi = $this->tokenize($uri);
        $troute = $this->tokenize($r->route);
        $match = $this->matchPath($troute, $turi);
        if (!$match) return $match;

        // match params
        return $this->matchParams($r);
    }

    private function matchPath(array $route, array $path)
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
        return $this->matchPath($route, $path);
    }

    private function tokenize($url)
    {
        return array_filter(explode('/', ltrim($url, '/')));
    }

    private function matchParams($r)
    {
        $p = $this->getParams();
        $args = $r->args['parameters'];

        if (isset($args['match']) && !empty($args['match'])) {
            if (is_array($args['match'])) {
                foreach ($args['match'] as $key) {
                    if (!array_key_exists($key, $p)) {
                        return false;
                    }
                }
            } elseif (!array_key_exists($args['match'], $p)) {
                return false;
            }

        }

        if (isset($args['no_match']) && !empty($args['no_match'])) {
            if (is_array($args['no_match'])) {
                foreach ($args['no_match'] as $key) {
                    if (array_key_exists($key, $p)) {
                        return false;
                    }
                }
            } else {
                return !array_key_exists($args['no_match'], $p);
            }

        }

        return true;
    }

    private function getParams()
    {

        if (!isset(self::$rParams)) {
            self::$rParams = array_map(
                function ($value) {
                    return filter_var($value, FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_BACKTICK]);
                },
                $_GET
            );
        }

        return self::$rParams;
    }

    public function __destruct()
    {
        self::$rParams = null;
        self::$instance = null;
    }
}

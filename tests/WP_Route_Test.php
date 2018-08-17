<?php

namespace {
    global $filters;
    $filters;

    function add_filter(string $tag, $function_to_add, int $priority = 10, int $accepted_args = 1)
    {
        global $filters;
        if (!is_callable($function_to_add)) {
            throw new \Exception("function not callable");
        }
        $filters = $function_to_add;

    }

    function exec_filter()
    {
        global $filters;
        return call_user_func($filters);
    }

} namespace samueltissot\WP_Route_Tests {

    use PHPUnit\Framework\TestCase;
    use samueltissot\WP_Route\WP_Route;
    use samueltissot\WP_Route\RequestInterface;

    class WP_Route_Test extends TestCase
    {

        public function tearDown()
        {
            WP_Route::instance()->__destruct();
        }

        /** @test */
        public function initiate_WP_Route()
        {
            /* global $_SERVER; */
            $_SERVER["REQUEST_URI"] = "/flight/";
            $_SERVER["REQUEST_METHOD"] = "get";

            WP_Route::get("/flight/", function(RequestInterface $request) {
                return "hello";
            });

            $result = \exec_filter();
            $this->assertEquals("hello", $result);
        }

        /** @test */
        public function has_path_variable()
        {
            $_SERVER["REQUEST_URI"] = "/flight/high";
            $_SERVER["REQUEST_METHOD"] = "get";

            WP_Route::get("/flight/{altitude}", function(RequestInterface $request) {
                return $request->pathVariable("altitude");
            });

            $result = \exec_filter();
            $this->assertEquals("high", $result);
        }

        /** @test */
        public function has_parameter()
        {
            $_SERVER["REQUEST_URI"] = "/param/?foo=bar";
            $_GET = [
                'foo' => 'bar',
            ];
            $_SERVER["REQUEST_METHOD"] = "get";

            WP_Route::get("/param/", function(RequestInterface $request) {
                return $request->parameter("foo");
            });

            $result = \exec_filter();
            $this->assertEquals("bar", $result);
        }

        /** @test */
        public function must_select_the_correct_route()
        {
            $_SERVER["REQUEST_URI"] = "/correct/yeah/";
            $_SERVER["REQUEST_METHOD"] = "get";

            WP_Route::get("/bad/{foo}", function(RequestInterface $request) {
                return "bad"; 
            });
            WP_Route::get("/correct/{foo}", function(RequestInterface $request) {
                return $request->pathVariable("foo");
            });

            WP_Route::get("/notgood/{foo}", function(RequestInterface $request) {
                return "not good at all";
            });

            $result = \exec_filter();
            $this->assertEquals("yeah", $result);
        }
    }
}

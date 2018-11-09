<?php

namespace {
    global $filters;
    $filters = null;

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

}

namespace samueltissot\WP_Route_Tests {

    use PHPUnit\Framework\TestCase;
    use samueltissot\WP_Route\RequestInterface;
    use samueltissot\WP_Route\WP_Route;

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

            WP_Route::get("/flight/", function (RequestInterface $request) {
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

            WP_Route::get("/flight/{altitude}", function (RequestInterface $request) {
                return $request->pathVariable("altitude");
            });

            $result = \exec_filter();
            $this->assertEquals("high", $result);
        }

        /** @test */
        public function request_object_has_parameter()
        {
            $_GET = [
                'foo' => 'bar',
            ];
            $_SERVER["REQUEST_URI"] = "/param/?" . http_build_query($_GET, '&');
            $_SERVER["REQUEST_METHOD"] = "get";

            WP_Route::get("/param/", function (RequestInterface $request) {
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

            WP_Route::get("/bad/{foo}", function (RequestInterface $request) {
                return "bad";
            });
            WP_Route::get("/correct/{foo}", function (RequestInterface $request) {
                return $request->pathVariable("foo");
            });

            WP_Route::get("/notgood/{foo}", function (RequestInterface $request) {
                return "not good at all";
            });

            $result = \exec_filter();
            $this->assertEquals("yeah", $result);
        }


        // -----------------------------------------------------
        // TEST WITH Params
        // -----------------------------------------------------

        /** @test */
        public function no_match_on_parameter()
        {
            $_GET = [
                'foo' => 'bar',
            ];
            $_SERVER["REQUEST_URI"] = "/param/?" . http_build_query($_GET, '&');
            $_SERVER["REQUEST_METHOD"] = "get";

            $args = [
                'parameters' => [
                    'no_match' => 'foo',
                ]
            ];

            WP_Route::get("/param/", function (RequestInterface $request) {
                return $request->parameter("foo");
            }, $args);

            $result = \exec_filter();
            $this->assertNotEquals("bar", $result);
        }

        /** @test */
        public function match_if_no_match_parameter_is_not_present()
        {
            $_GET = [
                'box' => 'bee',
            ];
            $_SERVER["REQUEST_URI"] = "/param/?" . http_build_query($_GET, '&');
            $_SERVER["REQUEST_METHOD"] = "get";

            $args = [
                'parameters' => [
                    'no_match' => 'foo',
                ]
            ];

            WP_Route::get("/param/", function (RequestInterface $request) {
                return $request->parameter("box");
            }, $args);

            $result = \exec_filter();
            $this->assertEquals("bee", $result);
        }

        /** @test */
        public function must_require_param_to_match()
        {
            $_GET = [
                'foo' => 'bar',
            ];
            $_SERVER["REQUEST_URI"] = "/param/?" . http_build_query($_GET, '&');
            $_SERVER["REQUEST_METHOD"] = "get";

            $args = [
                'parameters' => [
                    'match' => 'foo',
                ]
            ];

            WP_Route::get("/param/", function (RequestInterface $request) {
                return $request->parameter("foo");
            }, $args);

            $result = \exec_filter();
            $this->assertEquals("bar", $result);
        }

        /** @test */
        public function if_param_is_not_present_do_not_match()
        {
            $_GET = [
                'bar' => 'foo',
            ];
            $_SERVER["REQUEST_URI"] = "/param/?" . http_build_query($_GET, '&');
            $_SERVER["REQUEST_METHOD"] = "get";

            $args = [
                'parameters' => [
                    'match' => 'foo',
                ]
            ];

            WP_Route::get("/param/", function (RequestInterface $request) {
                return $request->parameter("foo");
            }, $args);

            $result = \exec_filter();
            $this->assertNotEquals("bar", $result);
        }

        /** @test */
        public function match_param_present_but_a_no_match_also__dont_match()
        {
            $_GET = [
                'bar' => 'foo',
                'disco' => 'queen',
            ];
            $_SERVER["REQUEST_URI"] = "/param/?" . http_build_query($_GET, '&');
            $_SERVER["REQUEST_METHOD"] = "get";

            $args = [
                'parameters' => [
                    'match' => ['foo'],
                    'no_match' => ['disco'],
                ]
            ];

            WP_Route::get("/param/", function (RequestInterface $request) {
                return $request->parameter("bar");
            }, $args);

            $result = \exec_filter();
            $this->assertNotEquals("foo", $result);
        }

    }
}

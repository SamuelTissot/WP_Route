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
            $_SERVER["REQUEST_URI"] = "/param/?foo=bar";
            $_GET = [
                'foo' => 'bar',
            ];
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

        /** @test */
        public function match_all_param()
        {
            $_SERVER["REQUEST_URI"] = "/param/?foo=bar&match=this";
            $_GET = [
                'foo' => 'bar',
                'm' => 'this',
            ];
            $_SERVER["REQUEST_METHOD"] = "get";

            WP_Route::get("/param/", function (RequestInterface $request) {
                return $request->parameter("m");
            }, ['match' => []]);

            $result = \exec_filter();
            $this->assertEquals("this", $result);
        }

        /** @test */
        public function no_matching_param()
        {
            $_SERVER["REQUEST_URI"] = "/param/?foo=bar";
            $_GET = [
                'foo' => 'bar',
            ];
            $_SERVER["REQUEST_METHOD"] = "get";

            WP_Route::get("/param/", function (RequestInterface $request) {
                return "hello";
            }, ['match' => [
                'm',
            ]]);

            $result = \exec_filter();
            $this->assertNotEquals("hello", $result);
        }

        /** @test */
        public function do_not_match_with_extra_params()
        {
            $_SERVER["REQUEST_URI"] = "/param/?foo=bar&match=this";
            $_GET = [
                'foo' => 'bar',
            ];
            $_SERVER["REQUEST_METHOD"] = "get";

            WP_Route::get("/param/", function (RequestInterface $request) {
                return $request->parameter("foo");
            }, ['do_not_match' => [
                'foo'
            ]]);

            $result = \exec_filter();
            $this->assertNotEquals("bar", $result);
        }

        /** @test */
        public function match_on_one_param_but_not_on_another()
        {
            $_SERVER["REQUEST_URI"] = "/param/?foo=bar&zoo=lion";
            $_GET = [
                'preview' => 'test',
                'zoo' => 'lion'
            ];
            $_SERVER["REQUEST_METHOD"] = "get";

            WP_Route::get("/param/", function (RequestInterface $request) {
                return $request->parameter("foo");
            }, [
                'match' => [
                    'preview'
                ],
                'do_not_match' => [
                    'zoo'
                ]
            ]);

            $result = \exec_filter();
            $this->assertNotEquals("bar", $result);
        }

        /** @test */
        public function honnor_global_args()
        {
            $route = WP_Route::instance();
            $route->setGlobalArgs([
                'do_not_match' => ['q'],
            ]);

            $_SERVER["REQUEST_URI"] = "/param/?foo=bar&q=page";
            $_GET = [
                'foo' => 'bar',
                'q' => 'page'
            ];
            $_SERVER["REQUEST_METHOD"] = "get";

            WP_Route::get("/param/", function (RequestInterface $request) {
                return $request->parameter("foo");
            });

            $result = \exec_filter();
            $this->assertNotEquals("bar", $result);
        }

        /** @test */
        public function do_not_match_all()
        {
            $_SERVER["REQUEST_URI"] = "/param/?foo=bar&q=page";
            $_GET = [
                'foo' => 'bar',
                'q' => 'page'
            ];
            $_SERVER["REQUEST_METHOD"] = "get";

            WP_Route::get("/param/", function (RequestInterface $request) {
                return $request->parameter("foo");
            }, ['do_not_match' => []]);

            $result = \exec_filter();
            $this->assertNotEquals("bar", $result);
        }
    }

}

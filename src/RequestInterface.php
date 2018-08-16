<?php

namespace samueltissot\WP_Route;

interface RequestInterface
{
    public function uri();

    public function method();

    public function pathVariables();

    public function pathVariable($name);

    public function parameters();

    public function parameter($name);
}

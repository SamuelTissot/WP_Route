<?php

namespace samueltissot\WP_Route;

class Request implements RequestInterface
{
    private $method = '';
    private $uri = '';
    private $pathVariables = [];
    private $params = [];

    public function __construct(
        string $method,
        string $uri,
        array $params,
        array $pathVariables
    )
    {
        $this->uri = $uri;
        $this->method = $method;
        $this->params = $params;
        $this->pathVariables = $pathVariables;
    }


    public function uri()
    {
        return $this->uri; 
    }

    public function method()
    {
        return $this->method;
    }

    public function pathVariable($name)
    {
        if (isset($this->pathVariables[$name])) {
            return $this->pathVariables[$name];
        }

        return "";
    }

    public function pathVariables()
    {
        return $this->pathVariables;
    }

    public function parameter($name)
    {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        }

        return "";
    }

    public function parameters()
    {
        return $this->params;
    }
}

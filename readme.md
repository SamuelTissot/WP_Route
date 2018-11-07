WP_Route
====

[![CircleCI](https://circleci.com/gh/SamuelTissot/WP_Route/tree/master.svg?style=svg)](https://circleci.com/gh/SamuelTissot/WP_Route/tree/master)

This is a fork of [Anthony Budd's](https://github.com/anthonybudd) [WP_Route](https://github.com/anthonybudd/WP_Route)

### Added features 
- small security fix
- Named Paths Variable
- renamed parameter to PathVariable
- pass an Request object to the callable function
- option to ignore query string


### basic usage
```php

WP_Route::get('/flights/', 'listFlights');
WP_Route::get('/buses/', 'listBuses');
WP_Route::post('/flights/{flight}/', 'singleFlight');
WP_Route::put('/flights/{flight}/book/{date}', 'bookFlight');
WP_Route::delete('/flights/{flight}/delete', 'deleteFlight');
WP_Route::any('flights/{flight}',   array('Class', 'staticMethod'));
WP_Route::patch('flights/{flight}', array($object, 'method'));
WP_Route::match(['get', 'post'],    'flights/{flight}/confirm', 'confirmFlight');

// if you want to take into account the parameters when doing a path match 
WP_Route::get('/flights/', 'listFlights', ["match" => "*"]);

// if you want to match one or more parameters
WP_Route::get('/flights/', 'listFlights', ["match" => ['param2', 'param2', ...]]);

// redirect
WP_Route::redirect('open-google', 'https://google.com', 301);

// close
WP_Route::get('flights/{flight}', function singleFlight(RequestInterface $req) {
    $req->pathVariable('flight');
}

```

#### the arguments list
```php

[
   'match' => [ [, parameters [, parameter2] ... ]],
   'do_not_match' => [ [, parameter, [parameter2] ... ]],
]
```
**NOTE:** an empty array means : apply to all

# Installation

Require WP_Route with composer

```
$ composer require samueltissot/wp_route
```


# All Callback must accept a variable of type `RequestInterface`
a request object is passed to the callable method

**note:** it is in the plans to be able to provide your custom RequestInterface class (PR accepted)

### examples
```php

use samueltissot\WP_Route\RequestInterface;

// an invocable class
class Controller
{
    public function __invoke(RequestInterface $req)
    {
        // code goes here;
    }
}


// or a simple function
function my_super_func(RequestInterface $req) {
    // code goes here;
}

// method inside class

class MyAwesomeClass
{
    public function wow(RequestInterface $req)
    {
        // code goes here;
    }
}

```


# The Request Object (RequestInterface)
a small helper object that provide usefull data about the request

### the Interface
```php
interface RequestInterface
{
    public function uri();

    public function method();

    public function pathVariables();

    public function pathVariable($name);

    public function parameters();

    public function parameter($name);
}
```

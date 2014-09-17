[![Build Status](https://travis-ci.org/alixaxel/curly.svg?branch=master)](https://travis-ci.org/alixaxel/curly/)

#curly

Parallel cURL Wrapper for PHP

##Requirements

- PHP 5.4+
- cURL Extension

##Installation (via Composer)

Add the following dependency in your composer.json file:

```json
{
	"require": {
		"alixaxel/curly": "*"
	}
}
```

And then just run `composer install` or `composer update`.

##Usage (Single Requests)

```php
<?php

use alixaxel\curly\CURL;

$url = 'http://httpbin.org/post',
$data = [
	'foo' => sprintf('@', __FILE__),
	'bar' => 'baz',
];

var_dump(CURL::Uni($url, $data, 'POST'));
```

##Usage (Multiple Requests with Callback)

```php
<?php

use alixaxel\curly\CURL;

$url = 'http://httpbin.org/post',
$data = [
	'foo' => sprintf('@', __FILE__),
	'bar' => 'baz',
];

$handles = [];

for ($i = 0; $i < 16; ++$i)
{
	$handles[$id = uniqid()] = CURL::Uni($url, $data, 'POST', null, null, 0);
}

$parallel = 4; // number of requests to make in parallel
$throttle = 1; // wait at least 1 second per each $parallel requests

$handles = CURL::Multi($handles, function ($response, $info, $id) {
	var_dump($id, $response);
}, $parallel, $throttle);

print_r($handles);
```

##Changelog

- **0.1.0** ~~initial release~~
- **0.2.0** ~~added XPathify() utility method~~

##Credits

XPathify() is based on [visionmedia/php-selector](https://github.com/visionmedia/php-selector/).

##License (MIT)

Copyright (c) 2014 Alix Axel (alix.axel+github@gmail.com).

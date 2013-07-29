<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/libs/HttpPHPUnit/init.php';

$http = new HttpPHPUnit;

require_once __DIR__ . '/boot.php';

$http->coverage(__DIR__ . '/../Migration', __DIR__ . '/coverage');

$http->run(__DIR__ . '/cases');

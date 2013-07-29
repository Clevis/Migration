<?php

require_once __DIR__ . '/../vendor/dump.php';
require_once __DIR__ . '/../vendor/Nette/loader.php';
require_once __DIR__ . '/libs/Access/Init.php';

define('TEMP_DIR', __DIR__ . '/tmp');

use Nette\Loaders\RobotLoader;
use Nette\Caching\Storages\FileStorage;

Nette\Diagnostics\Debugger::enable(false);

$r = new RobotLoader;
$r->setCacheStorage(new FileStorage(TEMP_DIR));
$r->addDirectory(__DIR__ . '/../Migration');
$r->addDirectory(__DIR__ . '/../vendor');
$r->addDirectory(__DIR__ . '/libs');
$r->addDirectory(__DIR__ . '/inc');
$r->addDirectory(__DIR__ . '/cases');
$r->register();

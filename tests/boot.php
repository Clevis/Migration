<?php
define('TEMP_DIR', __DIR__ . '/tmp');

use Nette\Loaders\RobotLoader;
use Nette\Caching\Storages\FileStorage;

Nette\Diagnostics\Debugger::enable(false);

$r = new RobotLoader;
$r->setCacheStorage(new FileStorage(TEMP_DIR));
$r->addDirectory(__DIR__ . '/../Migration');
$r->addDirectory(__DIR__ . '/libs');
$r->addDirectory(__DIR__ . '/inc');
$r->addDirectory(__DIR__ . '/cases');
$r->register();

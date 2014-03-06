<?php

use Migration\Extensions\OrmPhp;
use Migration\Extensions\DibiPhp;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/Configurator.php';

set_time_limit(0);
ini_set('memory_limit', '1G');

$configurator = new App\Configurator;
$configurator->enableDebugger();
$configurator->createRobotLoader()->register();

$context = $configurator->createContainer();
$reset = php_sapi_name() === "cli" ? in_array('reset', $argv) : isset($_GET['reset']);
if ($reset AND $context->parameters['productionMode'])
{
	throw new Exception('Reset není povolen na produkčním prostředí.');
}

$runner = new Migration\Runner($dibi = $context->getService('dibiConnection'));
$runner->addExtension(new DibiPhp($dibi));
$runner->addExtension(new OrmPhp($configurator, $context, $dibi));

$finder = new Migration\Finders\MultipleDirectories;
$finder->addDirectory(__DIR__ . '/struct');
if (isset($_GET['data']))
{
	$finder->addDirectory(__DIR__ . '/data');
}

$runner->run($finder, $reset);

<?php

/**
 * Context aplikace.
 * @var Nette\DI\Container
 * <pre>
 * 	$productionMode = $context->params['productionMode']
 * </pre>
 */
$productionMode;

/**
 * Pripojeni na databazi.
 * @var DibiConnection
 */
$dibi;

/**
 * Slozka kde se nachazi soubory z migraci.
 * @var string
 */
$directory = __DIR__;

/**
 * Vymaze celou db a spusti vsechny migrace znovu.
 * @var bool
 * `/run.php`
 * `/run.php?reset`
 */
$reset = isset($_GET['reset']);


// Jsou ruzne moznosti jak lze napojit na applikaci:

/* Pouzit vlastni konfigurator:
require_once __DIR__ . '/../libs/Nette/loader.php';
require_once __DIR__ . '/../app/Configurator.php';
$configurator = new MyApp/Configurator;
$context = $configurator->getContainer();
$dibi = $context->dibiConnection;
$productionMode = $context->params['productionMode'];
 */

/* Includovat boostrap a z neho pouzit $context
require_once __DIR__ . '/../index.php';
$dibi = new DibiConnection($context->params['database']);
$productionMode = $context->params['productionMode'];
 */

/* Includovat boostrap a pouzit Environment:
require_once __DIR__ . '/../index.php';
$dibi = dibi::getConnection();
$productionMode = Nette\Environment::isProduction();
 */

/* Nebo si vse nastavit rucne: */
require_once __DIR__ . '/../libs/Nette/loader.php';
Nette\Diagnostics\Debugger::enable();
$context = Nette\Environment::getContext();
$context->params['tempDir'] = __DIR__ . '/../tests/tmp';
$r = $context->robotLoader;
$r->addDirectory(__DIR__ . '/../Migration');
$r->addDirectory(__DIR__ . '/../libs');
$r->register();
$dibi = new DibiConnection(array(
	'username' => 'root',
	'database' => 'migration_example',
));
$productionMode = $context->params['productionMode'];
/**/





set_time_limit(0);
ini_set('memory_limit', '1G');

if ($reset AND $productionMode)
{
	throw new Exception('Reset neni povolen na produkcnim prostredi.');
}
$runner = new Migration\Runner($dibi);
$runner->run($directory, $reset);

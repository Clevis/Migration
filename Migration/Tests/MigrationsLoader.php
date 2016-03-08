<?php

namespace Clevis\Migration\Tests;

use Nette\DI;
use Nette\Object;
use Migration;


/**
 * Vytvoří DB a naplní testovacími daty.
 */
class MigrationsLoader extends Object
{

	/** @var DI\Container */
	private $context;


	public function __construct(DI\Container $context)
	{
		$this->context = $context;
	}

	/**
	 * Vytvoří DB a naplní testovacími daty.
	 */
	public function runMigrations()
	{
		if (!$this->context->parameters['migrations']['enabled']) return;

		$connection = $this->context->getService('dibiConnection');
		$dbNamePrefix = $this->context->parameters['testDbPrefix'] . date('Ymd_His') . '_' . rand(1, 1000) . '_';
		$i = 1;
		do
		{
			$dbName = $dbNamePrefix . $i;
			$i++;
		}
		while (
		$connection->query('SHOW DATABASES WHERE %n', 'Database', ' = %s', $dbName)
			->count()
		);
		$connection->query('CREATE DATABASE %n COLLATE=utf8_czech_ci', $dbName);
		$connection->query('USE %n', $dbName);

		$migrationsPath = $this->context->parameters['wwwDir'] . '/' . $this->context->parameters['migrations']['path'];
		$finder = new Migration\Finders\MultipleDirectories;
		$finder->addDirectory($migrationsPath . '/struct');
		$finder->addDirectory($migrationsPath . '/data');

		$migrations = $this->createRunner($connection);
		ob_start();
		$migrations->run($finder, FALSE, TRUE);
		$result = ob_get_clean();
		if (substr(strip_tags($result), -2) !== 'OK')
		{
			throw new \Exception('Migrace neproběhly v pořádku: ' . $result);
		}
		$this->context->parameters['testDbName'] = $dbName;
	}

	/**
	 * @param $connection
	 * @return Migration\Runner
	 */
	protected function createRunner($connection)
	{
		return new Migration\Runner($connection, new Migration\Printers\HtmlDump);
	}

}

<?php

namespace Clevis\Migration\Tests;

use Nette\Object;
use SystemContainer;
use Migration;


/**
 * Vytvoří DB a naplní testovacími daty.
 */
class MigrationsLoader extends Object
{

	/** @var SystemContainer */
	private $context;


	public function __construct(SystemContainer $context)
	{
		$this->context = $context;
	}

	/**
	 * Vytvoří DB a naplní testovacími daty.
	 */
	public function runMigrations()
	{
		if (!$this->context->parameters['migrations']['enabled']) return;

		$connection = $this->context->dibiConnection;
		$dbNamePrefix = $this->context->parameters['testDbPrefix'] . date('Ymd_His') . '_';
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
		$connection->query('CREATE DATABASE %n', $dbName);
		$connection->query('USE %n', $dbName);

		$migrationsPath = $this->context->parameters['wwwDir'] . '/' . $this->context->parameters['migrations']['path'];
		$finder = new Migration\Finders\MultipleDirectories;
		$finder->addDirectory($migrationsPath . '/struct');
		$finder->addDirectory($migrationsPath . '/data');

		$migrations = new Migration\Runner($connection);
		ob_start();
		$migrations->run($finder, FALSE, TRUE);
		$result = ob_get_clean();
		if (substr(strip_tags($result), -2) !== 'OK')
		{
			throw new \Exception('Migrace neproběhly v pořádku: ' . $result);
		}
		$this->context->parameters['testDbName'] = $dbName;
	}

}

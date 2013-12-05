<?php
namespace Migrations\DI;

use Pimple;


class Container extends Pimple
{

	public function __construct(\DibiConnection $dibi, $env, array $groups, array $extensionHandlers)
	{
		parent::__construct();

		$this['dibi'] = $dibi;
		$this['environment'] = $env;
		$this['groups'] = $groups;
		$this['extensionHandlers'] = $extensionHandlers;

		$this->setDefaultParameters();
		$this->setDefaultServices();
	}

	/**
	 * @return \Migrations\IController
	 */
	public function getController()
	{
		return $this['controller'];
	}

	protected function setDefaultParameters()
	{
		$this['recordsTableName'] = 'migrations';
		$this['lockTableName'] = 'migrations_lock';
	}

	protected function setDefaultServices()
	{
		$this['dbHelpers'] = function (self $c) {
			return new \Migrations\Engine\DatabaseHelpers($c['dibi'], $c['lockTableName']);
		};

		$this['recordsTable'] = function (self $c) {
			return new \Migrations\Engine\MigrationsTable($c['dibi'], $c['recordsTableName']);
		};

		$this['finder'] = function (self $c) {
			return new \Migrations\Engine\Finder();
		};

		$this['scheduler'] = function (self $c) {
			return new \Migrations\Engine\Scheduler($c['recordsTable'], $c['finder'], $c['groups'], $c['extensionHandlers']);
		};

		$this['runner'] = function (self $c) {
			return new \Migrations\Engine\Runner($c['dbHelpers'], $c['recordsTable'], $c['scheduler']);
		};

		$this['consoleController'] = function (self $c) {
			return new \Migrations\Controllers\ConsoleController($c['runner'], $c['groups']);
		};

		$this['httpController'] = function (self $c) {
			return new \Migrations\Controllers\HttpController($c['runner'], $c['groups']);
		};

		$this['controller'] = function (self $c) {
			switch ($c['environment'])
			{
				case 'console':
					return $c['consoleController'];
				case 'http':
					return $c['httpController'];
				default:
					throw new \Migrations\Exceptions\LogicException(sprintf('Unknown environment "%s".', $c['environment']));
			}
		};
	}

}

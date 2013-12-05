<?php
namespace Migrations\Controllers;

use Migrations\Engine;
use Migrations\Entities\Migration;
use Migrations\Exceptions\ImpossibleStateException;
use Migrations\IController;


abstract class BaseController implements IController
{

	/** @var Engine\Runner */
	protected $runner;

	/** @var string */
	protected $mode;

	/** @var array (name => Group) */
	protected $groups;

	/** @var string[] */
	protected $enabledGroups;

	/** @var int */
	protected $migrationsCount;

	/** @var int */
	protected $currentMigrationOrder;

	/** @var Migration */
	protected $currentMigration;

	/** @var int */
	protected $maxNameLength;

	protected function __construct(Engine\Runner $runner, array $groups)
	{
		$this->runner = $runner;
		$this->mode = Engine\Scheduler::MODE_CONTINUE;
		$this->groups = $groups;
		$this->enabledGroups = array();
		$this->initRunnerEvents();
	}

	protected function startRunner()
	{
		$this->runner->run($this->enabledGroups, $this->mode);
	}

	protected function setupPhp()
	{
		@set_time_limit(0);
		@ini_set('memory_limit', '1G');
	}

	protected function getGroupsCombinations()
	{
		$groups = array();
		$index = 1;
		foreach ($this->groups as $group)
		{
			$groups[$index] = $group;
			$index = ($index << 1);
		}

		$combinations = array();
		for ($i = 1; true; $i++)
		{
			$combination = array();
			foreach ($groups as $key => $group)
			{
				if ($i & $key) $combination[] = $group->name;
			}
			if (empty($combination)) break;
			foreach ($combination as $groupName)
			{
				foreach ($this->groups[$groupName]->dependencies as $dependency)
				{
					if (!in_array($dependency, $combination)) continue 3;
				}
			}
			$combinations[] = $combination;
		}
		return $combinations;
	}

	private function initRunnerEvents()
	{
		$that = $this;
		$this->runner->onScheduleReady[] = function (Engine\Runner $runner, array $migrations) use ($that) {
			$that->migrationsCount = count($migrations);
			$that->currentMigrationOrder = 0;
		};

		$this->runner->onBeforeMigrationExecuted[] = function (Engine\Runner $runner, Migration $migration) use ($that) {
			if ($that->currentMigration !== NULL) throw new ImpossibleStateException();
			$that->currentMigration = $migration;
			$that->currentMigrationOrder++;
		};

		$this->runner->onAfterMigrationExecuted[] = function (Engine\Runner $runner, Migration $migration) use ($that) {
			if ($that->currentMigration !== $migration) throw new ImpossibleStateException();
			$that->currentMigration = NULL;
		};
	}

}

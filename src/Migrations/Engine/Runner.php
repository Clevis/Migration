<?php
namespace Migrations\Engine;

use Migrations\Entities\Migration;
use Migrations\Exceptions\ExecutionException;
use Migrations\Exceptions\LockException;
use Migrations\Exceptions\LogicException;


class Runner
{

	/** @var DatabaseHelpers */
	private $dbHelper;

	/** @var MigrationsTable */
	private $log;

	/** @var Scheduler */
	private $scheduler;

	/** @var array (# => callback($this)) */
	public $onStart;

	/** @var array (# => callback($this)) */
	public $onBeforeDatabaseWipe;

	/** @var array (# => callback($this)) */
	public $onAfterDatabaseWipe;

	/** @var array (# => callback($this, Migration[] $migrations)) */
	public $onScheduleReady;

	/** @var array (# => callback($this, Migration $migration)) */
	public $onBeforeMigrationExecuted;

	/** @var array (# => callback($this, Migration $migration, int $queriesCount)) */
	public $onAfterMigrationExecuted;

	/** @var array (# => callback($this)) */
	public $onComplete;

	/** @var array (# => callback($this, Exception $e)) */
	public $onError;

	/**
	 * @param DatabaseHelpers
	 * @param MigrationsTable
	 * @param Scheduler
	 */
	public function __construct(DatabaseHelpers $dbHelper, IExecutionLog $log, Scheduler $scheduler)
	{
		$this->dbHelper = $dbHelper;
		$this->log = $log;
		$this->scheduler = $scheduler;
	}

	/**
	 * Simple "Nette\Object-like" event support.
	 */
	public function __call($method, array $args)
	{
		if (substr_compare($method, 'on', 0, 2) === 0 && property_exists($this, $method))
		{
			$callbacks = $this->$method;
			if (is_array($callbacks))
			{
				foreach ($callbacks as $callback)
				{
					call_user_func_array($callback, $args);
				}
			}
			elseif ($callbacks !== NULL)
			{
				throw new LogicException(sprintf('Property %s::$%s must be array or NULL.', get_class($this), $method));
			}
		}
		else
		{
			throw new LogicException(sprintf('Call to undefined method %s::%s().', get_class($this), $method));
		}
	}

	/**
	 * @param  string[]
	 * @param  string self::MODE_CONTINUE or self::MODE_RESET
	 * @return void
	 */
	public function run(array $enabledGroups, $mode)
	{
		try
		{
			$this->onStart($this);
			$this->dbHelper->setup();
			$this->dbHelper->lock();

			if ($mode === Scheduler::MODE_RESET)
			{
				$this->onBeforeDatabaseWipe($this);
				$this->dbHelper->wipeDatabase();
				$this->onAfterDatabaseWipe($this);
			}

			$this->log->init();

			$migrations = $this->scheduler->getSchedule($enabledGroups, $mode);
			$this->onScheduleReady($this, $migrations);

			foreach ($migrations as $migration)
			{
				$this->onBeforeMigrationExecuted($this, $migration);
				$queriesCount = $this->execute($migration);
				$this->onAfterMigrationExecuted($this, $migration, $queriesCount);
			}

			$this->dbHelper->unlock();
			$this->onComplete($this);
		}
		catch (\Migrations\Exceptions\Exception $e)
		{
			if ($this->dbHelper->isLocked())
			{
				try
				{
					$this->dbHelper->unlock();
				}
				catch (LockException $e2)
				{
					// ignore
				}
			}

			$this->onError($this, $e);
		}
	}

	/**
	 * @param  Migration
	 * @return int number of executed queries
	 * @throws ExecutionException
	 */
	private function execute(Migration $migration)
	{
		// Note: MySQL implicitly commits after some operations, such as CREATE or ALTER TABLE, see http://dev.mysql.com/doc/refman/5.6/en/implicit-commit.html
		$this->dbHelper->beginTransaction();
		$file = $migration->getFile();
		$recordId = $this->log->logMigrationBeforeStart($file);

		try
		{
			$queriesCount = $migration->execute();
		}
		catch (\Exception $e)
		{
			$this->dbHelper->rollbackTransaction();
			throw new ExecutionException(sprintf('Executing migration "%s" has failed.', $file->getPath()), NULL, $e);
		}

		$this->log->logMigrationCompleted($recordId);
		$this->dbHelper->commitTransaction();

		return $queriesCount;
	}

}

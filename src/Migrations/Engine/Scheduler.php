<?php
namespace Migrations\Engine;

use Migrations\Entities\File;
use Migrations\Entities\Group;
use Migrations\Entities\Migration;
use Migrations\Exceptions\LogicException;

class Scheduler
{

	/** modes */
	const MODE_CONTINUE = 'continue';
	const MODE_RESET = 'reset';

	/** @var MigrationsTable */
	private $log;

	/** @var Finder */
	private $finder;

	/** @var array (name => Group) */
	private $groups;

	/** @var array (name => isGroupEnabled) */
	private $isEnabled;

	/** @var array (extension => IExtensionHandler) */
	private $extensionHandlers;

	/**
	 * @param IExecutionLog
	 * @param Finder
	 * @param Group[]
	 * @param array (extension => IExtensionHandler)
	 */
	public function __construct(IExecutionLog $log, Finder $finder, array $groups, array $extensionHandlers)
	{
		$this->log = $log;
		$this->finder = $finder;
		$this->groups = $this->getAssocGroups($groups);
		$this->extensionHandlers = $extensionHandlers;
	}

	/**
	 * Returns list of migrations which needs to be executed sorted in order of execution.
	 *
	 * @param  string[]
	 * @param  string self::MODE_CONTINUE or self::MODE_RESET
	 * @return Migration[]
	 */
	public function getSchedule(array $enabledGroups, $mode)
	{
		$enabledGroups = $this->initEnabledGroups($enabledGroups);
		$records = $this->log->getAllRecords();
		$allFiles = $this->finder->find($enabledGroups, array_keys($this->extensionHandlers));
		$filesToExecute = $this->resolveOrder($this->groups, $records, $allFiles, $mode);
		$migrations = $this->createMigrationsList($filesToExecute);

		return $migrations;
	}

	/**
	 * @param  string[]
	 * @return Group[]
	 */
	private function initEnabledGroups($enabledGroups)
	{
		$this->isEnabled = array_fill_keys(array_keys($this->groups), FALSE);
		$list = array();
		foreach ($enabledGroups as $name)
		{
			if (!isset($this->groups[$name]))
			{
				throw new LogicException(sprintf('Unknown group "%s".', $name));
			}
			else
			{
				$this->isEnabled[$name] = TRUE;
				$list[] = $this->groups[$name];
			}
		}
		return $list;
	}

	/**
	 * Wraps files with Migration object.
	 *
	 * @param  File[]
	 * @return Migration[]
	 */
	private function createMigrationsList(array $files)
	{
		$migrations = array();
		foreach ($files as $file)
		{
			$migrations[] = new Migration($file, $this->extensionHandlers[$file->extension]);
		}
		return $migrations;
	}

	/**
	 * @param  array (name => Group)
	 * @param  ExecutionRecord[] all records about previously executed migrations
	 * @param  File[] all currently existing files (in enabled groups) containing migrations
	 * @param  string
	 * @return File[]
	 * @throws \Migrations\Exceptions\Exception
	 */
	private function resolveOrder(array $groups, array $records, array $files, $mode)
	{
		$this->validateGroups($groups);

		if ($mode === self::MODE_RESET) return $this->sortFiles($files);

		$records = $this->getAssocRecords($records);
		$files = $this->getAssocFiles($files);
		$lastRecord = NULL;

		foreach ($records as $groupName => $records2)
		{
			if (!isset($groups[$groupName]))
			{
				throw new LogicException(sprintf(
					'Previously executed migration depends on unknown group "%s".',
					$groupName
				));
			}

			$group = $groups[$groupName];
			foreach ($records2 as $fileName => $record)
			{
				if (!$record->completed)
				{
					throw new LogicException(sprintf(
						'Previously executed migration "%s/%s" did not succeed. Please fix this manually or reset the migrations.',
						$groupName, $fileName
					));
				}

				if (isset($files[$groupName][$fileName]))
				{
					$file = $files[$groupName][$fileName];
					if ($record->checksum !== $file->checksum)
					{
						throw new LogicException(sprintf(
							'Previously executed migration "%s/%s" has been changed. You MUST never change a migration.',
							$groupName, $fileName
						));
					}
					unset($files[$groupName][$fileName]);
				}
				elseif ($this->isEnabled[$group->name])
				{
					throw new LogicException(sprintf(
						'Previously executed migration "%s/%s" no longer exists. You MUST never delete a migration.',
						$groupName, $fileName
					));
				}

				if ($lastRecord === NULL || strcmp($record->filename, $lastRecord->filename) > 0)
				{
					$lastRecord = $record;
				}
			}
		}

		$files = $this->getFlatFiles($files);
		$files = $this->sortFiles($files);
		if ($files && $lastRecord)
		{
			$firstFile = $files[0];
			if (strcmp($firstFile->name, $lastRecord->filename) < 0)
			{
				throw new LogicException(sprintf(
					'New migration "%s/%s" must follow after the latest executed migration "%s/%s".',
					$firstFile->group->name, $firstFile->name, $lastRecord->group, $lastRecord->filename
				));
			}
		}

		return $files;
	}

	/**
	 * @param  File[]
	 * @return File[] sorted
	 */
	private function sortFiles(array $files)
	{
		usort($files, function (File $a, File $b) {
			return strcmp($a->name, $b->name);
		});

		return $files;
	}

	/**
	 * @param  Group[]
	 * @return array (name => Group)
	 */
	private function getAssocGroups(array $groups)
	{
		$assoc = array();
		foreach ($groups as $group) $assoc[$group->name] = $group;
		return $assoc;
	}

	/**
	 * @param  ExecutionRecord[]
	 * @return array (groupName => array(fileName => ExecutionRecord, ...))
	 */
	private function getAssocRecords(array $records)
	{
		$assoc = array();
		foreach ($records as $record) $assoc[$record->group][$record->filename] = $record;
		return $assoc;
	}

	/**
	 * @param  File[]
	 * @return array (groupName => array(fileName => File, ...))
	 */
	private function getAssocFiles(array $files)
	{
		$assoc = array();
		foreach ($files as $file) $assoc[$file->group->name][$file->name] = $file;
		return $assoc;
	}

	/**
	 * @param  array (groupName => array(fileName => File, ...))
	 * @return File[]
	 */
	private function getFlatFiles(array $files)
	{
		$flat = array();
		foreach ($files as $tmp) foreach ($tmp as $file) $flat[] = $file;
		return $flat;
	}

	/**
	 * @param  array (name => Group)
	 * @return void
	 * @throws \Migrations\Exceptions\LogicException
	 */
	private function validateGroups(array $groups)
	{
		foreach ($groups as $group)
		{
			foreach ($group->dependencies as $dependency)
			{
				if (!isset($groups[$dependency]))
				{
					throw new LogicException(sprintf(
						'Group "%s" depends on unknown group "%s".',
						$group->name, $dependency
					));
				}
				elseif (!$this->isEnabled[$dependency])
				{
					throw new LogicException(sprintf(
						'Group "%s" depends on disabled group "%s". Please enable group "%s" to continue.',
						$group->name, $dependency, $dependency
					));
				}
			}
		}
	}

}

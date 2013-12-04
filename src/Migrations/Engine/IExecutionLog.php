<?php
namespace Migrations\Engine;

use Migrations\Entities\ExecutionRecord;
use Migrations\Entities\File;


interface IExecutionLog
{

	/**
	 * Initializes the storage.
	 *
	 * @return void
	 */
	public function init();

	/**
	 * Returns all existing execution records.
	 *
	 * @return ExecutionRecord[]
	 */
	public function getAllRecords();

	/**
	 * Creates record that migration is about to be executed.
	 *
	 * @return int id of created record
	 */
	public function logMigrationBeforeStart(File $file);

	/**
	 * Marks given migration as successfully completed.
	 *
	 * @param  int
	 * @return void
	 */
	public function logMigrationCompleted($recordId);

}

<?php
namespace Migrations\Engine;

use DateTime;
use DibiConnection;
use Migrations\Entities\ExecutionRecord;
use Migrations\Entities\File;


class MigrationsTable
{

	/** @var DibiConnection */
	private $dibi;

	/** @var string */
	private $tableName;

	/**
	 * @param  DibiConnection
	 * @param  string
	 */
	public function __construct(DibiConnection $dibiConnection, $tableName)
	{
		$this->dibi = $dibiConnection;
		$this->tableName = $tableName;
	}

	/**
	 * Returns name of the table.
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->tableName;
	}

	/**
	 * Creates the table if it does not already exist.
	 *
	 * @return void
	 */
	public function create()
	{
		$this->dibi->query('
			CREATE TABLE IF NOT EXISTS %n', $this->tableName, '(
				`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`group` varchar(100) NOT NULL,
				`file` varchar(100) NOT NULL,
				`checksum` char(32) NOT NULL,
				`executed` datetime NOT NULL,
				`ready` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE KEY `type_file` (`group`, `file`)
			) ENGINE=InnoDB;
		');
	}

	/**
	 * Drops the table.
	 *
	 * @return void
	 */
	public function drop()
	{
		$this->dibi->query('DROP TABLE %n', $this->tableName);
	}

	/**
	 * Creates record that migration is about to be executed.
	 *
	 * @return int id of created record
	 */
	public function logMigrationBeforeStart(File $file)
	{
		$this->dibi->query('INSERT INTO %n', $this->tableName, array(
			'group' => $file->group->name,
			'file' => $file->name,
			'checksum' => $file->checksum,
			'executed' => new DateTime('now'),
			'ready' => (int) FALSE,
		));

		return $this->dibi->getInsertId();
	}

	/**
	 * Marks given migration as successfully completed.
	 *
	 * @param  int
	 * @return void
	 */
	public function logMigrationCompleted($recordId)
	{
		$this->dibi->query('
			UPDATE %n', $this->tableName, '
			SET [ready] = 1
			WHERE [id] = %i', $recordId
		);
	}

	/**
	 * Returns all existing execution records.
	 *
	 * @return ExecutionRecord[]
	 */
	public function getAllRecords()
	{
		$result = $this->dibi->query('
			SELECT *
			FROM %n', $this->tableName, '
		');

		$result->setRowFactory(function (array $row) {
			$record = new ExecutionRecord();
			$record->id = $row['id'];
			$record->group = $row['group'];
			$record->filename = $row['file'];
			$record->checksum = $row['checksum'];
			$record->executedAt = $row['executed'];
			$record->completed = (bool) $row['ready'];
			return $record;
		});

		return $result->fetchAll();
	}

}

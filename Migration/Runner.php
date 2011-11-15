<?php

namespace Migration;

use Nette\Object;
use Nette\Utils\Finder;
use DibiConnection;

/**
 * <code>
 * 	$runner = new Runner($dibiConnection);
 * 	$runner->run(__DIR__, isset($_GET['reset']));
 * </code>
 *
 * @author Petr Procházka
 */
class Runner extends Object
{

	/** @var DibiConnection */
	private $dibi;

	/** @var IPrinter */
	private $printer;

	/**
	 * @param DibiConnection
	 * @param IPrinter
	 */
	public function __construct(DibiConnection $dibi, IPrinter $printer = NULL)
	{
		$this->dibi = $dibi;
		$this->printer = $printer === NULL ? new DumpPrinter : $printer;
	}

	/**
	 * @param string
	 * @param bool Kdyz true, tak nejprve smaze celou databazi.
	 */
	public function run($dir, $reset = false)
	{
		try {
			$this->runSetup();
			if ($reset)
			{
				$this->runWipe();
				$this->printer->printReset();
			}
			$this->runInitMigrationTable();

			$toExecute = $this->getToExecute($this->getAllMigrations(), $this->getAllFiles($dir));

			$this->printer->printToExecute($toExecute);

			foreach ($toExecute as $sql)
			{
				$this->printer->printExecute($sql, $this->execute($sql));
			}

			$this->printer->printDone();
		} catch (Exception $e) {
			$this->printer->printError($e);
		}
	}

	protected function runSetup()
	{
		$this->dibi->query('SET NAMES utf8');
		$this->dibi->query('SET foreign_key_checks = 0');
		$this->dibi->query("SET time_zone = 'SYSTEM'");
		$this->dibi->query("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
	}

	protected function runWipe()
	{
		foreach ($this->dibi->getDatabaseInfo()->getTableNames() as $table)
		{
			$this->dibi->query('DROP TABLE %n', $table);
		}
	}

	protected function runInitMigrationTable()
	{
		$this->dibi->query("CREATE TABLE IF NOT EXISTS [migrations] (
			[id] bigint NOT NULL AUTO_INCREMENT,
			[file] text NOT NULL,
			[checksum] text NOT NULL,
			[executed] datetime NOT NULL,
			[ready] smallint(1) NOT NULL default 0,
			PRIMARY KEY ([id])
		) ENGINE='InnoDB'");
	}

	/** @return array file => DibiRow */
	protected function getAllMigrations()
	{
		return $this->dibi->query('SELECT * FROM [migrations]')->fetchAssoc('file');
	}

	/**
	 * @param string
	 * @return array of MigrationSqlFile
	 */
	protected function getAllFiles($dir)
	{
		$files = iterator_to_array(Finder::findFiles('*.sql')->in($dir));
		ksort($files);
		return array_map(function ($sql) {
			return new MigrationSqlFile($sql);
		}, $files);
	}

	/**
	 * @param array {@see self::getAllMigrations()}
	 * @param array {@see self::getAllFiles()}
	 * @return array of MigrationSqlFile
	 */
	protected function getToExecute(array $migrations, array $files)
	{
		$toExecute = array();
		foreach ($files as $sql)
		{
			if (isset($migrations[$sql->file]))
			{
				if ($migrations[$sql->file]->checksum !== $sql->checksum)
				{
					throw new Exception("{$sql->file} se zmenil.");
				}
				if (!$migrations[$sql->file]->ready)
				{
					throw new Exception("{$sql->file} se nedokoncil.");
				}
				unset($migrations[$sql->file]);
			}
			else
			{
				$toExecute[] = $sql;
			}
		}
		foreach ($migrations as $m)
		{
			throw new Exception("{$m->file} se smazal.");
		}
		return $toExecute;
	}

	/**
	 * @param MigrationSqlFile
	 * @return int Pocet queries.
	 */
	protected function execute(MigrationSqlFile $sql)
	{
		$this->dibi->begin();
		// mysql pri nekterych operacich commitne (CREATE/ALTER TABLE) http://dev.mysql.com/doc/refman/5.6/en/implicit-commit.html
		// proto se radeji kontroluje jestli bylo dokonceno
		$id = $this->dibi->insert('migrations', $sql->toArray())->execute(\dibi::IDENTIFIER);
		$count = $this->dibi->loadFile($sql->path);
		$this->dibi->update('migrations', array('ready' => 1))->where('[id] = %s', $id)->execute();
		$this->dibi->commit();
		return $count;
	}

}

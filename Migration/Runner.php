<?php

namespace Migration;

use Nette\Object;
use Nette\Utils\Finder;
use DibiConnection;
use Nette\DateTime;

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
			$this->lock();
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

	protected function lock()
	{
		$dibi = $this->dibi;
		$_this = $this;
		$lock = function () use ($dibi, $_this) {
			try {
				$dibi->query("CREATE TABLE %n ([foo] varchar(1) NOT NULL) ENGINE='InnoDB'", 'migrations-lock');
			} catch (\Exception $e) {
				return false;
			}
			register_shutdown_function(function () use ($_this) { $_this->unLock(); });
			return true;
		};

		$times = 0;
		while (!$lock())
		{
			if ($times++ > 100)
			{
				throw new Exception('Lock error');
			}
			usleep(500000); // 500ms
		}
	}

	/**
	 * @access protected
	 */
	public function unLock()
	{
		try {
			$this->dibi->query('DROP TABLE IF EXISTS %n', 'migrations-lock');
		} catch (\Exception $e) {}
	}

	protected function runWipe()
	{
		foreach ($this->dibi->getDatabaseInfo()->getTables() as $table)
		{
			if ($table->getName() === 'migrations-lock') continue;
			$this->dibi->query('DROP ' . ($table->isView() ? 'VIEW' :'TABLE') . ' %n', $table->getName());
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
		$count = $this->loadFile($sql->path);
		if ($count === 0)
		{
			throw new Exception("{$sql->file} neobsahuje zadne sql.");
		}
		$this->dibi->update('migrations', array('ready' => 1))->where('[id] = %s', $id)->execute();
		$this->dibi->commit();
		return $count;
	}

	/**
	 * Import SQL dump from file - extreme fast!
	 * V dibi obsahuje chybu https://github.com/dg/dibi/issues/63
	 * @param  string  filename
	 * @return int  count of sql commands
	 * @author David Grudl
	 */
	protected function loadFile($file)
	{
		$driver = $this->dibi->getDriver();
		@set_time_limit(0); // intentionally @

		$handle = @fopen($file, 'r'); // intentionally @
		if (!$handle)
		{
			throw new RuntimeException("Cannot open file '$file'.");
		}

		$count = 0;
		$sql = '';
		while (!feof($handle))
		{
			$s = fgets($handle);
			$sql .= $s;
			if (substr(rtrim($s), -1) === ';')
			{
				$driver->query($sql);
				$sql = '';
				$count++;
			}
		}
		fclose($handle);
		if (trim($sql))
		{
			$driver->query($sql);
			$count++;
		}
		return $count;
	}

}

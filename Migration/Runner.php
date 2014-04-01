<?php

namespace Migration;

use Nette\Object;
use DibiConnection;
use Nette\Utils\Strings;

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

	/** @var array name => IExtension */
	private $extensions;

	/**
	 * @param DibiConnection
	 * @param IPrinter
	 */
	public function __construct(DibiConnection $dibi, IPrinter $printer = NULL)
	{
		$this->dibi = $dibi;
		$this->printer = $printer === NULL
			? (php_sapi_name() === "cli"
				? new Printers\Console
				: new Printers\HtmlDump
			) : $printer;
		$this->addExtension(new Extensions\Sql($dibi));
	}

	/**
	 * @param string|IFinder
	 * @param bool Kdyz true, tak nejprve smaze celou databazi.
	 */
	public function run($finderOrDirectory, $reset = false)
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

			$toExecute = $this->getToExecute($this->getAllMigrations(), $this->getAllFiles($finderOrDirectory));

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

	/**
	 * @param IExtension
	 * @return Runner $this
	 */
	public function addExtension(IExtension $extension)
	{
		$name = $extension->getName();
		if (isset($this->extensions[$name]))
		{
			throw new Exception("Extension '$name' already defined.");
		}
		$this->extensions[$name] = $extension;
		return $this;
	}

	/**
	 * @param string
	 * @return IExtension
	 */
	protected function getExtension($name)
	{
		if (!isset($this->extensions[$name]))
		{
			throw new Exception("Extension '$name' not found.");
		}
		return $this->extensions[$name];
	}

	protected function runSetup()
	{
		$this->dibi->query('SET NAMES utf8');
		$this->dibi->query('SET foreign_key_checks = 0');
		$this->dibi->query("SET time_zone = 'SYSTEM'");
		$this->dibi->query("SET sql_mode = 'TRADITIONAL'");
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
	 * @param string|IFinder
	 * @return array of File
	 */
	protected function getAllFiles($finderOrDirectory)
	{
		$finder = $finderOrDirectory instanceof IFinder ? $finderOrDirectory : new Finders\Directory($finderOrDirectory);
		return $finder->find(array_keys($this->extensions));
	}

	/**
	 * @param array {@see self::getAllMigrations()}
	 * @param array {@see self::getAllFiles()}
	 * @return array of File
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
			if (!Strings::endsWith($m->file, '.testdata.sql'))
			{
				throw new Exception("{$m->file} se smazal.");
			}
		}
		return $toExecute;
	}

	/**
	 * @param File
	 * @return int Pocet queries.
	 */
	protected function execute(File $sql)
	{
		$this->dibi->begin();
		// mysql pri nekterych operacich commitne (CREATE/ALTER TABLE) http://dev.mysql.com/doc/refman/5.6/en/implicit-commit.html
		// proto se radeji kontroluje jestli bylo dokonceno
		$id = $this->dibi->insert('migrations', $sql->toArray())->execute(\dibi::IDENTIFIER);

		try {
			$count = $this->getExtension($sql->extension)->execute($sql);
		} catch (\Exception $e) {
			throw new Exception("Error in: '{$sql->file}'.", NULL, $e);
		}

		$this->dibi->update('migrations', array('ready' => 1))->where('[id] = %s', $id)->execute();
		$this->dibi->commit();
		return $count;
	}

}

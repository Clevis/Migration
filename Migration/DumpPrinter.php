<?php

namespace Migration;

use Nette\Object;

/**
 * Echoje informace na vystup jako html.
 * @author Petr Procházka
 */
class DumpPrinter extends Object implements IPrinter
{

	/** Migrace se nejprve resetovala. */
	public function printReset()
	{
		$this->dump('RESET');
	}

	/**
	 * Seznam migraci ktere se spusti.
	 * @param array of MigrationSqlFile
	 */
	public function printToExecute(array $toExecute)
	{

	}

	/**
	 * Provedena migrace.
	 * @param MigrationSqlFile
	 * @param int Pocet queries
	 */
	public function printExecute(MigrationSqlFile $sql, $count)
	{
		$this->dump($sql->file . '; ' . $count . ' queries');
	}

	/** Vse vporadku/dokonceno */
	public function printDone()
	{
		$this->dump('OK');
	}

	/**
	 * Nastala chyba.
	 * @param Migration\Exception
	 */
	public function printError(Exception $e)
	{
		$this->dump('ERROR: ' . $e->getMessage());
		throw $e;
	}

	/** @var string */
	protected function dump($s)
	{
		$s = htmlspecialchars($s);
		echo '<pre><h1>';
		echo $s;
		echo '</h1></pre>';
	}

}

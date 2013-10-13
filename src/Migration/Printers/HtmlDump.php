<?php

namespace Migration\Printers;

use Nette\Object;
use Migration;


/**
 * Echoje informace na vystup jako html.
 * @author Petr Procházka
 */
class HtmlDump extends Object implements Migration\IPrinter
{

	/** Migrace se nejprve resetovala. */
	public function printReset()
	{
		$this->dump('RESET');
	}

	/**
	 * Seznam migraci ktere se spusti.
	 * @param array of Migration\File
	 */
	public function printToExecute(array $toExecute)
	{

	}

	/**
	 * Provedena migrace.
	 * @param Migration\File
	 * @param int Pocet queries
	 */
	public function printExecute(Migration\File $sql, $count)
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
	public function printError(Migration\Exception $e)
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

<?php

namespace Migration\Printers;

use Nette\Object;
use Migration;


/**
 * Nic nedela.
 * @author Petr Procházka
 */
class Null extends Object implements Migration\IPrinter
{

	/** Migrace se nejprve resetovala. */
	public function printReset()
	{
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
	}

	/** Vse vporadku/dokonceno */
	public function printDone()
	{
	}

	/**
	 * Nastala chyba.
	 * @param Migration\Exception
	 */
	public function printError(Migration\Exception $e)
	{
		throw $e;
	}

}

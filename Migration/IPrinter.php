<?php

namespace Migration;

/**
 * @author Petr Procházka
 */
interface IPrinter
{

	/** Migrace se nejprve resetovala. */
	public function printReset();

	/**
	 * Seznam migraci ktere se spusti.
	 * @param array of MigrationSqlFile
	 */
	public function printToExecute(array $toExecute);

	/**
	 * Provedena migrace.
	 * @param MigrationSqlFile
	 * @param int Pocet queries
	 */
	public function printExecute(MigrationSqlFile $sql, $count);

	/** Vse vporadku/dokonceno */
	public function printDone();

	/**
	 * Nastala chyba.
	 * @param Migration\Exception
	 */
	public function printError(Exception $e);

}

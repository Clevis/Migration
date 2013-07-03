<?php

namespace Migration\Printers;

use Nette\Object;
use Migration;


/**
 * Vypisuje vystup pro konzoly
 * @author Mikulas DIte
 */
class Console extends Object implements Migration\IPrinter
{

	const COLOR_ERROR = '1;31';
	const COLOR_NOTICE = '1;34';
	const COLOR_SUCCESS = '1;32';

	/** Migrace se nejprve resetovala. */
	public function printReset()
	{
		$this->output('RESET', self::COLOR_NOTICE);
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
		$this->output($sql->file . '; ' . $count . ' queries');
	}

	/** Vse vporadku/dokonceno */
	public function printDone()
	{
		$this->output('OK', self::COLOR_SUCCESS);
	}

	/**
	 * Nastala chyba.
	 * @param Migration\Exception
	 */
	public function printError(Migration\Exception $e)
	{
		$this->output('ERROR: ' . $e->getMessage(), self::COLOR_ERROR);
		throw $e;
	}

	/** @var string */
	protected function output($s, $color = NULL)
	{
		$useColors = preg_match('#^xterm|^screen#', getenv('TERM'));

		if ($color && !in_array($color, self::getColors())) {
			throw new \Nette\InvalidArgumentException('Invalid color specified, expected `self::COLOR_[SUCCESS|NOTICE|ERROR]`.');
		}
		if (!$color || !$useColors)
		{
			echo "$s\n";
		}
		else
		{
			echo "\033[{$color}m$s\033[0m\n";
		}
	}

	protected static function getColors()
	{
		return array(self::COLOR_SUCCESS, self::COLOR_NOTICE, self::COLOR_ERROR);
	}

}

<?php

namespace Migration\Extensions;

use Nette\Object;
use DibiConnection;
use Migration;


/**
 * @author Petr Procházka
 */
class Sql extends Object implements Migration\IExtension
{

	/** @var DibiConnection */
	private $dibi;

	/**
	 * @param DibiConnection
	 */
	public function __construct(DibiConnection $dibi)
	{
		$this->dibi = $dibi;
	}

	/**
	 * Unique extension name.
	 * @return string
	 */
	public function getName()
	{
		return 'sql';
	}

	/**
	 * @param Migration\File
	 * @return int number of queries
	 */
	public function execute(Migration\File $sql)
	{
		$count = $this->loadFile($sql->path);
		if ($count === 0)
		{
			throw new Migration\Exception("{$sql->file} neobsahuje zadne sql.");
		}
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

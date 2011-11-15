<?php

namespace Migration;

use Orm\Entity;

/**
 * @property string $file
 * @property string $checksum
 * @property-read DateTime $executed {default now}
 *
 * @author Petr Procházka
 */
class MigrationSqlFile extends Entity
{
	/** @var string */
	private $path;

	/** @param string */
	public function __construct($path)
	{
		parent::__construct();
		$this->path = (string) $path;
		$this->file = basename($path);
		$this->checksum = md5_file($path);
	}

	/** @return string */
	public function getPath()
	{
		return $this->path;
	}
}

<?php

namespace Migration;

use Nette\Object;
use DateTime;


/**
 * @author Petr Procházka
 */
class File extends Object
{

	/** @var string */
	private $path;

	/** @var string */
	private $extension;

	/** @var string */
	private $file;

	/** @var string */
	private $checksum;

	/** @var DateTime */
	private $executed;

	/**
	 * @param string
	 * @param string
	 */
	public function __construct($path, $extension)
	{
		$this->path = (string) $path;
		$this->extension = $extension;
		$this->file = basename($path);
		$this->checksum = md5_file($path);
		$this->executed = new DateTime('now');
	}

	/** @return string */
	public function getPath()
	{
		return $this->path;
	}

	/** @return string */
	public function getExtension()
	{
		return $this->extension;
	}

	/** @return string */
	public function getFile()
	{
		return $this->file;
	}

	/** @return string */
	public function getChecksum()
	{
		return $this->checksum;
	}

	/** @return DateTime */
	public function getExecuted()
	{
		return clone $this->executed;
	}

	/** @return array */
	public function toArray()
	{
		return array(
			'file' => $this->getFile(),
			'checksum' => $this->getChecksum(),
			'executed' => $this->getExecuted(),
		);
	}
}

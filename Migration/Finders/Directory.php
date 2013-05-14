<?php

namespace Migration\Finders;

use Nette\Object;
use Nette\Utils\Finder;
use Migration;


/**
 * @author Petr Procházka
 */
class Directory extends Object implements Migration\IFinder
{

	/** @var string */
	private $directory;

	/** @var bool */
	private $errorOnInvalidExtension;

	/** @var array extensionName => preg patern */
	private $extensionPaterns;

	/**
	 * @param string
	 * @param bool
	 */
	public function __construct($directory, $errorOnInvalidExtension = false)
	{
		$this->directory = $directory;
		$this->errorOnInvalidExtension = $errorOnInvalidExtension;
	}

	/**
	 * @param array of extensionName
	 * @return array path => Migration\File
	 */
	public function find(array $extensions)
	{
		$this->extensionPaterns = array_combine($extensions, array_map(function ($name) {
			return '#' . preg_quote('.' . $name, '#') . '$#si';
		}, $extensions));

		$files = iterator_to_array(Finder::findFiles(NULL)->in($this->directory));
		ksort($files);

		return array_filter(array_map(array($this, 'createMigrationFile'), $files));
	}

	/**
	 * @param string
	 * @return File
	 */
	protected function createMigrationFile($path)
	{
		if ($extension = $this->getExtensionForFileName(basename($path)))
		{
			return new Migration\File($path, $extension);
		}
	}

	/**
	 * @param string
	 * @return string
	 */
	protected function getExtensionForFileName($fileName)
	{
		$posibles = array();
		foreach ($this->extensionPaterns as $name => $patern)
		{
			if (preg_match($patern, $fileName))
			{
				$posibles[] = $name;
			}
		}
		if (count($posibles) > 1)
		{
			throw new Migration\Exception("Finders\\Directory: '$fileName' is ambiguous. More than one extension can be used: '" . implode("', '", $posibles) . "'.");
		}
		if (count($posibles) === 0)
		{
			if ($this->errorOnInvalidExtension)
			{
				throw new Migration\Exception("Finders\\Directory: no extension match for '$fileName'.");
			}
			return NULL;
		}
		return $posibles[0];
	}

}

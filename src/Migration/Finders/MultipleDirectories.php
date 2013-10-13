<?php

namespace Migration\Finders;

use Nette\Object;
use Nette\Utils\Finder;
use Migration;


/**
 * @author Petr Procházka
 */
class MultipleDirectories extends Object implements Migration\IFinder
{

	/** @var Directory[] */
	private $directories;

	public function __construct()
	{
	}

	/**
	 * @param string
	 * @param bool
	 * @return MultipleDirectories $this
	 */
	public function addDirectory($directory, $errorOnInvalidExtension = true)
	{
		$this->directories[] = new Directory($directory, $errorOnInvalidExtension);
		return $this;
	}

	/**
	 * @param array of extensionName
	 * @return array path => Migration\File
	 */
	public function find(array $extensions)
	{
		$files = array();
		foreach ($this->directories as $directory)
		{
			$files += $directory->find($extensions);
		}

		uasort($files, function ($a, $b) {
			if ($a->file === $b->file)
			{
				list($a, $b) = MultipleDirectories::pathDiff($a->path, $b->path);
				throw new Migration\Exception("Finders\\MultipleDirectories: migration file name is same in '$b' and '$a'.");
			}
			return strcmp($a->file, $b->file);
		});

		return $files;
	}

	/**
	 * Return defferent part of two path.
	 * @param string /foo/bar/aaa/bbb
	 * @param string /foo/bar/ccc/ddd/eee
	 * @return array aaa/bbb, ccc/ddd/eee
	 */
	public static function pathDiff($path1, $path2)
	{
		$path1 = explode(DIRECTORY_SEPARATOR, realpath($path1));
		$path2 = explode(DIRECTORY_SEPARATOR, realpath($path2));
		for ($i = 0, $max = max(count($path1), count($path2)) - 1; $i < $max; $i++)
		{
			$part1 = isset($path1[$i]) ? $path1[$i] : NULL;
			$part2 = isset($path2[$i]) ? $path2[$i] : NULL;
			if ($part1 !== $part2)
			{
				break;
			}
			unset($path1[$i], $path2[$i]);
		}
		return array(
			implode('/', $path1),
			implode('/', $path2),
		);
	}
}

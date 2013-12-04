<?php
namespace Migrations\Engine;

use Migrations\Entities\File;
use Migrations\Entities\Group;


class Finder
{

	/**
	 * Finds files in given groups.
	 *
	 * @param  Group[] list of enabled groups
	 * @param  string[] list of known file extensions
	 * @return File[]
	 * @throws \Migrations\Exceptions\Exception
	 */
	public function find(array $groups, array $extensions)
	{
		$files = array();
		foreach ($groups as $group)
		{
			$items = $this->getFiles($group->directory);
			foreach ($items as $fileName)
			{
				if ($fileName[0] === '.') continue; // skip '.', '..' and hidden files

				$file = new File();
				$file->group = $group;
				$file->name = $fileName;
				$file->extension = $this->getExtension($file, $extensions);
				$file->checksum = $this->getChecksum($file);

				$files[] = $file;
			}
		}
		return $files;
	}

	/**
	 * Returns files inside the specified directory.
	 *
	 * @param  string
	 * @return string[]
	 * @throws \Migrations\Exceptions\IOException if directory does not exist
	 */
	protected function getFiles($directory)
	{
		$files = @scandir($directory); // directory may not exist
		if ($files === FALSE)
		{
			throw new \Migrations\Exceptions\IOException(sprintf(
				'Finder: Directory "%s" does not exist.',
				$directory
			));
		}
		return $files;
	}

	/**
	 * Returns file extension.
	 *
	 * @param  File
	 * @param  string[]
	 * @return string
	 * @throws \Migrations\Exceptions\Exception
	 */
	protected function getExtension(File $file, array $extensions)
	{
		$fileExt = NULL;

		foreach ($extensions as $extension)
		{
			if (substr($file->name, -strlen($extension)) === $extension)
			{
				if ($fileExt !== NULL)
				{
					throw new \Migrations\Exceptions\LogicException(sprintf(
						'Finder: Extension of "%s" is ambiguous, both "%s" and "%s" can be used.',
						$file->group->directory . '/' . $file->name, $fileExt, $extension
					));
				}
				else
				{
					$fileExt = $extension;
				}
			}
		}

		if ($fileExt === NULL)
		{
			throw new \Migrations\Exceptions\LogicException(sprintf(
				'Finder: No extension matched for file "%s". Supported extensions are %s.',
				$file->group->directory . '/' . $file->name, '"' . implode('", "', $extensions) . '"'
			));
		}

		return $fileExt;
	}

	/**
	 * Returns MD5 hash of file content.
	 *
	 * @param  File
	 * @return string
	 * @throws \Migrations\Exceptions\IOException
	 */
	protected function getChecksum(File $file)
	{
		$path = $file->getPath();
		$checksum = md5_file($path);
		if ($checksum === FALSE)
		{
			throw new \Migrations\Exceptions\IOException(sprintf(
				'Finder: Unable to calculate MD5 hash of file "%s".',
				$path
			));
		}
		return $checksum;
	}

}

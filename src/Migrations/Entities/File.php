<?php
namespace Migrations\Entities;


/**
 * Entity representing a single file containing a migration
 */
class File
{

	/** @var Group group to which this file belong to */
	public $group;

	/** @var string e.g. '2013-07-05-users.sql' */
	public $name;

	/** @var string e.g. 'sql' */
	public $extension;

	/** @var string MD5 of file content */
	public $checksum;

	/**
	 * Returns absolute path to this file.
	 *
	 * @return string
	 */
	public function getPath()
	{
		return $this->group->directory . '/' . $this->name;
	}

}

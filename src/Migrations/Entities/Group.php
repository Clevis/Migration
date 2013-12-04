<?php
namespace Migrations\Entities;


/**
 * Entity representing a named collection of files.
 */
class Group
{

	/** @var string */
	public $name;

	/** @var string absolute path to the directory */
	public $directory;

	/** @var string[] list of group names this group depends on */
	public $dependencies;

}

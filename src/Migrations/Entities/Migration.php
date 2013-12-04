<?php
namespace Migrations\Entities;

use Migrations\IExtensionHandler;


class Migration
{

	/** @var File */
	private $file;

	/** @var IExtensionHandler */
	private $handler;

	/**
	 * @param File
	 * @param IExtensionHandler
	 */
	public function __construct(File $file, IExtensionHandler $handler)
	{
		$this->file = $file;
		$this->handler = $handler;
	}

	/**
	 * @return File
	 */
	public function getFile()
	{
		return $this->file;
	}

	/**
	 * @return IExtensionHandler
	 */
	public function getHandler()
	{
		return $this->handler;
	}

	/**
	 * Executes a single migration.
	 *
	 * @return int number of executed queries
	 */
	public function execute()
	{
		return $this->handler->execute($this->file);
	}

}

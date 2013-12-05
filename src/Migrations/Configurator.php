<?php
namespace Migrations;

use DibiConnection;
use Migrations\Entities\Group;


class Configurator
{

	/** environments */
	const DETECT = 'detect';
	const CONSOLE = 'console';
	const HTTP = 'http';

	/** @var string */
	private $environment = self::DETECT;

	/** @var array (name => Group) */
	private $groups = array();

	/** @var array (extension => IExtensionHandler) */
	private $extensionHandlers = array();

	/**
	 * @return string
	 */
	public function getEnvironment()
	{
		return $this->environment;
	}

	/**
	 * @param string
	 */
	public function setEnvironment($environment)
	{
		$this->environment = $environment;
	}

	/**
	 * Registers directory containing migrations.
	 *
	 * @param  string
	 * @param  string
	 * @param  string[] list of names which the group depends on
	 * @return self
	 */
	public function addGroup($name, $dir, array $dependencies = array())
	{
		$group = new Group();
		$group->name = $name;
		$group->directory = $dir;
		$group->dependencies = $dependencies;
		$group->enabled = FALSE;

		$this->groups[$name] = $group;
		return $this;
	}

	/**
	 * Registers an extension handler.
	 *
	 * @param  string file extension, e.g. 'sql'
	 * @param  IExtensionHandler
	 * @return self
	 */
	public function addExtensionHandler($extension, IExtensionHandler $handler)
	{
		$this->extensionHandlers[$extension] = $handler;
		return $this;
	}

	/**
	 * Returns auto-detected environment.
	 *
	 * @return string
	 */
	protected function detectEnvironment()
	{
		return (PHP_SAPI === 'cli' ? self::CONSOLE : self::HTTP);
	}

	/**
	 * Creates dependency injection container.
	 *
	 * @param  DibiConnection
	 * @return DI\Container
	 */
	public function createContainer(DibiConnection $dibi)
	{
		$env = ($this->environment === self::DETECT ? $this->detectEnvironment() : $this->environment);
		$dic = new DI\Container($dibi, $env, $this->groups, $this->extensionHandlers);

		return $dic;
	}

}

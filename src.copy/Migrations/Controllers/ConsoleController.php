<?php
namespace Migrations\Controllers;

use Migrations\Engine;
use Migrations\Entities\Migration;
use Migrations\Exceptions\ImpossibleStateException;


class ConsoleController extends BaseController
{

	/** console colors */
	const COLOR_ERROR = '1;31';
	const COLOR_ERROR_BG = '1;41;37';
	const COLOR_GREEN = '0;32';
	const COLOR_YELLOW = '0;33';
	const COLOR_SUCCESS = '1;32';
	const COLOR_SUCCESS_BG = '1;42;37';

	/** @var bool */
	private $useColors;

	/** @var Migration */
	private $currentMigration;

	/** @var int */
	private $maxNameLength;

	public function run()
	{
		$this->processArguments();
		$this->startRunner();
	}

	public function __construct(Engine\Runner $runner, array $groups)
	{
		parent::__construct($runner, $groups);
		$this->useColors = $this->detectColorSupport();
		$this->initRunnerEvents();
	}

	private function processArguments()
	{
		$arguments = array_slice($_SERVER['argv'], 1);
		$help = (count($arguments) === 0);
		$groups = FALSE;
		$error = FALSE;

		foreach ($arguments as $argument)
		{
			if (strncmp($argument, '--', 2) === 0)
			{
				if ($argument === '--reset')
				{
					$this->mode = Engine\Scheduler::MODE_RESET;
				}
				else
				{
					if ($argument === '--help')
					{
						$help = TRUE;
					}
					else
					{
						$this->writeColored(STDERR, self::COLOR_ERROR, "Error: Unknown option '%s'\n", $argument);
						$error = TRUE;
					}
				}
			}
			else
			{
				if (isset($this->groups[$argument]))
				{
					$this->enabledGroups[] = $argument;
					$groups = TRUE;
				}
				else
				{
					$this->writeColored(STDERR, self::COLOR_ERROR, "Error: Unknown group '%s'\n", $argument);
					$error = TRUE;
				}
			}
		}

		if (!$groups && !$help)
		{
			$this->writeColored(STDERR, self::COLOR_ERROR, "Error: At least one group must be enabled.\n");
			$error = TRUE;
		}

		if ($error)
		{
			$this->write(STDOUT, "\n");
		}

		if ($help || $error)
		{
			$this->write(STDOUT, "Clevis\\Migrations CLI - executes migrations in the given groups.\n\n");
			$this->writeColored(STDOUT, self::COLOR_YELLOW, "Usage:\n");
			$this->write(STDOUT, "  %s group1 [, group2, ...] [--reset] [--help]\n\n", $_SERVER['argv'][0]);

			$this->writeColored(STDOUT, self::COLOR_YELLOW, "Registered groups:\n");
			foreach ($this->groups as $group) $this->write(STDOUT, "  %s\n", $group->name);
			$this->write(STDOUT, "\n");

			$combinations = $this->getGroupsCombinations();
			$this->writeColored(STDOUT, self::COLOR_YELLOW, "Allowed group combinations:\n");
			foreach ($combinations as $combination) $this->write(STDOUT, "  %s\n", implode(' ', $combination));
			$this->write(STDOUT, "\n");

			$this->writeColored(STDOUT, self::COLOR_YELLOW, "Options:\n");
			$this->write(STDOUT, "  --reset      drop all tables (views, ...) in database and start from scratch\n");
			$this->write(STDOUT, "  --help       show this help\n");

			exit($error ? 1 : 0);
		}
	}

	private function initRunnerEvents()
	{
		$this->runner->onAfterDatabaseWipe[] = array($this, 'handleDatabaseWiped');
		$this->runner->onScheduleReady[] = array($this, 'handleOrderResolution');
		$this->runner->onBeforeMigrationExecuted[] = array($this, 'handleBeforeMigrationExecuted');
		$this->runner->onAfterMigrationExecuted[] = array($this, 'handleAfterMigrationExecuted');
		$this->runner->onComplete[] = array($this, 'handleComplete');
		$this->runner->onError[] = array($this, 'handleError');
	}

	public function handleDatabaseWiped(Engine\Runner $runner)
	{
		$this->writeColored(STDOUT, self::COLOR_GREEN, "Database reset completed!\n");
	}

	public function handleOrderResolution(Engine\Runner $runner, array $migrations)
	{
		$maxNameLength = 0;
		if ($migrations)
		{
			foreach ($migrations as $migration)
			{
				$file = $migration->getFile();
				$maxNameLength = max($maxNameLength, strlen($file->group->name) + strlen($file->name));
			}
		}
		else
		{
			$this->write(STDOUT, "No migration needs to be executed.\n");
		}

		$this->maxNameLength = $maxNameLength;
	}

	public function handleBeforeMigrationExecuted(Engine\Runner $runner, Migration $migration)
	{
		$format = '%-' . ($this->maxNameLength + 2) . 's';
		$file = $migration->getFile();
		$this->write(STDOUT, $format, $file->group->name . '/' . $file->name . ' ');
	}

	public function handleAfterMigrationExecuted(Engine\Runner $runner, Migration $migration, $queriesCount)
	{
		$this->writeColored(STDOUT, self::COLOR_SUCCESS, "OK");
		$this->write(STDOUT, " (%d %s)\n", $queriesCount, ($queriesCount > 1 ? 'queries' : 'query'));
	}

	public function handleComplete(Engine\Runner $runner)
	{
		$this->writeColored(STDOUT, self::COLOR_SUCCESS_BG, "\n                                                   \n");
		$this->writeColored(STDOUT, self::COLOR_SUCCESS_BG, "  All migrations have been successfully executed.  \n");
		$this->writeColored(STDOUT, self::COLOR_SUCCESS_BG, "                                                   \n\n");
	}

	public function handleError(Engine\Runner $runner, \Migrations\Exceptions\Exception $e)
	{
		if ($this->currentMigration !== NULL)
		{
			$this->writeColored(STDOUT, self::COLOR_ERROR, "ERROR\n");
		}

		$header = '[' . get_class($e) . ']';
		$message = $e->getMessage();
		$length = max(strlen($header), strlen($message));
		$empty = str_repeat(' ', $length + 4);
		$header = str_pad($header, $length);
		$message = str_pad($message, $length);

		$this->writeColored(STDERR, self::COLOR_ERROR_BG, "\n%s\n  %s  \n  %s  \n%s\n\n", $empty, $header, $message, $empty);
		throw $e;
	}

	/**
	 * Prints text to a console.
	 *
	 * @param  resource
	 * @param  string
	 * @return void
	 */
	protected function write($stream, $s)
	{
		$args = array_slice(func_get_args(), 2);
		vfprintf($stream, $s, $args);
	}

	/**
	 * Prints text to a console in a specific color.
	 *
	 * @param  resource
	 * @param  string
	 * @param  string
	 * @return void
	 */
	protected function writeColored($stream, $color, $s)
	{
		$args = func_get_args();
		unset($args[1]); // color
		if ($color !== NULL && $this->useColors)
		{
			$args[2] = "\033[{$color}m$s\033[0m";
		}
		call_user_func_array(array($this, 'write'), $args);
	}

	/**
	 * @return bool TRUE if terminal support colors, FALSE otherwise
	 */
	protected function detectColorSupport()
	{
		return (bool) preg_match('#^xterm|^screen|^cygwin#', getenv('TERM'));
	}

}

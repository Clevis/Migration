<?php
/**
 * Test: Migrations\Engine\Finder
 *
 * @testCase
 */

namespace Migrations\Tests;

use DibiConnection;
use Migrations\Engine\DatabaseHelpers;
use Mockery;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../dibi-connect.php';

class DatabaseHelpersTest extends TestCase
{

	/** @var \DibiConnection */
	private $dibi;

	/** @var DatabaseHelpers */
	private $dbHelpers;

	/** @var string */
	private $database;

	public function __construct(DibiConnection $dibi)
	{
		$this->dibi = $dibi;
	}

	protected function setUp()
	{
		parent::setUp();
		$this->database = 'tests_migrations_database_helpers_' . uniqid();
		$this->dibi->query('DROP DATABASE IF EXISTS %n', $this->database);
		$this->dibi->query('CREATE DATABASE %n', $this->database);
		$this->dibi->query('USE %n', $this->database);
		$this->dbHelpers = new DatabaseHelpers($this->dibi, 'lock_table');
	}

	protected function tearDown()
	{
		parent::tearDown();
		$this->dibi->query('DROP DATABASE %n', $this->database);
	}

	public function testSetup()
	{
		$this->dbHelpers->setup();

		Assert::same('utf8', $this->getSessionVariable('character_set_client'));
		Assert::same('utf8', $this->getSessionVariable('character_set_results'));
		Assert::same('utf8', $this->getSessionVariable('character_set_connection'));
		Assert::same('OFF', $this->getSessionVariable('foreign_key_checks'));
		Assert::same('SYSTEM', $this->getSessionVariable('time_zone'));

		// http://dev.mysql.com/doc/refman/5.7/en/server-sql-mode.html#sqlmode_traditional
		// NO_ENGINE_SUBSTITUTION since 5.6.6
		$expectedModes = [
			'STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES', 'NO_ZERO_IN_DATE', 'NO_ZERO_DATE',
			'ERROR_FOR_DIVISION_BY_ZERO', 'NO_AUTO_CREATE_USER' /*, 'NO_ENGINE_SUBSTITUTION'*/,
		];
		$activeModes = explode(',', $this->getSessionVariable('sql_mode'));
		foreach ($expectedModes as $mode)
		{
			Assert::contains($mode, $activeModes);
		}
	}

	public function testLockUnlock()
	{
		Assert::false($this->dbHelpers->isLocked());

		// 2x lock
		$this->dbHelpers->lock();
		Assert::true($this->dbHelpers->isLocked());
		Assert::exception([
			$this->dbHelpers, 'lock'
		], '\Migrations\Exceptions\LogicException', 'Trying to acquire already acquired lock.');

		// 2x unlock
		$this->dbHelpers->unlock();
		Assert::false($this->dbHelpers->isLocked());
		Assert::exception([
			$this->dbHelpers, 'unlock'
		], '\Migrations\Exceptions\LogicException', 'A lock must be acquired before it can be released.');

		// lock failure
		$this->dibi->query('CREATE TABLE [lock_table] ([id] INT)');
		Assert::exception([
			$this->dbHelpers, 'lock'
		], '\Migrations\Exceptions\LockException', 'Unable to acquire a lock.');
		$this->dibi->query('DROP TABLE [lock_table]');

		// unlock failure
		$this->dbHelpers->lock();
		$this->dibi->query('DROP TABLE [lock_table]');
		Assert::exception([
			$this->dbHelpers, 'unlock'
		], '\Migrations\Exceptions\LockException', 'Unable to release lock, because it has been already released.');
		$this->dibi->query('CREATE TABLE [lock_table] ([id] INT)');
		$this->dbHelpers->unlock();
	}


	public function testWipeDatabase()
	{
		$this->createDummyStructures();
		$this->dbHelpers->wipeDatabase();
		$this->createDummyStructures();
	}

	private function getSessionVariable($name)
	{
		$var = $this->dibi->fetch('SHOW VARIABLES LIKE %s', $name);
		return $var['Value'];
	}

	private function createDummyStructures()
	{
		$this->dibi->query('CREATE TABLE [foo] ([id] INT)');
		$this->dibi->query('CREATE VIEW [bar] AS SELECT * FROM [foo]');
	}

}

run(new DatabaseHelpersTest($dibiConnection));

<?php
/**
 * Test: Migrations\Engine\Finder
 *
 * @testCase
 */

namespace Migrations\Tests;

use DibiConnection;
use Migrations\Engine\MigrationsTable;
use Migrations\Entities\File;
use Migrations\Entities\Group;
use Mockery;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../dibi-connect.php';

class MigrationsTableTest extends TestCase
{

	/** @var \DibiConnection */
	private $dibi;

	/** @var MigrationsTable */
	private $table;

	/** @var string */
	private $database;

	public function __construct(DibiConnection $dibi)
	{
		$this->dibi = $dibi;
	}

	protected function setUp()
	{
		parent::setUp();
		$this->database = 'tests_migrations_table_' . uniqid();
		$this->dibi->query('DROP DATABASE IF EXISTS %n', $this->database);
		$this->dibi->query('CREATE DATABASE %n', $this->database);
		$this->dibi->query('USE %n', $this->database);
		$this->table = new MigrationsTable($this->dibi, 'mig');
	}

	protected function tearDown()
	{
		parent::tearDown();
		$this->dibi->query('DROP DATABASE %n', $this->database);
	}

	public function testGetName()
	{
		Assert::same('mig', $this->table->getName());
	}

	public function testCreate()
	{
		$this->table->create();
		$this->table->create(); // can be called twice!
		$this->dibi->query('SELECT * FROM [mig]');
	}

	public function testDrop()
	{
		$this->table->create();
		$this->table->drop();
		$this->dibi->query('CREATE TABLE [mig] ([id] INT)');
	}

	public function testLogMigrationBeforeStart()
	{
		$file = $this->createDummyFile();
		$this->table->create();

		$id = $this->table->logMigrationBeforeStart($file);
		Assert::type('int', $id);

		$row = $this->dibi->fetch('SELECT * FROM [mig] WHERE [id] = %i', $id);
		Assert::same('g', $row->group);
		Assert::same('a.sql', $row->file);
		Assert::same(0, $row->ready);
	}

	public function testLogMigrationCompleted()
	{
		$file = $this->createDummyFile();
		$this->table->create();
		$id = $this->table->logMigrationBeforeStart($file);

		$this->table->logMigrationCompleted($id);
		Assert::same(1, $this->dibi->fetchSingle('SELECT [ready] FROM [mig] WHERE [id] = %i', $id));
	}

	public function testGetAllRecords()
	{
		$file = $this->createDummyFile();
		$this->table->create();
		$id = $this->table->logMigrationBeforeStart($file);

		$records = $this->table->getAllRecords();
		Assert::same(1, count($records));
		Assert::type('Migrations\Entities\ExecutionRecord', $records[0]);
		Assert::same($id, $records[0]->id);
		Assert::same(FALSE, $records[0]->completed);
	}

	private function createDummyFile()
	{
		$group = new Group();
		$group->name = 'g';

		$file = new File();
		$file->group = $group;
		$file->name = 'a.sql';
		$file->checksum = 'a.md5';

		return $file;
	}

}

run(new MigrationsTableTest($dibiConnection));

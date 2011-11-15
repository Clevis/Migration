<?php

use Migration\Runner;

/**
 * @covers Migration\Runner
 * @covers Migration\MigrationSqlFile
 * @author Petr Procházka
 */
class Runner_run_Test extends TestCase
{
	private $dibi;
	private $driver;
	private $printer;
	private $runner;

	protected function setUp()
	{
		parent::setUp();
		$driver = Mockery::mock('IDibiDriver, IDibiResultDriver, IDibiReflector');
		class_alias(get_class($driver), 'Dibi' . get_class($driver) . 'Driver');
		$dibi = new DibiConnection(array('driver' => get_class($driver), 'lazy' => true));
		Access($dibi)->driver = $driver;
		$printer = Mockery::mock('Migration\IPrinter');

		$driver->shouldReceive('connect')->atMost()->once();
		$driver->shouldReceive('disconnect')->atMost()->once();
		$driver->shouldReceive('escape')->andReturnUsing(function ($value, $type) {
			switch ($type) {
			case dibi::TEXT:
				return "'$value'";
			case dibi::IDENTIFIER:
				return '`' . str_replace('`', '``', $value) . '`';
			case dibi::BOOL:
				return $value ? 1 : 0;
			case dibi::DATE:
				return $value instanceof DateTime ? $value->format("'Y-m-d'") : date("'Y-m-d'", $value);
			case dibi::DATETIME:
				return $value instanceof DateTime ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);
			}
			throw new InvalidArgumentException('Unsupported type.');
		});

		$this->dibi = $dibi;
		$this->driver = $driver;
		$this->printer = $printer;
		$this->runner = Access(new Runner($this->dibi, $this->printer));
	}

	private function qar($sql, $r = 0)
	{
		$this->driver->shouldReceive('query')->with($sql)->once()->ordered();
		$this->driver->shouldReceive('getAffectedRows')->withNoArgs()->andReturn($r)->once()->ordered();
	}

	private function qr($sql, array $data)
	{
		$this->driver->shouldReceive('query')->with($sql)->andReturn($this->driver)->once()->ordered();
		foreach ($data as $row)
		{
			$this->driver->shouldReceive('fetch')->with(true)->andReturn($row)->once()->ordered();
		}
		$this->driver->shouldReceive('fetch')->with(true)->andReturn()->once()->ordered();
	}

	public function testNotReset()
	{
		$runner = new Runner_Mock($this->dibi, $this->printer);
		$runner->sql = new Migration\MigrationSqlFile(__FILE__);

		$this->printer->shouldReceive('printToExecute')->with(array($runner->sql))->once()->ordered();
		$this->printer->shouldReceive('printExecute')->with($runner->sql, 5)->once()->ordered();
		$this->printer->shouldReceive('printDone')->withNoArgs()->once()->ordered();

		$runner->run(__DIR__ , false);

		$this->assertSame(array(
			array('runSetup'),
			array('runInitMigrationTable'),
			array('getAllMigrations'),
			array('getAllFiles', __DIR__),
			array('getToExecute', array(), array(__FILE__ => $runner->sql)),
			array('execute', $runner->sql),
		), $runner->log);
	}

	public function testReset()
	{
		$runner = new Runner_Mock($this->dibi, $this->printer);
		$runner->sql = new Migration\MigrationSqlFile(__FILE__);

		$this->printer->shouldReceive('printReset')->withNoArgs()->once()->ordered();
		$this->printer->shouldReceive('printToExecute')->with(array($runner->sql))->once()->ordered();
		$this->printer->shouldReceive('printExecute')->with($runner->sql, 5)->once()->ordered();
		$this->printer->shouldReceive('printDone')->withNoArgs()->once()->ordered();

		$runner->run(__DIR__ , true);

		$this->assertSame(array(
			array('runSetup'),
			array('runWipe'),
			array('runInitMigrationTable'),
			array('getAllMigrations'),
			array('getAllFiles', __DIR__),
			array('getToExecute', array(), array(__FILE__ => $runner->sql)),
			array('execute', $runner->sql),
		), $runner->log);
	}

	public function testError()
	{
		$runner = new Runner_Mock($this->dibi, $this->printer);
		$runner->error = true;

		$test = $this;
		$this->printer->shouldReceive('printError')->with(new Mockery\Matcher\Closure(function ($e) use ($test) {
			$test->assertInstanceOf('Migration\Exception', $e);
			$test->assertSame('foo bar', $e->getMessage());
			return true;
		}))->once()->ordered();

		$runner->run(__DIR__ , false);

		$this->assertSame(array(), $runner->log);
	}

	public function testRunSetup()
	{
		$this->qar('SET NAMES utf8');
		$this->qar('SET foreign_key_checks = 0');
		$this->qar("SET time_zone = 'SYSTEM'");
		$this->qar("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
		$this->runner->runSetup();
		$this->assertTrue(true);
	}

	public function testRunWipe()
	{
		$this->driver->shouldReceive('getReflector')->withNoArgs()->andReturn($this->driver)->once()->ordered();
		$this->driver->shouldReceive('getTables')->withNoArgs()->andReturn(array(
			array('name' => 'foo'),
			array('name' => 'bar'),
			array('name' => 'migrations'),
		))->once()->ordered();
		$this->qar('DROP TABLE `foo`');
		$this->qar('DROP TABLE `bar`');
		$this->qar('DROP TABLE `migrations`');
		$this->runner->runWipe();
		$this->assertTrue(true);
	}

	public function testRunInitMigrationTable()
	{
		$this->qar("CREATE TABLE IF NOT EXISTS `migrations` (
			`id` bigint NOT NULL AUTO_INCREMENT,
			`file` text NOT NULL,
			`checksum` text NOT NULL,
			`executed` datetime NOT NULL,
			`ready` smallint(1) NOT NULL default 0,
			PRIMARY KEY (`id`)
		) ENGINE='InnoDB'");
		$this->runner->runInitMigrationTable();
		$this->assertTrue(true);
	}

	public function testGetAllMigrations()
	{
		$this->qr("SELECT * FROM `migrations`", array(
			array(
				'id' => 1,
				'file' => 'file',
				'checksum' => 'checksum',
				'executed' => '2011-11-11',
				'ready' => 1,
			),
			array(
				'id' => 2,
				'file' => 'file2',
				'checksum' => 'checksum2',
				'executed' => '2011-11-12',
				'ready' => 1,
			),
		));
		$r = $this->runner->getAllMigrations();

		$this->assertEquals(array(
			'file' => new DibiRow(array(
				'id' => 1,
				'file' => 'file',
				'checksum' => 'checksum',
				'executed' => '2011-11-11',
				'ready' => 1,
			)),
			'file2' => new DibiRow(array(
				'id' => 2,
				'file' => 'file2',
				'checksum' => 'checksum2',
				'executed' => '2011-11-12',
				'ready' => 1,
			)),
		), $r);
	}

	public function testGetAllFiles()
	{
		$tmp = realpath(TEMP_DIR);
		file_put_contents("$tmp/_1.sql", '1');
		file_put_contents("$tmp/_2.sql", '2');
		$a = realpath("$tmp/_1.sql");
		$b = realpath("$tmp/_2.sql");

		$r = $this->runner->getAllFiles($tmp);

		$this->assertSame(array($a, $b), array_keys($r));

		$this->assertSame('_1.sql', $r[$a]->file);
		$this->assertSame('_2.sql', $r[$b]->file);
		$this->assertSame('c4ca4238a0b923820dcc509a6f75849b', $r[$a]->checksum);
		$this->assertSame('c81e728d9d4c2f636f067f89cc14862c', $r[$b]->checksum);
		$tmp = new DateTime('now');
		$this->assertSame($tmp->format('c'), $r[$a]->executed->format('c'));
		$tmp = new DateTime('now');
		$this->assertSame($tmp->format('c'), $r[$b]->executed->format('c'));
		$this->assertSame($a, $r[$a]->path);
		$this->assertSame($b, $r[$b]->path);

		unlink($a);
		unlink($b);
		return array($a, $b, $r);
	}

	public function testGetToExecute()
	{
		list($a, $b, $files) = $this->testGetAllFiles();

		$r = $this->runner->getToExecute(array(
			'_1.sql' => new DibiRow(array(
				'id' => 1,
				'file' => '_1.sql',
				'checksum' => 'c4ca4238a0b923820dcc509a6f75849b',
				'executed' => '2011-11-11',
				'ready' => 1,
			)),
		), $files);

		$this->assertSame(1, count($r));
		$this->assertSame(array($files[$b]), $r);
	}

	public function testGetToExecuteRemove()
	{
		list($a, $b, $files) = $this->testGetAllFiles();

		$this->setExpectedException('Migration\Exception', '_3.sql se smazal.');
		$this->runner->getToExecute(array(
			'_3.sql' => new DibiRow(array(
				'id' => 1,
				'file' => '_3.sql',
				'checksum' => 'c4ca4238a0b923820dcc509a6f75849b',
				'executed' => '2011-11-11',
				'ready' => 1,
			)),
		), $files);
	}

	public function testGetToExecuteChange()
	{
		list($a, $b, $files) = $this->testGetAllFiles();

		$this->setExpectedException('Migration\Exception', '_1.sql se zmenil.');
		$this->runner->getToExecute(array(
			'_1.sql' => new DibiRow(array(
				'id' => 1,
				'file' => '_1.sql',
				'checksum' => 'bbb',
				'executed' => '2011-11-11',
				'ready' => 1,
			)),
		), $files);
	}

	public function testGetToExecuteNotReady()
	{
		list($a, $b, $files) = $this->testGetAllFiles();

		$this->setExpectedException('Migration\Exception', '_1.sql se nedokoncil.');
		$this->runner->getToExecute(array(
			'_1.sql' => new DibiRow(array(
				'id' => 1,
				'file' => '_1.sql',
				'checksum' => 'c4ca4238a0b923820dcc509a6f75849b',
				'executed' => '2011-11-11',
				'ready' => 0,
			)),
		), $files);
	}

	public function testExecute()
	{
		list($a, $b, $files) = $this->testGetAllFiles();

		$this->driver->shouldReceive('begin')->with(NULL)->andReturn()->once()->ordered();
		$this->qar("INSERT INTO `migrations` (`id`, `file`, `checksum`, `executed`) VALUES (NULL, '_1.sql', 'c4ca4238a0b923820dcc509a6f75849b', " . $this->driver->escape(new DateTime('now'), Dibi::DATETIME) . ")");
		$this->driver->shouldReceive('getInsertId')->with(NULL)->andReturn(123)->once()->ordered();

		$this->driver->shouldReceive('query')->with("\n\t\t\tSELECT foobar;\n")->once()->ordered();
		$this->driver->shouldReceive('query')->with("\t\t\tDO WHATEVER;\n")->once()->ordered();
		$this->driver->shouldReceive('query')->with("\t\t\tHELLO;\n")->once()->ordered();

		$this->qar("UPDATE `migrations` SET `ready`=1 WHERE `id` = '123'");
		$this->driver->shouldReceive('commit')->with(NULL)->andReturn()->once()->ordered();

		file_put_contents($a, '
			SELECT foobar;
			DO WHATEVER;
			HELLO;
		');
		$count = $this->runner->execute($files[$a]);
		unlink($a);
		$this->assertSame(3, $count);
	}

}

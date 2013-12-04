<?php
/**
 * Test: Migrations\Entities\Migration
 *
 * @testCase
 */

namespace Migrations\Tests;

use Migrations\Entities\Migration;
use Mockery;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


class MigrationTest extends TestCase
{

	public function test()
	{
		$file = Mockery::mock('Migrations\Entities\File');
		$handler = Mockery::mock('Migrations\IExtensionHandler');
		$handler->shouldReceive('execute')->once()->with($file)->andReturn(123);

		$migration = new Migration($file, $handler);

		Assert::same($file, $migration->getFile());
		Assert::same($handler, $migration->getHandler());
		Assert::same(123, $migration->execute());
	}

}

run(new MigrationTest);

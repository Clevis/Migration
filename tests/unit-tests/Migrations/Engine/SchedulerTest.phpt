<?php
/**
 * Test: Migrations\Engine\Scheduler
 *
 * @testCase
 */

namespace Migrations\Tests;

use Migrations\Engine\Scheduler;
use Migrations\Entities\ExecutionRecord;
use Migrations\Entities\File;
use Migrations\Entities\Group;
use Migrations\IExtensionHandler;
use Mockery;
use Tester;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


class SchedulerTest extends Tester\TestCase
{

	/** @var IExtensionHandler */
	private $handler;

	protected function setUp()
	{
		parent::setUp();
		$this->handler = Mockery::mock('Migrations\IExtensionHandler');
	}

	public function testFirstRun()
	{
		$groupA = $this->createGroup('structures');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		$scheduler = $this->createScheduler([], [$fileB, $fileA], [$groupA]);

		// 1s* 2s*
		$this->assertOrder([$fileA, $fileB], $scheduler->getSchedule(['structures'], $scheduler::MODE_CONTINUE));
	}

	public function testFirstRunTwoGroups()
	{
		$groupA = $this->createGroup('1g');
		$groupB = $this->createGroup('2g');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupB);
		$fileC = $this->createFile('3s', $groupA);

		$scheduler = $this->createScheduler([], [$fileC, $fileB, $fileA], [$groupA, $groupB]);

		// 1s* 2s* 3s*
		$this->assertOrder([$fileA, $fileB, $fileC], $scheduler->getSchedule(['1g', '2g'], $scheduler::MODE_CONTINUE));
	}

	public function testSecondRunContinue()
	{
		$groupA = $this->createGroup('structures');
		$recordA = $this->createExecutionRecord($groupA->name, '1s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		$scheduler = $this->createScheduler([$recordA], [$fileB, $fileA], [$groupA]);

		// 1s 2s*
		$this->assertOrder([$fileB], $scheduler->getSchedule(['structures'], $scheduler::MODE_CONTINUE));
	}

	public function testSecondRunContinueNothingToDo()
	{
		$groupA = $this->createGroup('structures');
		$recordA = $this->createExecutionRecord($groupA->name, '1s');
		$recordB = $this->createExecutionRecord($groupA->name, '2s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		$scheduler = $this->createScheduler([$recordA, $recordB], [$fileB, $fileA], [$groupA]);

		// 1s 2s
		$this->assertOrder([], $scheduler->getSchedule(['structures'], $scheduler::MODE_CONTINUE));
	}

	public function testSecondRunContinueTwoGroups()
	{
		$groupA = $this->createGroup('structures');
		$groupB = $this->createGroup('data');

		$recordA = $this->createExecutionRecord($groupA->name, '1s');
		$recordB = $this->createExecutionRecord($groupB->name, '2d');

		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2d', $groupB);
		$fileC = $this->createFile('3s', $groupA);
		$fileD = $this->createFile('4d', $groupB);

		$scheduler = $this->createScheduler([$recordB, $recordA], [$fileB, $fileA, $fileD, $fileC], [$groupA, $groupB]);

		// 1s 2d 3s* 4d*
		$this->assertOrder([$fileC, $fileD], $scheduler->getSchedule(['structures', 'data'], $scheduler::MODE_CONTINUE));
	}

	public function testSecondRunContinueDisabledGroup()
	{
		$groupA = $this->createGroup('structures');
		$groupB = $this->createGroup('data');

		$recordA = $this->createExecutionRecord($groupA->name, '1s');
		$recordB = $this->createExecutionRecord($groupB->name, '2d');

		$fileA = $this->createFile('1s', $groupA);
		$fileD = $this->createFile('4s', $groupA);

		$scheduler = $this->createScheduler([$recordB, $recordA], [$fileD, $fileA], [$groupA, $groupB]);

		// 1s 2d 3d* 4s*
		$this->assertOrder([$fileD], $scheduler->getSchedule(['structures'], $scheduler::MODE_CONTINUE));
	}

	public function testSecondRunReset()
	{
		$groupA = $this->createGroup('structures');
		$recordA = $this->createExecutionRecord($groupA->name, '1s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		$scheduler = $this->createScheduler([$recordA], [$fileB, $fileA], [$groupA]);

		$this->assertOrder([$fileA, $fileB], $scheduler->getSchedule(['structures'], $scheduler::MODE_RESET));
	}

	public function testErrorRemovedFile()
	{
		$groupA = $this->createGroup('structures');
		$recordA = $this->createExecutionRecord($groupA->name, '1s');
		$fileB = $this->createFile('2s', $groupA);

		$scheduler = $this->createScheduler([$recordA], [$fileB], [$groupA]);

		// 1s 2s*
		Assert::exception(function () use ($scheduler) {
			$scheduler->getSchedule(['structures'], $scheduler::MODE_CONTINUE);
		}, 'Migrations\Exceptions\LogicException', 'Previously executed migration "structures/1s" no longer exists. You MUST never delete a migration.');
	}

	public function testErrorChangedChecksum()
	{
		$groupA = $this->createGroup('structures');
		$recordA = $this->createExecutionRecord($groupA->name, '1s', '1s.md5.X');
		$fileA = $this->createFile('1s', $groupA, '1s.md5.Y');
		$fileB = $this->createFile('2s', $groupA);

		$scheduler = $this->createScheduler([$recordA], [$fileB, $fileA], [$groupA]);

		// 1s 2s*
		Assert::exception(function () use ($scheduler) {
			$scheduler->getSchedule(['structures'], $scheduler::MODE_CONTINUE);
		}, 'Migrations\Exceptions\LogicException', 'Previously executed migration "structures/1s" has been changed. You MUST never change a migration.');
	}

	public function testErrorIncompleteMigration()
	{
		$groupA = $this->createGroup('structures');
		$recordA = $this->createExecutionRecord($groupA->name, '1s', NULL, FALSE);
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		$scheduler = $this->createScheduler([$recordA], [$fileB, $fileA], [$groupA]);

		// 1s 2s*
		Assert::exception(function () use ($scheduler) {
			$scheduler->getSchedule(['structures'], $scheduler::MODE_CONTINUE);
		}, 'Migrations\Exceptions\LogicException', 'Previously executed migration "structures/1s" did not succeed. Please fix this manually or reset the migrations.');
	}

	public function testErrorNewMigrationInTheMiddleOfExistingOnes()
	{
		$groupA = $this->createGroup('structures');
		$recordA = $this->createExecutionRecord($groupA->name, '1s');
		$recordC = $this->createExecutionRecord($groupA->name, '3s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);
		$fileC = $this->createFile('3s', $groupA);

		$scheduler = $this->createScheduler([$recordC, $recordA], [$fileA, $fileB, $fileC], [$groupA]);

		// 1s 2s* 3s
		Assert::exception(function () use ($scheduler) {
			$scheduler->getSchedule(['structures'], $scheduler::MODE_CONTINUE);
		}, 'Migrations\Exceptions\LogicException', 'New migration "structures/2s" must follow after the latest executed migration "structures/3s".');
	}

	public function testErrorMigrationDependingOnUnknownGroup()
	{
		$recordA = $this->createExecutionRecord('foo', '1s');

		$scheduler = $this->createScheduler([$recordA], [], []);

		Assert::exception(function () use ($scheduler) {
			$scheduler->getSchedule([], $scheduler::MODE_CONTINUE);
		}, 'Migrations\Exceptions\LogicException', 'Previously executed migration depends on unknown group "foo".');
	}

	public function testErrorGroupDependingOnUnknownGroup()
	{
		$groupB = $this->createGroup('data', ['structures']);

		$scheduler = $this->createScheduler([], [], [$groupB]);

		Assert::exception(function () use ($scheduler) {
			$scheduler->getSchedule(['data'], $scheduler::MODE_CONTINUE);
		}, 'Migrations\Exceptions\LogicException', 'Group "data" depends on unknown group "structures".');
	}

	public function testErrorDisablingRequiredGroup()
	{
		$groupA = $this->createGroup('structures');
		$groupB = $this->createGroup('data', ['structures']);

		$scheduler = $this->createScheduler([], [], [$groupA, $groupB]);

		Assert::exception(function () use ($scheduler) {
			$scheduler->getSchedule(['data'], $scheduler::MODE_CONTINUE);
		}, 'Migrations\Exceptions\LogicException', 'Group "data" depends on disabled group "structures". Please enable group "structures" to continue.');
	}

	private function assertOrder(array $expected, array $actual)
	{
		Assert::same(count($expected), count($actual));
		for ($i = 0; $i < count($actual); $i++)
		{
			Assert::type('Migrations\Entities\Migration', $actual[$i]);
			Assert::same($expected[$i], $actual[$i]->getFile());
			Assert::same($this->handler, $actual[$i]->getHandler());
		}
	}

	private function createScheduler(array $records, array $files, array $groups)
	{
		$log = Mockery::mock('Migrations\Engine\IExecutionLog');
		$log->shouldReceive('getAllRecords')->once()->andReturn($records);
		$finder = Mockery::mock('Migrations\Engine\Finder');
		$finder->shouldReceive('find')->once()->andReturn($files);

		return new Scheduler($log, $finder, $groups, array('sql' => $this->handler));
	}

	protected function createExecutionRecord($groupName, $fileName, $checksum = NULL, $completed = TRUE)
	{
		$record = new ExecutionRecord();
		$record->group = $groupName;
		$record->filename = $fileName;
		$record->checksum = $checksum ? : "$fileName.md5";
		$record->completed = $completed;
		return $record;
	}

	protected function createFile($name, $group, $checksum = NULL)
	{
		$file = new File();
		$file->group = $group;
		$file->name = $name;
		$file->extension = 'sql';
		$file->checksum = $checksum ? : "$name.md5";
		return $file;
	}

	protected function createGroup($name, $dependencies = [])
	{
		$group = new Group();
		$group->name = $name;
		$group->dependencies = $dependencies;
		return $group;
	}

}

run(new SchedulerTest);

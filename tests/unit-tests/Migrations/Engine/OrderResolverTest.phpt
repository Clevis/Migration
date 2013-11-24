<?php
/**
 * Test: Migrations\Engine\OrderResolver
 *
 * @testCase
 */

namespace Migrations\Tests;

use Migrations\Engine\OrderResolver;
use Migrations\Entities\ExecutionRecord;
use Migrations\Entities\File;
use Migrations\Entities\Group;
use Mockery;
use Tester;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


class OrderResolverTest extends Tester\TestCase
{

	public function testFirstRun()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('structures');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		// 1s* 2s*
		Assert::same([$fileA, $fileB], $resolver->resolve(
			[],
			[$groupA],
			[$fileB, $fileA],
			$resolver::MODE_CONTINUE
		));
	}

	public function testFirstRunTwoGroups()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('1g');
		$groupB = $this->createGroup('2g');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupB);
		$fileC = $this->createFile('3s', $groupA);

		// 1s* 2s* 3s*
		Assert::same([$fileA, $fileB, $fileC], $resolver->resolve(
			[],
			[$groupA, $groupB],
			[$fileC, $fileB, $fileA],
			$resolver::MODE_CONTINUE
		));
	}

	public function testSecondRunContinue()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createExecutionRecord($groupA->name, '1s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		// 1s 2s*
		Assert::same([$fileB], $resolver->resolve(
			[$migrationA],
			[$groupA],
			[$fileB, $fileA],
			$resolver::MODE_CONTINUE
		));
	}

	public function testSecondRunContinueNothingToDo()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createExecutionRecord($groupA->name, '1s');
		$migrationB = $this->createExecutionRecord($groupA->name, '2s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		// 1s 2s
		Assert::same([], $resolver->resolve(
			[$migrationA, $migrationB],
			[$groupA],
			[$fileB, $fileA],
			$resolver::MODE_CONTINUE
		));
	}

	public function testSecondRunContinueTwoGroups()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('structures');
		$groupB = $this->createGroup('data');

		$migrationA = $this->createExecutionRecord($groupA->name, '1s');
		$migrationB = $this->createExecutionRecord($groupB->name, '2d');

		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2d', $groupB);
		$fileC = $this->createFile('3s', $groupA);
		$fileD = $this->createFile('4d', $groupB);

		// 1s 2d 3s* 4d*
		Assert::same([$fileC, $fileD], $resolver->resolve(
			[$migrationB, $migrationA],
			[$groupA, $groupB],
			[$fileB, $fileA, $fileD, $fileC],
			$resolver::MODE_CONTINUE
		));
	}

	public function testSecondRunContinueDisabledGroup()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('structures');
		$groupB = $this->createGroup('data', FALSE);

		$migrationA = $this->createExecutionRecord($groupA->name, '1s');
		$migrationB = $this->createExecutionRecord($groupB->name, '2d');

		$fileA = $this->createFile('1s', $groupA);
		$fileD = $this->createFile('4s', $groupA);

		// 1s 2d 3d* 4s*
		Assert::same([$fileD], $resolver->resolve(
			[$migrationB, $migrationA],
			[$groupA, $groupB],
			[$fileD, $fileA],
			$resolver::MODE_CONTINUE
		));
	}

	public function testSecondRunReset()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createExecutionRecord($groupA->name, '1s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		Assert::same([$fileA, $fileB], $resolver->resolve(
			[$migrationA],
			[$groupA],
			[$fileB, $fileA],
			$resolver::MODE_RESET
		));
	}

	public function testErrorRemovedFile()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createExecutionRecord($groupA->name, '1s');
		$fileB = $this->createFile('2s', $groupA);

		// 1s 2s*
		Assert::exception(function () use ($resolver, $groupA, $migrationA, $fileB)
		{
			$resolver->resolve(
				[$migrationA],
				[$groupA],
				[$fileB],
				$resolver::MODE_CONTINUE
			);
		}, 'Migrations\Exceptions\LogicException', 'Previously executed migration "structures/1s" is missing.');
	}

	public function testErrorChangedChecksum()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createExecutionRecord($groupA->name, '1s', '1s.md5.X');
		$fileA = $this->createFile('1s', $groupA, '1s.md5.Y');
		$fileB = $this->createFile('2s', $groupA);

		// 1s 2s*
		Assert::exception(function () use ($resolver, $groupA, $migrationA, $fileA, $fileB)
		{
			$resolver->resolve(
				[$migrationA],
				[$groupA],
				[$fileB, $fileA],
				$resolver::MODE_CONTINUE
			);
		}, 'Migrations\Exceptions\LogicException', 'Previously executed migration "structures/1s" has been changed.');
	}

	public function testErrorIncompleteMigration()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createExecutionRecord($groupA->name, '1s', NULL, FALSE);
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		// 1s 2s*
		Assert::exception(function () use ($resolver, $groupA, $migrationA, $fileA, $fileB)
		{
			$resolver->resolve(
				[$migrationA],
				[$groupA],
				[$fileB, $fileA],
				$resolver::MODE_CONTINUE
			);
		}, 'Migrations\Exceptions\LogicException', 'Previously executed migration "structures/1s" did not succeed. Please fix this manually or reset the migrations.');
	}

	public function testErrorNewMigrationInTheMiddleOfExistingOnes()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createExecutionRecord($groupA->name, '1s');
		$migrationC = $this->createExecutionRecord($groupA->name, '3s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);
		$fileC = $this->createFile('3s', $groupA);

		// 1s 2s* 3s
		Assert::exception(function () use ($resolver, $groupA, $migrationA, $migrationC, $fileA, $fileB, $fileC)
		{
			$resolver->resolve(
				[$migrationC, $migrationA],
				[$groupA],
				[$fileA, $fileB, $fileC],
				$resolver::MODE_CONTINUE
			);
		}, 'Migrations\Exceptions\LogicException', 'New migration "structures/2s" must follow after the latest executed migration "structures/3s".');
	}

	public function testErrorMigrationDependingOnUnknownGroup()
	{
		$resolver = new OrderResolver();

		$migrationA = $this->createExecutionRecord('foo', '1s');

		Assert::exception(function () use ($resolver, $migrationA)
		{
			$resolver->resolve(
				[$migrationA],
				[],
				[],
				$resolver::MODE_CONTINUE
			);
		}, 'Migrations\Exceptions\LogicException', 'Existing migrations depend on unknown group "foo".');
	}

	public function testErrorGroupDependingOnUnknownGroup()
	{
		$resolver = new OrderResolver();

		$groupB = $this->createGroup('data', TRUE, ['structures']);

		Assert::exception(function () use ($resolver, $groupB)
		{
			$resolver->resolve(
				[],
				[$groupB],
				[],
				$resolver::MODE_CONTINUE
			);
		}, 'Migrations\Exceptions\LogicException', 'Group "data" depends on unknown group "structures".');
	}

	public function testErrorDisablingRequiredGroup()
	{
		$resolver = new OrderResolver();

		$groupA = $this->createGroup('structures', FALSE);
		$groupB = $this->createGroup('data', TRUE, ['structures']);

		Assert::exception(function () use ($resolver, $groupA, $groupB)
		{
			$resolver->resolve(
				[],
				[$groupA, $groupB],
				[],
				$resolver::MODE_CONTINUE
			);
		}, 'Migrations\Exceptions\LogicException', 'Group "data" depends on disabled group "structures". Please enable group "structures" to continue.');
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
		$file->name = $name;
		$file->group = $group;
		$file->checksum = $checksum ? : "$name.md5";
		return $file;
	}

	protected function createGroup($name, $enabled = TRUE, $dependencies = [])
	{
		$group = new Group();
		$group->name = $name;
		$group->enabled = $enabled;
		$group->dependencies = $dependencies;
		return $group;
	}

}

run(new OrderResolverTest);

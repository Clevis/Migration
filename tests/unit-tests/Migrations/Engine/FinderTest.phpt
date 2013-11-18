<?php
/**
 * Test: Migrations\Engine\Finder
 *
 * @testCase
 */

namespace Migrations\Tests;

use Migrations\Entities\File;
use Migrations\Entities\Group;
use Mockery;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


class FinderTest extends TestCase
{

	/** @var \Migrations\Engine\Finder|\Mockery\MockInterface */
	private $finder;

	/** @var Group */
	private $groupA;

	/** @var Group */
	private $groupB;

	protected function setUp()
	{
		parent::setUp();

		$this->finder = Mockery::mock('Migrations\Engine\Finder[getFiles,getChecksum]');
		$this->finder->shouldAllowMockingProtectedMethods();
		$this->finder->shouldReceive('getChecksum')->andReturnUsing(function (File $file) {
			return "{$file->name}.md5";
		});

		$this->groupA = new Group();
		$this->groupA->enabled = TRUE;
		$this->groupA->directory = '/m/g1';

		$this->groupB = new Group();
		$this->groupB->enabled = TRUE;
		$this->groupB->directory = '/m/g2';
	}


	public function testFind()
	{
		$this->finder->shouldReceive('getFiles')->with('/m/g1')->once()->andReturn(['.', '..', 'a.sql', 'b.php']);
		$this->finder->shouldReceive('getFiles')->with('/m/g2')->once()->andReturn(['.', '..', 'c.sql']);

		$files = $this->finder->find([$this->groupA, $this->groupB], ['sql', 'php']);

		Assert::same(3, count($files));

		Assert::same($this->groupA, $files[0]->group);
		Assert::same('a.sql', $files[0]->name);
		Assert::same('sql', $files[0]->extension);
		Assert::same('a.sql.md5', $files[0]->checksum);

		Assert::same($this->groupA, $files[1]->group);
		Assert::same('b.php', $files[1]->name);
		Assert::same('php', $files[1]->extension);
		Assert::same('b.php.md5', $files[1]->checksum);

		Assert::same($this->groupB, $files[2]->group);
		Assert::same('c.sql', $files[2]->name);
		Assert::same('sql', $files[2]->extension);
		Assert::same('c.sql.md5', $files[2]->checksum);
	}

	public function testFindErrorUnknownExtension()
	{
		$this->finder->shouldReceive('getFiles')->with('/m/g1')->once()->andReturn(['a.xxx']);

		Assert::exception(function () {
			$this->finder->find([$this->groupA], ['sql', 'php']);
		}, 'Migrations\Exceptions\LogicException', 'Finder: No extension matched for file "/m/g1/a.xxx". Supported extensions are "sql", "php".');
	}

}

run(new FinderTest);

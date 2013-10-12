<?php
namespace Migration\Tests;

use Migration;


/**
 * @author Petr Procházka
 */
class RunnerMock extends Migration\Runner
{
	public $log = array();
	public $sql;
	public $error = false;

	protected function runSetup()
	{
		if ($this->error) throw new Migration\Exception('foo bar');
		$this->log[] = array(__FUNCTION__);
	}

	protected function runWipe()
	{
		$this->log[] = array(__FUNCTION__);
	}

	protected function runInitMigrationTable()
	{
		$this->log[] = array(__FUNCTION__);
	}

	protected function getAllMigrations()
	{
		$this->log[] = array(__FUNCTION__);
		return array();
	}

	protected function getAllFiles($dir)
	{
		$this->log[] = array(__FUNCTION__, $dir);
		return array(
			$this->sql->path => $this->sql,
		);
	}

	protected function getToExecute(array $migrations, array $files)
	{
		$this->log[] = array(__FUNCTION__, $migrations, $files);
		return array_values($files);
	}

	protected function execute(Migration\File $sql)
	{
		$this->log[] = array(__FUNCTION__, $sql);
		return 5;
	}

	protected function lock()
	{
		$this->log[] = array(__FUNCTION__);
	}

	public function unLock()
	{
		$this->log[] = array(__FUNCTION__);
	}

}

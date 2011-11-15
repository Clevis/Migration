<?php

use Migration\DumpPrinter;
use Migration\MigrationSqlFile;

/**
 * @covers Migration\DumpPrinter
 * @author Petr Procházka
 */
class DumpPrinter_Test extends TestCase
{
	public function testPrintReset()
	{
		$p = new DumpPrinter;
		ob_start();
		$p->printReset();
		$this->assertSame('<pre><h1>RESET</h1></pre>', ob_get_clean());
	}

	public function testPrintToExecute()
	{
		$p = new DumpPrinter;
		ob_start();
		$p->printToExecute(array());
		$this->assertSame('', ob_get_clean());
	}

	public function testPrintExecute()
	{
		$p = new DumpPrinter;
		ob_start();
		$p->printExecute(new MigrationSqlFile(__FILE__), 5);
		$this->assertSame('<pre><h1>DumpPrinter_Test.php; 5 queries</h1></pre>', ob_get_clean());
	}

	public function testPrintDone()
	{
		$p = new DumpPrinter;
		ob_start();
		$p->printDone();
		$this->assertSame('<pre><h1>OK</h1></pre>', ob_get_clean());
	}

	public function testPrintError()
	{
		$p = new DumpPrinter;
		ob_start();
		$e = new Migration\Exception('Foo bar.');
		try {
			$p->printError($e);
			$this->fail();
		} catch (Migration\Exception $ee) {}
		$this->assertSame('<pre><h1>ERROR: Foo bar.</h1></pre>', ob_get_clean());
		$this->assertSame($e, $ee);
	}

}

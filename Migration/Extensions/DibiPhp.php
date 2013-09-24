<?php

namespace Migration\Extensions;

use Migration\Extensions\name;
use Migration\Extensions\SimplePhp;

class DibiMigrationsExtension extends SimplePhp
{

	public function __construct(\DibiConnection $dibi)
	{
		parent::__construct(['dibi' => $dibi]);
	}

	public function getName()
	{
		return 'dibi.php';
	}

}

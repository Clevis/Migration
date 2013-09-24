<?php

namespace Migration\Extensions;

use Migration\Extensions\name;

class DibiPhp extends SimplePhp
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

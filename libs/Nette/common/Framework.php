<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 *
 * Copyright (c) 2004, 2011 David Grudl (http://davidgrudl.com)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Nette;

use Nette;



/**
 * The Nette Framework (http://nette.org)
 *
 * @author     David Grudl
 */
final class Framework
{

	/** Nette Framework version identification */
	const NAME = 'Nette Framework',
		VERSION = '2.0-beta',
		REVISION = 'e2bcd6c released on 2011-10-12';

	/** @var bool set to TRUE if your host has disabled function ini_set */
	public static $iAmUsingBadHost = FALSE;



	/**
	 * Static class - cannot be instantiated.
	 */
	final public function __construct()
	{
		throw new StaticClassException;
	}

}

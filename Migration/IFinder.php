<?php

namespace Migration;


/**
 * @author Petr Procházka
 */
interface IFinder
{

	/**
	 * @param array of extensionName
	 * @return array path => File
	 */
	public function find(array $extensions);

}

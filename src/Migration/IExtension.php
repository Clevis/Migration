<?php

namespace Migration;


/**
 * @author Petr Procházka
 */
interface IExtension
{

	/**
	 * Unique extension name.
	 * @return string
	 */
	public function getName();

	/**
	 * @param File
	 * @return int number of queries
	 */
	public function execute(File $file);

}

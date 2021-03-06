<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008, 2012 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Persistence;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
interface QueryExecutor
{

	/**
	 * @param Query $queryObject
	 * @return integer
	 */
	function count(Query $queryObject);



	/**
	 * @param Query $queryObject
	 * @return array
	 */
	function fetch(Query $queryObject);



	/**
	 * @param Query $queryObject
	 * @return object
	 */
	function fetchOne(Query $queryObject);

}

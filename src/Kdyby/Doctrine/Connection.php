<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008, 2012 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Doctrine;
use Doctrine;
use Kdyby;
use Nette;
use PDO;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Connection extends Doctrine\DBAL\Connection
{

	/**
	 * @param string $query
	 * @param array $params
	 * @param array $types
	 * @param \Doctrine\DBAL\Cache\QueryCacheProfile $qcp
	 * @return \Doctrine\DBAL\Driver\Statement
	 * @throws DBALException
	 */
	public function executeQuery($query, array $params = array(), $types = array(), Doctrine\DBAL\Cache\QueryCacheProfile $qcp = NULL)
	{
		try {
			return parent::executeQuery($query, $params, $types, $qcp);

		} catch (\Exception $e) {
			throw $this->resolveException($e);
		}
	}



	/**
	 * @param string $query
	 * @param array $params
	 * @param array $types
	 * @return int
	 * @throws DBALException
	 */
	public function executeUpdate($query, array $params = array(), array $types = array())
	{
		try {
			return parent::executeUpdate($query, $params, $types);

		} catch (\Exception $e) {
			throw $this->resolveException($e);
		}
	}



	/**
	 * @param string $statement
	 * @return int
	 * @throws DBALException
	 */
	public function exec($statement)
	{
		try {
			return parent::exec($statement);

		} catch (\Exception $e) {
			throw $this->resolveException($e);
		}

	}



	/**
	 * @return \Doctrine\DBAL\Driver\Statement|mixed
	 * @throws DBALException
	 */
	public function query()
	{
		$args = func_get_args();
		try {
			return call_user_func_array('parent::query', $args);

		} catch (\Exception $e) {
			throw $this->resolveException($e);
		}
	}



	/**
	 * Prepares an SQL statement.
	 *
	 * @param string $statement The SQL statement to prepare.
	 * @throws DBALException
	 * @return PDOStatement The prepared statement.
	 */
	public function prepare($statement)
	{
		$this->connect();

		try {
			$stmt = new PDOStatement($statement, $this);
		} catch (\Exception $ex) {
			throw $this->resolveException(Doctrine\DBAL\DBALException::driverExceptionDuringQuery($ex, $statement), $statement);
		}

		$stmt->setFetchMode(PDO::FETCH_ASSOC);

		return $stmt;
	}



	/**
	 * @internal
	 * @param \Exception $e
	 * @param string $query
	 * @param array $params
	 * @return DBALException
	 */
	public function resolveException(\Exception $e, $query = NULL, $params = array())
	{
		if ($e instanceof Doctrine\DBAL\DBALException && ($pe = $e->getPrevious()) instanceof \PDOException) {
			$info = $pe->errorInfo;

		} elseif ($e instanceof \PDOException) {
			$info = $e->errorInfo;

		} else {
			return new DBALException($e, $query, $params);
		}

		if ($this->getDriver() instanceof Doctrine\DBAL\Driver\PDOMySql\Driver) {
			if ($info[0] == 23000 && $info[1] == 1062) { // unique fail
				$columns = array();
				if (preg_match('~Duplicate entry .*? for key \'([^\']+)\'~', $info[2], $m)) {
					$key = $m[1];

					if (($table = self::resolveExceptionTable($e))
						&& ($indexes = $this->getSchemaManager()->listTableIndexes($table))
						&& isset($indexes[$key])) {
						$columns = $indexes[$key]->getColumns();
					}
				}

				return new DuplicateEntryException($e, $columns, $query, $params);

			} elseif ($info[0] == 23000 && $info[1] == 1048) { // notnull fail
				$column = NULL;
				if (preg_match('~Column \'([^\']+)\'~', $info[2], $m)) {
					$column = $m[1];
				}

				return new EmptyValueException($e, $column, $query, $params);
			}
		}

		return new DBALException($e, $query, $params);
	}



	/**
	 * @param \Exception $e
	 * @return string|NULL
	 */
	private static function resolveExceptionTable(\Exception $e)
	{
		if (!$e instanceof Doctrine\DBAL\DBALException) {
			return NULL;
		}

		list($caused) = $e->getTrace();

		if (!empty($caused['class']) && !empty($caused['function'])
			&& $caused['class'] === 'Doctrine\DBAL\DBALException'
			&& $caused['function'] === 'driverExceptionDuringQuery'
		) {
			if (preg_match('~(?:INSERT|UPDATE|REPLACE)(?:[A-Z_\s]*)`?([^\s`]+)`?\s*~', $caused['args'][1], $m)) {
				return $m[1];
			}
		}

		return NULL;
	}

}
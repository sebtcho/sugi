<?php namespace Sugi\Database;
/**
 * @package Sugi
 * @author  Plamen Popov <tzappa@gmail.com>
 * @license http://opensource.org/licenses/mit-license.php (MIT License)
 */

/**
 * PgSQL driver for Sugi\Database class
 */
class Pgsql implements IDatabase
{
	/**
	 * Cache of connection parameters
	 * @var array
	 */
	protected $params;

	/**
	 * pgsql connection handle
	 * @var object
	 */
	protected $dbHandle = null;

	function __construct(array $params)
	{
		if (!isset($params["handle"]) and empty($params["database"])) {
			throw new Exception("Database handle or database name required", "internal_error"); 
		}
		if (isset($params["handle"])) {
			$this->setHandle($params["handle"]);
		}

		$this->params = $params;
	}

	function open()
	{
		// if we have a pgsql database handle (connection) return it now
		if ($this->dbHandle) {
			return $this->dbHandle;
		}

		// supported connection params
		$keywords = array(
			"host" => "host", 
			"port" => "port", 
			"user" => "user",
			"pass" => "password",
			"database" => "dbname"
		);
		// creating connection string
		$conn = array();
		foreach ($keywords as $key => $keyword) {
			if (!empty($this->params[$key])) {
				$conn[] = "{$keyword}={$this->params[$key]}";
			}
		}
		$conn = implode(" ", $conn);

		// before connection we want to handle errors/warnings and convert them to Sugi\Database\Exceptions
		$old_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
			global $old_error_handler;
			// restoring error_handler
			restore_error_handler($old_error_handler);
			throw new Exception($errstr, "connection_error");
		});
		// establish connection
		$this->dbHandle = \pg_connect($conn);
		// restoring error_handler
		restore_error_handler($old_error_handler);

		return $this->dbHandle;
	}
	
	function close()
	{
		if (!$this->dbHandle) {
			return;
		}
		\pg_close($this->dbHandle);
		$this->dbHandle = null;
	}
	
	function escape($item)
	{
		return \pg_escape_string($this->dbHandle, $item);
	}
	
	/**
	 * Executes query
	 * 
	 * @param string SQL statement
	 * @return resource of type (pgsql result) or FALSE on failure
	 */
	function query($sql)
	{
		// additional warning is triggered, when the query is wrong
		return @\pg_query($this->dbHandle, $sql);
	}
	
	function fetch($res)
	{
		return \pg_fetch_assoc($res);
	}

	function affected($res)
	{
		return \pg_affected_rows($res);
	}
	
	function lastId()
	{
		$res = \pg_fetch_row(\pg_query("SELECT lastval()"));
		return $res[0];
	}
		
	function free($res)
	{
		\pg_free_result($res);
	}
	
	function getHandle()
	{
		return $this->dbHandle;
	}
	
	function error()
	{
		return \pg_last_error($this->dbHandle);
	}


	/*
	 * Custom methods
	 */
	
	/**
	 * Begin transaction
	 */
	public function begin()
	{
		return \pg_query($this->dbHandle, "BEGIN TRANSACTION");
	}

	/**
	 * Commit transaction
	 */
	public function commit()
	{
		return \pg_query($this->dbHandle, "COMMIT TRANSACTION");
	}
	
	/**
	 * Rollback transaction
	 */
	public function rollback()
	{
		return \pg_query($this->dbHandle, "ROLLBACK TRANSACTION");
	}

	protected function setHandle($handle)
	{
		if (gettype($handle) != "object" or get_class($handle) != "pgsql") {
			throw new Exception("Handle must be PgSQL object", "internal_error");
		}
		$this->dbHandle = $handle;
	}
}

<?php

/*
* Copyright (c) 2012-2013 David Pesta, https://github.com/DavidPesta/DatabaseHandler
* This file is licensed under the MIT License.
* You should have received a copy of the MIT License along with this program.
* If not, see http://www.opensource.org/licenses/mit-license.php
*/

class DatabaseHandler extends PDO
{
	protected $_connected = false;
	protected $_transactionLevel = 0;
	protected $_cache;
	protected $_cachePath;
	protected $_cacheFile;
	protected $_host;
	protected $_port;
	protected $_database;
	protected $_user;
	protected $_pass;
	protected $_opt;
	protected $_schemata;
	protected $_primaryKeys;
	
	public function __construct()
	{
		$args = func_get_args();
		
		if( count( $args ) == 0 ) {
			$settings = array();
		}
		elseif( is_array( $args[ 0 ] ) ) {
			$settings = $args[ 0 ];
		}
		else {
			throw new Exception( "Unexpected parameters for Database constructor: Expects either an array of settings, or no arguments for default settings." );
		}
		
		$settings += array(
			'cache'     => false,
			'cachePath' => "",
			'host'      => 'localhost',
			'port'      => '3306',
			'database'  => '',
			'user'      => 'root',
			'pass'      => '',
			'opt'       => array()
		);
		
		if( isset( $settings[ 'dbname' ] ) ) $settings[ 'database' ] = $settings[ 'dbname' ];
		$settings[ 'opt' ][ PDO::ATTR_PERSISTENT ] = false;
		
		$this->_cache     = $settings[ 'cache' ];
		$this->_cachePath = $settings[ 'cachePath' ];
		$this->_cacheFile = $this->_cachePath == "" ? "DatabaseHandler.cache" : $this->_cachePath . DIRECTORY_SEPARATOR . "DatabaseHandler.cache";
		$this->_host      = $settings[ 'host' ];
		$this->_port      = $settings[ 'port' ];
		$this->_database  = $settings[ 'database' ];
		$this->_user      = $settings[ 'user' ];
		$this->_pass      = $settings[ 'pass' ];
		$this->_opt       = $settings[ 'opt' ];
		
		$this->connectToDatabase();
		$this->loadSchemata();
	}
	
	private function connectToDatabase()
	{
		if( $this->_connected == true ) return;
		
		parent::__construct(
			'mysql:host=' . $this->_host . ';port=' . $this->_port . ';dbname=' . $this->_database,
			$this->_user,
			$this->_pass,
			$this->_opt
		);
		
		$this->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		
		$this->_connected = true;
	}
	
	public function getConnectionSignature()
	{
		return $this->_host . ":" . $this->_port . ":" . $this->_database;
	}
	
	public function loadSchemata( $force = null )
	{
		if( $this->_database == "" ) {
			$this->_schemata = null;
			return;
		}
		
		$this->_schemata = false;
		$this->_primaryKeys = false;
		
		if( $this->_cache ) {
			if( is_file( $this->_cacheFile ) ) {
				$cache = unserialize( file_get_contents( $this->_cacheFile ) );
				
				if( isset( $cache[ "dbcache:schemata:" . $this->getConnectionSignature() ] ) ) {
					$this->_schemata = $cache[ "dbcache:schemata:" . $this->getConnectionSignature() ];
				}
				
				if( isset( $cache[ "dbcache:primaryKeys:" . $this->getConnectionSignature() ] ) ) {
					$this->_primaryKeys = $cache[ "dbcache:primaryKeys:" . $this->getConnectionSignature() ];
				}
			}
		}

		if( $this->_schemata === false || $this->_primaryKeys === false || $force == "force" ) {
			$this->_schemata = array();
			
			$stmtTables = $this->execute( "show tables" );
			while( $tableRecord = $stmtTables->fetch( PDO::FETCH_NUM ) ) {
				$tableName = $tableRecord[ 0 ];
				
				$this->_schemata[ $tableName ] = array();
				$this->_primaryKeys[ $tableName ] = array();
				
				$stmtSchema = $this->execute( "describe " . $tableName );
				while( $schemaRecord = $stmtSchema->fetch( PDO::FETCH_ASSOC ) ) {
					$fieldName = $schemaRecord[ 'Field' ];
					$this->_schemata[ $tableName ][ $fieldName ] = $schemaRecord;
					if( $schemaRecord[ 'Key' ] == "PRI" ) $this->_primaryKeys[ $tableName ][] = $fieldName;
				}
			}
			
			if( $this->_cache ) {
				if( is_file( $this->_cacheFile ) ) $cache = unserialize( file_get_contents( $this->_cacheFile ) );
				
				if( ! isset( $cache ) || ! is_array( $cache ) ) $cache = [];
				
				$cache[ "dbcache:schemata:" . $this->getConnectionSignature() ] = $this->_schemata;
				$cache[ "dbcache:primaryKeys:" . $this->getConnectionSignature() ] = $this->_primaryKeys;
				
				if( $this->_cachePath != "" && ! is_dir( $this->_cachePath ) ) mkdir( $this->_cachePath, 0777, true );
				file_put_contents( $this->_cacheFile, serialize( $cache ), LOCK_EX );
			}
		}
	}
	
	public function fetchSchemata()
	{
		return $this->_schemata;
	}
	
	public function fetchConfig()
	{
		return array(
			"host"     => $this->_host,
			"port"     => $this->_port,
			"database" => $this->_database,
			"user"     => $this->_user,
			"pass"     => $this->_pass,
			"opt"      => $this->_opt
		);
	}
	
	public function createDatabase( $database = "database", $autoConnect = true )
	{
		$stmt = $this->prepare( "create database if not exists $database" );
		$stmt->execute();
		
		if( $autoConnect ) $this->useDatabase( $database, false );
	}
	
	public function databaseExists( $databaseName )
	{
		return $this->fetchValue( "SELECT IF( '" . $databaseName . "' IN( SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA ), 1, 0 ) AS found" );
	}
	
	public function useDatabase( $database, $loadSchemata = true )
	{
		$this->_database = $database;
		$this->execute( "use " . $this->_database );
		if( $loadSchemata ) $this->loadSchemata();
	}
	
	public function dropDatabase( $database = null )
	{
		if( $database == null ) $database = $this->_database;
		
		if( $database == null ) return;
		
		if( $this->databaseExists( $database ) ) {
			$this->execute( "drop database " . $database );
		}
	}
	
	public function createTable( $sql )
	{
		$this->execute( $sql );
		$this->loadSchemata( "force" );
	}
	
	public function createTables( $script )
	{
		$startingPoint = "CREATE TABLE IF NOT EXISTS";
		$endingPoint = "ENGINE = InnoDB;";
		
		$script = preg_replace( '/(CREATE)\s+(TABLE IF NOT EXISTS )`\w+`./', '\1 \2', $script );
		$script = preg_replace( '/(REFERENCES )`\w+`./', '\1', $script );
		
		$pieces = preg_split( '/(' . $startingPoint . ')|(' . $endingPoint . ')/', $script, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
		
		$createTables = array();
		
		$tempString = "";
		foreach( $pieces as $piece ) {
			if( $piece == $startingPoint ) $tempString = $piece;
			else $tempString .= $piece;
			if( $piece == $endingPoint ) {
				$createTables[] = $tempString;
				$tempString = "";
			}
		}
		
		foreach( $createTables as $createTable ) {
			$this->execute( $createTable );
		}
		
		$this->loadSchemata( "force" );
	}
	
	public function fetchCreateTable( $table )
	{
		$stmt = $this->prepare( "SHOW CREATE TABLE $table" );
		$stmt->execute();
		
		return $stmt->fetch( PDO::FETCH_ASSOC )[ 'Create Table' ];
	}
	
	public function fetchCreateSchemata()
	{
		$createSchemata = "";
		
		foreach( $this->_schemata as $tableName => $columns ) {
			if( $createSchemata != "" ) $createSchemata .= "\n\n";
			$createSchemata .= $this->fetchCreateTable( $tableName );
		}
		
		return $createSchemata;
	}
	
	protected static function prepareArgs( $args, & $firstArg, & $remainingArgs )
	{
		$num = count( $args );
		
		if( $firstArg == null ) $firstArg = "";
		if( $remainingArgs == null ) $remainingArgs = array();
		
		for( $i = 0; $i < $num; $i++ ) {
			if( $i == 0 ) $firstArg = $args[ $i ];
			else {
				if( is_array( $args[ $i ] ) ) $remainingArgs = array_merge( $remainingArgs, $args[ $i ] );
				else $remainingArgs[] = $args[ $i ];
			}
		}
		
		if( count( $remainingArgs ) == 1 && array_key_exists( 0, $remainingArgs ) && $remainingArgs[ 0 ] == null ) $remainingArgs = array();
	}
	
	public function execute()
	{
		self::prepareArgs( func_get_args(), $sql, $params );
		
		$stmt = $this->prepare( $sql );
		$stmt->execute( $params );
		
		return $stmt;
	}
	
	public function resetAutoIncrement( $tableName )
	{
		$this->execute( "ALTER TABLE $tableName AUTO_INCREMENT = 1" );
	}
	
	public function prepareFetchArgs( $args, & $sql, & $params )
	{
		$num = count( $args );
		
		if( $num == 0 ) throw new Exception( "At least one argument is required to determine a query to be run." );
		
		// If a table name is passed, that is a single token without spaces, so if there IS a space in the first arg, we assume it is SQL and pass it on to prepareArgs
		if( strpos( $args[ 0 ], " " ) !== false ) {
			self::prepareArgs( $args, $sql, $params );
		}
		else {
			$table = $args[ 0 ];
			
			if( empty( $args[ 1 ] ) ) {
				$selectFields = "*";
			}
			else {
				if( is_array( $args[ 1 ] ) ) $selectFields = implode( ", ", $args[ 1 ] );
				else $selectFields = $args[ 1 ];
			}
			
			if( empty( $args[ 2 ] ) ) {
				$whereSql = "";
			}
			else {
				$whereValues = $args[ 2 ];
				
				$whereSql = "";
				
				// See if it is numeric-only indexed array; if so then values in the array correspond to table fields and must be in the same order
				for( reset( $whereValues ); is_int( key( $whereValues ) ); next( $whereValues ) );
				if( is_null( key( $whereValues ) ) ) {
					reset( $whereValues );
					foreach( $this->_schemata[ $table ] as $field => $fieldSchema ) {
						$whereValue = current( $whereValues );
						
						if( $whereValue !== false ) {
							if( $whereSql != "" ) $whereSql .= " and ";
							if( $whereValue === null ) {
								$whereSql .= "$field is null";
							}
							elseif( $whereValue === true ) {
								$whereSql .= "$field is not null";
							}
							else {
								$whereSql .= "$field = :$field";
								$params[ ":$field" ] = self::formatValueForDatabase( $fieldSchema, $whereValue );
							}
						}
						
						next( $whereValues );
					}
				}
				else {
					foreach( $this->_schemata[ $table ] as $field => $fieldSchema ) {
						$fieldExists = ( array_key_exists( $field, $whereValues ) && $whereValues[ $field ] !== false ) ? true : false;
						
						if( $fieldExists ) {
							if( $whereSql != "" ) $whereSql .= " and ";
							if( $whereValues[ $field ] === null ) {
								$whereSql .= "$field is null";
							}
							elseif( $whereValues[ $field ] === true ) {
								$whereSql .= "$field is not null";
							}
							else {
								$whereSql .= "$field = :$field";
								$params[ ":$field" ] = self::formatValueForDatabase( $fieldSchema, $whereValues[ $field ] );
							}
						}
					}
				}
				
				$whereSql = " where $whereSql";
			}
			
			$sql = "select " . $selectFields . " from " . $table . $whereSql;
		}
	}
	
	public function fetchOne()
	{
		self::prepareFetchArgs( func_get_args(), $sql, $params );
		
		$stmt = $this->execute( $sql, $params );
		
		return $stmt->fetch( PDO::FETCH_ASSOC );
	}
	
	public function fetch()
	{
		self::prepareFetchArgs( func_get_args(), $sql, $params );
		
		$stmt = $this->execute( $sql, $params );
		
		$arrays = array();
		while( $result = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$arrays[] = $result;
		}
		
		return $arrays;
	}
	
	public function fetchValue()
	{
		self::prepareFetchArgs( func_get_args(), $sql, $params );
		
		$stmt = $this->execute( $sql, $params );
		$result = $stmt->fetch( PDO::FETCH_NUM );
		
		return $result[ 0 ];
	}
	
	public static function groupByKeyValues()
	{
		$newResults = array();
		
		$args = func_get_args();
		
		$keyFields = $args[ 0 ];
		$valueFields = $args[ 1 ];
		$arrays = $args[ 2 ] == null ? array() : $args[ 2 ];
		
		foreach( $arrays as $array ) {
			$newResultsRef =& $newResults;
			
			if( ! is_array( $array ) ) throw new Exception( get_called_class() . "::groupByKeyValues must operate on an array of arrays. Value used: " . $array );
			
			if( $keyFields == null ) {
				$newResultsRef[] = array();
				$newResultsRef =& $newResultsRef[ count( $newResultsRef ) - 1 ];
			}
			elseif( ! is_array( $keyFields ) ) {
				$key = $array[ $keyFields ];
				if( ! isset( $newResultsRef[ $key ] ) || ! is_array( $newResultsRef[ $key ] ) ) {
					if( isset( $newResultsRef[ $key ] ) && $newResultsRef[ $key ] !== null ) {
						throw new Exception( "Key field collision detected on '" . $keyFields . "'; place '" . $keyFields . "' inside of an array followed by a unique key or null to prevent collision." );
					}
					$newResultsRef[ $key ] = array();
				}
				$newResultsRef =& $newResultsRef[ $key ];
			}
			else {
				foreach( $keyFields as $keyField ) {
					$key = $array[ $keyField ];
					if( $key != null ) {
						if( ! is_array( $newResultsRef[ $key ] ) ) {
							if( $newResultsRef[ $key ] !== null ) {
								throw new Exception( "Key field collision detected on '" . $keyField . "'; add a unique key or null to the array after '" . $keyField . "' to prevent collision." );
							}
							$newResultsRef[ $key ] = array();
						}
						$newResultsRef =& $newResultsRef[ $key ];
					}
					else {
						$newResultsRef[] = array();
						$newResultsRef =& $newResultsRef[ count( $newResultsRef ) - 1 ];
					}
				}
			}
			
			if( ! empty( $newResultsRef ) ) {
				throw new Exception( "Key field collision detected on '" . $keyField . "'; add a unique key or null to the array after '" . $keyField . "' to prevent collision." );
			}
			
			if( $valueFields == null ) $newResultsRef = $array;
			elseif( ! is_array( $valueFields ) ) $newResultsRef = $array[ $valueFields ];
			else {
				foreach( $valueFields as $valueField ) {
					$newResultsRef[ $valueField ] = $array[ $valueField ];
				}
			}
		}
		
		return $newResults;
	}
	
	public function fetchGroup()
	{
		$args = func_get_args();
		
		$keyFields = array_shift( $args );
		$valueFields = array_shift( $args );
		
		self::prepareFetchArgs( $args, $sql, $params );
		$stmt = $this->execute( $sql, $params );
		
		$arrays = array();
		while( $result = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$arrays[] = $result;
		}
		
		return self::groupByKeyValues( $keyFields, $valueFields, $arrays );
	}
	
	public static function formatValueForDatabase( $fieldSchema, $value )
	{
		if( $fieldSchema[ 'Type' ] == "datetime" && is_numeric( $value ) ) return date( "Y-m-d H:i:s", intval( $value ) );
		
		if( trim( $value ) === "" && $fieldSchema[ 'Null' ] == "YES" ) return null;
		
		return $value;
	}
	
	public static function isMultiArray( $array )
	{
		// The following detects if an array exists as one of $array's elements:
		// if( count( $records ) == count( $records, COUNT_RECURSIVE ) ) {
		// Instead, we want a negative response if there exists a single non-array value inside $array
		foreach( $array as $key => $value ) {
			if( $key === 0 && is_null( $value ) ) continue;
			if( ! is_array( $value ) ) return false;
		}
		return true;
	}
	
	public function insert()
	{
		self::prepareArgs( func_get_args(), $table, $records );
		
		if( empty( $records ) ) return;
		
		// Check if it is multidimensional array; if not, then make it multidimensional
		if( ! self::isMultiArray( $records ) ) {
			$singleRecord = true;
			$records = array( $records );
		}
		else {
			$singleRecord = false;
		}
		
		$recordsForDimensionShift = array();
		
		$fields = array();
		foreach( $this->_schemata[ $table ] as $field => $fieldSchema ) {
			$fields[] = $field;
		}
		
		$params = array();
		$values = "";
		
		$autoIncrementField = null;
		
		$recordNum = 0;
		foreach( $records as $record ) {
			// See if it is numeric-only indexed array; if so then values in the array correspond to table fields and must be in the same order
			for( reset( $record ); is_int( key( $record ) ); next( $record ) );
			if( is_null( key( $record ) ) ) {
				reset( $record );
				$newRecord = array();
				foreach( $this->_schemata[ $table ] as $field => $fieldSchema ) {
					$newRecord[ $field ] = current( $record );
					
					$fieldIsNull = ( ! array_key_exists( $field, $newRecord ) || is_null( $newRecord[ $field ] ) || $newRecord[ $field ] === false ) ? true : false;
					
					if( $fieldSchema[ 'Default' ] != null && $fieldIsNull ) $newRecord[ $field ] = $fieldSchema[ 'Default' ];
					
					$params[ ":" . $field . "_" . $recordNum ] = self::formatValueForDatabase( $fieldSchema, $newRecord[ $field ] );
					
					if( $fieldSchema[ 'Extra' ] == "auto_increment" && $fieldIsNull ) $autoIncrementField = $field;
					
					next( $record );
				}
				$record = $newRecord;
			}
			else {
				foreach( $this->_schemata[ $table ] as $field => $fieldSchema ) {
					$fieldIsNull = ( ! array_key_exists( $field, $record ) || is_null( $record[ $field ] ) || $record[ $field ] === false ) ? true : false;
					
					if( $fieldSchema[ 'Default' ] != null && $fieldIsNull ) $record[ $field ] = $fieldSchema[ 'Default' ];
					
					if( array_key_exists( $field, $record ) ) {
						$params[ ":" . $field . "_" . $recordNum ] = self::formatValueForDatabase( $fieldSchema, $record[ $field ] );
					}
					else {
						$params[ ":" . $field . "_" . $recordNum ] = null;
					}
					
					if( $fieldSchema[ 'Extra' ] == "auto_increment" && $fieldIsNull ) $autoIncrementField = $field;
				}
			}
			
			if($values != "") $values .= ",";
			$values .= "( :" . implode( "_" . $recordNum . ", :", $fields ) . "_" . $recordNum . " )";
			
			$recordsForDimensionShift[] = $record;
			
			$recordNum++;
		}
		
		$sql = "insert into " . $table . " ( " . implode( ", ", $fields ) . " ) values " . $values;
		
		$this->execute( $sql, $params );
		
		if( $autoIncrementField != null ) {
			$lastInsertId = $this->lastInsertId();
			
			$numRecords = count( $recordsForDimensionShift );
			for( $i = 0; $i < $numRecords; $i++ ) {
				$recordsForDimensionShift[ $i ][ $autoIncrementField ] = $lastInsertId;
				$lastInsertId++;
			}
		}
		
		if( $singleRecord == true ) return array_shift( $recordsForDimensionShift );
		else return $recordsForDimensionShift;
	}
	
	public function update()
	{
		self::prepareArgs( func_get_args(), $table, $records );
		
		if( empty( $records ) ) return;
		
		// Check if it is multidimensional array; if not, then make it multidimensional
		if( ! self::isMultiArray( $records ) ) $records = array( $records );
		
		foreach( $records as $record ) {
			$params = array();
			
			$where = "";
			foreach( $this->_primaryKeys[ $table ] as $key ) {
				if( $where != "" ) $where .= " and ";
				
				// Adding pk in the param name will prevent a conflict if the same param is being used as a part of the update clause
				$where .= "$key = :pk$key";
				
				if( ! isset( $record[ $key ] ) ) throw new Exception( "An update is being attempted on data that doesn't have primary key data set." );
				
				$params[ ":pk" . $key ] = $record[ $key ];
			}
			
			$update = "";
			foreach( $this->_schemata[ $table ] as $field => $fieldSchema ) {
				if( array_key_exists( $field, $record ) ) {
					if( $update != "" ) $update .= ", ";
					$update .= "$field = :$field";
					$params[ ":" . $field ] = self::formatValueForDatabase( $fieldSchema, $record[ $field ] );
				}
			}
			
			// If $update is an empty string, then abort the update because no changes have been made
			if( $update == "" ) return;
			
			$sql = "update " . $table . " set $update where $where";
			$this->execute( $sql, $params );
		}
	}
	
	public function delete()
	{
		self::prepareArgs( func_get_args(), $arg1, $arg2 );
		
		if( array_key_exists( $arg1, $this->_schemata ) ) {
			$table = $arg1;
			$data = $arg2;
			
			if( empty( $data ) ) return;
			
			// Check if it is multidimensional array; if not, then make it multidimensional
			if( ! self::isMultiArray( $data ) ) $data = array( $data );
			
			foreach( $data as $record ) {
				
				// See if it is numeric-only indexed array; if so then values in the array correspond to table fields and must be in the same order
				for( reset( $record ); is_int( key( $record ) ); next( $record ) );
				if( is_null( key( $record ) ) ) {
					
					// Method 1: Zero indexed array found; assume that the first n values match in the right order to all of the first n columns
					//           $dbh->delete( "table", [ "valueOfField1", "valueOfField2", ... ] );
					//           $dbh->delete( "table", [ [ "valueOfField1", "valueOfField2", ... ], [ "valueOfField1", "valueOfField2", ... ], ... ] );
					
					reset( $record );
					$where = "";
					$params = array();
					foreach( $this->_schemata[ $table ] as $field => $fieldSchema ) {
						$value = current( $record );
						if( $value === false ) {
							next( $record );
							continue;
						}
						
						if( $where != "" ) $where .= " and ";
						if( $value === null ) {
							$where .= "$field is null";
						}
						elseif( $value === true ) {
							$where .= "$field is not null";
						}
						else {
							$where .= "$field = :$field";
							$params[ ":$field" ] = self::formatValueForDatabase( $fieldSchema, $value );
						}
						
						next( $record );
					}
				}
				else {
					
					// Method 2: Field names explicitly given, not zero indexed
					//           $dbh->delete( "table", [ "field1" => "value", "field2" => "value", ... ] );
					//           $dbh->delete( "table", [ [ "field1" => "value", "field2" => "value", ... ], [ ... ], ... ] );
					
					$where = "";
					$params = array();
					foreach( $this->_schemata[ $table ] as $field => $fieldSchema ) {
						if( array_key_exists( $field, $record ) ) {
							if( $record[ $field ] === false ) continue;
							if( $where != "" ) $where .= " and ";
							if( $record[ $field ] === null ) {
								$where .= "$field is null";
							}
							elseif( $record[ $field ] === true ) {
								$where .= "$field is not null";
							}
							else {
								$where .= "$field = :$field";
								$params[ ":$field" ] = self::formatValueForDatabase( $fieldSchema, $record[ $field ] );
							}
						}
					}
				}
				
				$sql = "delete from " . $table . " where $where";
				$this->execute( $sql, $params );
			}
		}
		else {
			
			// Method 3: Table not found as first parameter passed to this delete function; apply sql explicitly
			//           $dbh->delete( "delete from table where ...", ?, ... );
			
			$this->execute( $arg1, $arg2 );
		}
	}
	
	public function beginTransaction()
	{
		if( $this->transactionLevel == 0 ) {
			$response = parent::beginTransaction();
			if( $response == true ) $this->transactionLevel++;
			return $response;
		}
		
		$this->transactionLevel++;
		
		return true;
	}
	
	public function commit()
	{
		if( $this->transactionLevel == 0 ) throw new Exception( "Commit cannot be performed when there is no transaction in progress." );
		
		if( $this->transactionLevel == 1 ) {
			$this->transactionLevel = 0;
			return parent::commit();
		}
		
		$this->transactionLevel--;
		
		return true;
	}
	
	public function rollback()
	{
		if( $this->transactionLevel == 0 ) throw new Exception( "Rollback cannot be performed when there is no transaction in progress." );
		
		if( $this->transactionLevel == 1 ) {
			$this->transactionLevel = 0;
			return parent::rollback();
		}
		
		$this->transactionLevel--;
		
		return true;
	}
	
	public function inTransaction()
	{
		return $this->transactionLevel > 0;
	}
	
	public function transactionLevel()
	{
		return $this->transactionLevel;
	}
	
	public function deadlockSafeTransaction( $codeFunction )
	{
		do {
			$retry = false;
			
			try {
				$this->beginTransaction();
				
				$response = $codeFunction( $this );
				
				$this->commit();
			}
			catch( exception $ex ) {
				$this->rollBack();
				
				if( $ex->getCode() == 48047 ) { // Return a custom abort message via an abort exception; 48047 is a clever numerical representation of "ABORT"
					if( ! $this->inTransaction() ) return $ex->getMessage();
				}
				
				if( $ex->getCode() == 40001 ) { // Deadlock found, retry; can also throw an exception with error code 40001 to trigger this retry
					if( $this->inTransaction() ) throw $ex;
					$retry = true;
					usleep( mt_rand( 5000, 100000 ) );
				}
				else throw $ex;
			}
		} while( $retry );
		
		return $response;
	}
	
	/*
	* The safeFetchForUpdate method fulfills the following use-case scenario:
	* There is a table record that may or may not exist, which has a specific primary key that is not auto_increment, and you want to create or update that
	* table record after peeking at its values and you want to block any other transaction that may want to do the same such that no transaction will wipe out
	* each other's update without at least having the opportunity to peek at that record's values during its exclusive lock.
	*
	* Special note: The reason "insert...on duplicate key update" is dangerous is because two transactions could fire that off at a nearly identical moment and
	* one transaction would overwrite the update of the other transaction without having a chance to peek at its values first. Using the safeFetchForUpdate
	* method is the better approach because it gets inserted right away (if it didn't exist) and then blocks for peeking at the values before updating.
	*/
	public function safeFetchForUpdate()
	{
		self::prepareArgs( func_get_args(), $table, $args );
		
		$where = "";
		$params = array();
		
		foreach( $this->_primaryKeys[ $table ] as $key ) {
			if( ! isset( $args[ $key ] ) ) {
				throw new Exception( "safeFetchForUpdate requires an array of key => value pairs for its primary key values" );
			}
			
			if( $where != "" ) $where .= " and ";
			$where .= "$key = :$key";
			$params[ ":$key" ] = $args[ $key ];
		}
		
		$record = $this->fetchOne( "select * from $table where $where limit 1 for update", $params );
		
		if( $record == null ) {
			$data = [];
			
			foreach( $this->_schemata[ $table ] as $field => $schema ) {
				if( array_search( $field, $this->_primaryKeys[ $table ] ) !== false ) {
					$data[ $field ] = $args[ $field ];
					continue;
				}
				
				if( $schema[ 'Default' ] !== null ) {
					$data[ $field ] = $schema[ 'Default' ];
					continue;
				}
				
				if( $schema[ 'Null' ] == "YES" ) {
					$data[ $field ] = null;
					continue;
				}
				
				$type = strtolower( explode( "(", $schema[ 'Type' ] )[ 0 ] );
				
				switch( $type ) {
					case 'tinyint':
					case 'smallint':
					case 'mediumint':
					case 'int':
					case 'integer':
					case 'bigint':
					case 'float':
					case 'double':
					case 'decimal':
					case 'numeric':
						$data[ $field ] = 0;
						break;
					
					case 'char':
					case 'varchar':
					case 'tinytext':
					case 'text':
					case 'mediumtext':
					case 'longtext':
					case 'tinyblob':
					case 'blob':
					case 'mediumblob':
					case 'longblob':
						$data[ $field ] = "";
						break;
					
					case 'datetime':
						$data[ $field ] = "0000-00-00 00:00:00";
						break;
					
					case 'date':
						$data[ $field ] = "0000-00-00";
						break;
					
					case 'time':
						$data[ $field ] = "00:00:00";
						break;
					
					case 'year':
						$data[ $field ] = "0000";
						break;
					
					case 'timestamp':
						// timestamp datatype is set to current time automatically
						break;
					
					default:
						throw new Exception( "'$type' is not a recognized data type in safeFetchForUpdate and may need to be added" );
				}
			}
			
			try {
				$this->insert( $table, $data ); // If a race condition causes this record to already exist, we want it to fail silently here
			} catch( Exception $ex ) {}
			
			$record = $this->fetchOne( "select * from $table where $where limit 1 for update", $params );
		}
		
		return $record;
	}
}

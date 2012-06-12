<?php

/*
* Copyright (c) 2012 David Pesta, https://github.com/DavidPesta/DatabaseHandler
* This file is licensed under the MIT License.
* You should have received a copy of the MIT License along with this program.
* If not, see http://www.opensource.org/licenses/mit-license.php
*/

class DatabaseHandler extends PDO
{
	protected $_cache;
	protected $_host;
	protected $_port;
	protected $_database;
	protected $_user;
	protected $_pass;
	protected $_opt;
	protected $_tableSchemata;
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
			'cache'    => false,
			'host'     => 'localhost',
			'port'     => '3306',
			'database' => '',
			'user'     => 'root',
			'pass'     => '',
			'opt'      => array()
		);
		
		$this->_cache    = $settings[ 'cache' ];
		$this->_host     = $settings[ 'host' ];
		$this->_port     = $settings[ 'port' ];
		$this->_database = $settings[ 'database' ];
		$this->_user     = $settings[ 'user' ];
		$this->_pass     = $settings[ 'pass' ];
		$this->_opt      = $settings[ 'opt' ];
		
		$this->connectToDatabase();
		$this->loadTableSchemata();
	}
	
	public function connectToDatabase()
	{
		parent::__construct(
			'mysql:host=' . $this->_host . ';port=' . $this->_port . ';dbname=' . $this->_database,
			$this->_user,
			$this->_pass,
			$this->_opt
		);
		
		$this->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}
	
	public function getConnectionSignature()
	{
		return $this->_host . ":" . $this->_port . ":" . $this->_database;
	}
	
	public function loadTableSchemata( $force = null )
	{
		if( $this->_database == "" ) {
			$this->_tableSchemata = null;
			return;
		}
		
		if( $this->_cache && extension_loaded('apc') ) {
			$this->_tableSchemata = apc_fetch( "dbcache:schemata:" . $this->getConnectionSignature() );
			$this->_primaryKeys = apc_fetch( "dbcache:primaryKeys:" . $this->getConnectionSignature() );
		}
		else {
			$this->_tableSchemata = false;
			$this->_primaryKeys = false;
		}
		
		if( $this->_tableSchemata === false || $this->_primaryKeys === false || $force == "force" ) {
			$this->_tableSchemata = array();
			
			$stmtTables = $this->execute( "show tables" );
			while( $tableRecord = $stmtTables->fetch( PDO::FETCH_NUM ) ) {
				$tableName = $tableRecord[ 0 ];
				
				$this->_tableSchemata[ $tableName ] = array();
				$this->_primaryKeys[ $tableName ] = array();
				
				$stmtSchema = $this->execute( "describe " . $tableName );
				while( $schemaRecord = $stmtSchema->fetch( PDO::FETCH_ASSOC ) ) {
					$fieldName = $schemaRecord[ 'Field' ];
					$this->_tableSchemata[ $tableName ][ $fieldName ] = $schemaRecord;
					if( $schemaRecord[ 'Key' ] == "PRI" ) $this->_primaryKeys[ $tableName ][] = $fieldName;
				}
			}
			
			if( $this->_cache && extension_loaded('apc') ) {
				apc_store( "dbcache:schemata:" . $this->getConnectionSignature(), $this->_tableSchemata );
				apc_store( "dbcache:primaryKeys:" . $this->getConnectionSignature(), $this->_primaryKeys );
			}
		}
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
		
		if( $autoConnect ) $this->useDatabase( $database );
	}
	
	public function useDatabase( $database )
	{
		$this->_database = $database;
		$this->connectToDatabase();
		$this->loadTableSchemata();
	}
	
	public function createTable( $sql )
	{
		$this->execute( $sql );
		$this->loadTableSchemata( "force" );
	}
	
	public function dropDatabase()
	{
		$stmt = $this->prepare( "drop database " . $this->_database );
		$stmt->execute();
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
	
	public function fetchOne()
	{
		self::prepareArgs( func_get_args(), $sql, $params );
		$stmt = $this->execute( $sql, $params );
		
		return $stmt->fetch( PDO::FETCH_ASSOC );
	}
	
	public function fetch()
	{
		self::prepareArgs( func_get_args(), $sql, $params );
		
		$stmt = $this->execute( $sql, $params );
		
		$arrays = array();
		while( $result = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$arrays[] = $result;
		}
		
		return $arrays;
	}
	
	public function fetchValue()
	{
		self::prepareArgs( func_get_args(), $sql, $params );
		
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
			
			if( ! is_array( $array ) ) throw new Exception( get_called_class() . "::groupByKeys must operate on an array of arrays." );
			
			if( $keyFields == null ) {
				$newResultsRef[] = array();
				$newResultsRef =& $newResultsRef[ count( $newResultsRef ) - 1 ];
			}
			elseif( ! is_array( $keyFields ) ) {
				$key = $array[ $keyFields ];
				if( ! is_array( $newResultsRef[ $key ] ) ) {
					if( $newResultsRef[ $key ] !== null ) {
						throw new Exception( "Key field collision detected; either use a unique key or null for a key field to prevent collision." );
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
								throw new Exception( "Key field collision detected; either use a unique key or null for a key field to prevent collision." );
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
				throw new Exception( "Key field collision detected; either use a unique key or null for a key field to prevent collision." );
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
		
		self::prepareArgs( $args, $sql, $params );
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
		foreach( $array as $value ) {
			if( is_null( $value ) ) continue;
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
		
		$recordsWithAdjustedValues = array();
		
		foreach( $records as $record ) {
			$fields = array();
			$params = array();
			
			$autoIncrementField = null;
			
			foreach( $this->_tableSchemata[ $table ] as $field => $fieldSchema ) {
				if( ! is_null( $record[ $field ] ) ) {
					$fields[] = $field;
					$params[ ":" . $field ] = self::formatValueForDatabase( $fieldSchema, $record[ $field ] );
				}
				
				if( $fieldSchema[ 'Extra' ] == "auto_increment" ) $autoIncrementField = $field;
				if( is_null( $record[ $field ] ) && $fieldSchema[ 'Default' ] != null ) $record[ $field ] = $fieldSchema[ 'Default' ];
			}
			
			$sql = "insert into " . $table . " ( " . implode( ", ", $fields ) . " ) values ( :" . implode( ", :", $fields ) . " )";
			$this->execute( $sql, $params );
			
			if( $autoIncrementField != null ) {
				$record = array_reverse( $record, true );
				$record[ $autoIncrementField ] = $this->lastInsertId();
				$record = array_reverse( $record, true );
			}
			$recordsWithAdjustedValues[] = $record;
		}
		
		if( $singleRecord == true ) return array_shift( $recordsWithAdjustedValues );
		else return $recordsWithAdjustedValues;
	}
	
	public function bulkInsert()
	{
		$args = func_get_args();
		
		$table = $args[ 0 ];
		$fields = $args[ 1 ];
		$records = $args[ 2 ];
		
		$values = array();
		$params = array();
		
		$recordNum = 0;
		foreach( $records as $record ) {
			$fieldBindings = array();
			
			$fieldNum = 0;
			foreach( $fields as $field ) {
				$fieldBinding = $field . "_" . $recordNum;
				$fieldBindings[] = $fieldBinding;
				if( is_null( $record[ $fieldNum ] ) ) {
					$params[ ":" . $fieldBinding ] = self::formatValueForDatabase( $this->_tableSchemata[ $table ][ $field ], $this->_tableSchemata[ $table ][ $field ][ 'Default' ] );
				}
				else {
					$params[ ":" . $fieldBinding ] = self::formatValueForDatabase( $this->_tableSchemata[ $table ][ $field ], $record[ $fieldNum ] );
				}
				$fieldNum++;
			}
			
			$values[] = "( :" . implode( ", :", $fieldBindings ) . " )";
			
			$recordNum++;
		}
		
		$sql = "insert into " . $table . " ( " . implode( ", ", $fields ) . " ) values " . implode( ", ", $values );
		
		$this->execute( $sql, $params );
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
			foreach( $this->_tableSchemata[ $table ] as $field => $fieldSchema ) {
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
		
		// Method 1: $dbh->delete( "table", array( "primaryKey1", "primaryKey2", "otherField", ... ) );
		//           $dbh->delete( "table", array( array( "primaryKey1", "primaryKey2", "otherField", ... ), array( ... ), ... ) );
		if( array_key_exists( $arg1, $this->_tableSchemata ) ) {
			$table = $arg1;
			$data = $arg2;
			
			if( empty( $data ) ) return;
			
			// Check if it is multidimensional array; if not, then make it multidimensional
			if( ! self::isMultiArray( $data ) ) $data = array( $data );
			
			foreach( $data as $record ) {
				$params = array();
				
				$where = "";
				foreach( $this->_primaryKeys[ $table ] as $key ) {
					if( $where != "" ) $where .= " and ";
					
					$where .= "$key = :$key";
					
					if( ! isset( $record[ $key ] ) ) throw new Exception( "Delete is being attempted on data that doesn't have primary key data set." );
					
					$params[ ":" . $key ] = $record[ $key ];
				}
				
				$sql = "delete from " . $table . " where $where";
				$this->execute( $sql, $params );
			}
		}
		
		// Method 2: $dbh->delete( "delete from table where ...", ?, ... );
		else {
			$this->execute( $arg1, $arg2 );
		}
	}
}

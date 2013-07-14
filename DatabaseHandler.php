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
		$this->loadTableSchemata();
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
	
	public function loadTableSchemata( $force = null )
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
		
		if( $autoConnect ) $this->useDatabase( $database );
	}
	
	public function useDatabase( $database )
	{
		$this->_database = $database;
		$this->execute( "use " . $this->_database );
		$this->loadTableSchemata();
	}
	
	public function createTable( $sql )
	{
		$this->execute( $sql );
		$this->loadTableSchemata( "force" );
	}
	
	public function dropDatabase()
	{
		if( $this->_database == "" ) return;
		
		$found = $this->fetchValue( "SELECT IF( '" . $this->_database . "' IN( SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA ), 1, 0 ) AS found" );
		
		if( $found == 1 ) {
			$stmt = $this->prepare( "drop database " . $this->_database );
			$stmt->execute();
		}
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
		
		$this->loadTableSchemata( "force" );
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
			
			// See if it is numeric-only indexed array; if so then values in the array correspond to table fields and must be in the same order
			for( reset( $record ); is_int( key( $record ) ); next( $record ) );
			if( is_null( key( $record ) ) ) {
				reset( $record );
				$newRecord = array();
				foreach( $this->_schemata[ $table ] as $field => $fieldSchema ) {
					$newRecord[ $field ] = current( $record );

					// If it is false, then set it to the default; but if it is set and it is null, then set it to null (nope, if null, it looks like MySQL sets it to the default anyway; that's fine)
					if( ( ! isset( $newRecord[ $field ] ) || $newRecord[ $field ] === false ) && $fieldSchema[ 'Default' ] != null ) $newRecord[ $field ] = $fieldSchema[ 'Default' ];

					$fields[] = $field;
					$params[ ":" . $field ] = self::formatValueForDatabase( $fieldSchema, $newRecord[ $field ] );

					if( $fieldSchema[ 'Extra' ] == "auto_increment" && ( $newRecord[ $field ] === false || is_null( $newRecord[ $field ] ) ) ) $autoIncrementField = $field;
					
					next( $record );
				}
				$record = $newRecord;
			}
			else {
				foreach( $this->_schemata[ $table ] as $field => $fieldSchema ) {
					// If it is false, then set it to the default; but if it is set and it is null, then set it to null (nope, if null, it looks like MySQL sets it to the default anyway; that's fine)
					if( ( ! isset( $record[ $field ] ) || $record[ $field ] === false ) && $fieldSchema[ 'Default' ] != null ) $record[ $field ] = $fieldSchema[ 'Default' ];

					if( isset( $record[ $field ] ) ) {
						$fields[] = $field;
						$params[ ":" . $field ] = self::formatValueForDatabase( $fieldSchema, $record[ $field ] );
					}
					
					if(
						$fieldSchema[ 'Extra' ] == "auto_increment" &&
						(
							! isset( $record[ $field ] ) ||
							(
								isset( $record[ $field ] ) &&
								(
									is_null( $record[ $field ] ) || $record[ $field ] === false
								)
							)
						)
					) {
						$autoIncrementField = $field;
					}
				}
			}
			
			// TODO: Experiment with pulling the following insert out of the foreach and having this work as a multi-insert statement like bulkInsert; then bulkInsert can be removed entirely
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
	
	// DEPRECATED by additions made to the insert method, specifically the numeric indexed array based table inserts
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
					$params[ ":" . $fieldBinding ] = self::formatValueForDatabase( $this->_schemata[ $table ][ $field ], $this->_schemata[ $table ][ $field ][ 'Default' ] );
				}
				else {
					$params[ ":" . $fieldBinding ] = self::formatValueForDatabase( $this->_schemata[ $table ][ $field ], $record[ $fieldNum ] );
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
	
	// Warning: For Methods 2 and 3, if all the fields are present, then only the primary key fields are used to find and delete the field.
	// On the other hand, if all the fields are not fully present, then the fields that are present will be used to find the records to delete.
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
						if( $value === false ) break;
						
						if( $where != "" ) $where .= " and ";
						$where .= "$field = :$field";
						$params[ ":$field" ] = self::formatValueForDatabase( $fieldSchema, $value );
						
						next( $record );
					}
				}
				else {
					
					// Method 2: If one or more fields are missing from the record, then take just the fields that are present and use them inside of the where clause
					//           This is a clean and easy way to delete all records that contain a common value for a column
					//           !!! There is a rare case that this will not cover, where you want to specify ALL fields for the delete. All non-primary key fields get ignored when all the fields exists as per method 3.
					//           $dbh->delete( "table", [ [ "field1" => "value" ], [ "field2" => "value" ], ... ] );
					//           $dbh->delete( "table", [ [ [ "field1" => "value" ], [ "field2" => "value" ], ... ], [ ... ], ... ] );
					
					$allFieldsPresent = true;
					$where = "";
					$params = array();
					foreach( $this->_schemata[ $table ] as $field => $fieldSchema ) {
						if( ! isset( $record[ $field ] ) ) {
							$allFieldsPresent = false;
						}
						else {
							if( $where != "" ) $where .= " and ";
							$where .= "$field = :$field";
							$params[ ":$field" ] = self::formatValueForDatabase( $fieldSchema, $record[ $field ] );
						}
					}
					
					if( $allFieldsPresent == true ) {
						
						// Method 3: If ALL fields were found in the record, then ONLY use primary keys in the where clause and do NOT put the other fields in the array clause
						//           This is for situations where a fetch method grabs whole record(s) (records as arrays) and you want to pass that array to this method to delete it
						//           $dbh->delete( "table", [ [ "primaryKey1" => "value" ], [ "primaryKey2" => "value" ], [ "otherIgnoredField" => "value" ], ... ] );
						//           $dbh->delete( "table", [ [ [ "primaryKey1" => "value" ], [ "primaryKey2" => "value" ], [ "otherIgnoredField" => "value" ], ... ], [ ... ], ... ] );
						
						$where = "";
						$params = array();
						foreach( $this->_primaryKeys[ $table ] as $key ) {
							if( $where != "" ) $where .= " and ";
							$where .= "$key = :$key";
							$params[ ":$key" ] = self::formatValueForDatabase( $this->_schemata[ $table ][ $key ], $record[ $key ] );
						}
					}
				}
				
				$sql = "delete from " . $table . " where $where";
				$this->execute( $sql, $params );
			}
		}
		else {
			
			// Method 4: Table not found as first parameter passed to this delete function; apply sql explicitly
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
}

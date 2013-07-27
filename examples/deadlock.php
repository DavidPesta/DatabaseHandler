<?php

/*
* Copyright (c) 2013 David Pesta, https://github.com/DavidPesta/DatabaseHandler
* This file is licensed under the MIT License.
* You should have received a copy of the MIT License along with this program.
* If not, see http://www.opensource.org/licenses/mit-license.php
*/

error_reporting( E_ALL & ~ ( E_STRICT | E_NOTICE ) );

class Transactions
{
	public static function codePosition( & $dbh, $position, $settings, & $records )
	{
		$positionValue = $settings[ 'position' ][ $position ];
		
		if( $positionValue != null ) echo "<br>codePosition - Position: $position; Value: $positionValue";
		
		if( $positionValue == 'sleep' ) {
			sleep( $settings[ 'sleepTime' ] ?: 10 );
			return;
		}
		
		if( $positionValue == 'retry' ) {
			if( mt_rand( 1, 10 ) == 10 ) return;
			echo "<br>Retry performed at position " . $position;
			throw new Exception( "Retry performed at position " . $position, 40001 );
		}
		
		if( $positionValue == 'abort' ) {
			throw new Exception( "Transaction aborted at position " . $position, 48047 );
		}
		
		if( ctype_digit( strval( $positionValue ) ) && $positionValue >= 1 ) {
			
			if( $settings[ 'safe' ] != 1 ) {
				$record = $dbh->fetchOne( "select * from test where id = ? for update", $positionValue );
				echo "; SELECT...FOR UPDATE performed; current record: " . $record[ 'id' ] . ": " . $record[ 'name' ] . "; microtime: " . microtime( true );
			}
			
			if( $settings[ 'safe' ] == 1 ) {
				$record = $dbh->safeFetchForUpdate( "test", [ "id" => $positionValue ] );
				echo "; safeFetchForUpdate performed; current record: " . $record[ 'id' ] . ": " . $record[ 'name' ] . "; microtime: " . microtime( true );
			}
			
			$record[ 'name' ] = $position;
			$records[] = $record;
		}
	}
	
	public static function innerTransaction( $settings, $step = 1 )
	{
		return $GLOBALS[ 'dbh' ]->deadlockSafeTransaction( function( & $dbh ) use ( $settings, $step ) {
			$records = [];
			
			$position = ( $step * 4 ) - 1;
			self::codePosition( $dbh, $position, $settings, $records );       // Position 3 and 7
			self::codePosition( $dbh, $position + 1, $settings, $records );   // Position 4 and 8
			
			return $records;
		});
	}
	
	public static function outerTransaction( $settings )
	{
		return $GLOBALS[ 'dbh' ]->deadlockSafeTransaction( function( & $dbh ) use ( $settings ) {
			$records = [];
			
			self::codePosition( $dbh, 1, $settings, $records );
			self::codePosition( $dbh, 2, $settings, $records );
			if( $settings[ 'inner1' ] == 1 ) $records = array_merge( $records, self::innerTransaction( $settings, 1 ) );
			self::codePosition( $dbh, 5, $settings, $records );
			self::codePosition( $dbh, 6, $settings, $records );
			if( $settings[ 'inner2' ] == 1 ) $records = array_merge( $records, self::innerTransaction( $settings, 2 ) );
			self::codePosition( $dbh, 9, $settings, $records );
			self::codePosition( $dbh, 10, $settings, $records );
			
			$dbh->update( "test", $records );
		});
	}
}

function createDatabase() {
	global $dbh;
	
	$dbh->createDatabase( "dbhtest" );
	
	$dbh->createTable("
		CREATE TABLE IF NOT EXISTS `dbhtest`.`test` (
			`id` INT UNSIGNED NOT NULL,
			`name` VARCHAR(255) NOT NULL,
			PRIMARY KEY (`id`)
		) ENGINE = InnoDB
	");
	
	$dbh->execute("
		insert into `test` ( `id`, `name` ) values
		( 1, 'one' ),
		( 2, 'two' ),
		( 3, 'three' ),
		( 4, 'four' ),
		( 5, 'five' ),
		( 6, 'six' ),
		( 7, 'seven' ),
		( 8, 'eight' ),
		( 9, 'nine' ),
		( 10, 'ten' )
	");
	//  Add 12 and 15 when testing the "Insert test of safeFetchForUpdate" to see that the insert was actually locking the whole table
	//	( 12, 'twelve' ),
	//	( 15, 'fifteen' )
	//	( , '' ),
}

include "../DatabaseHandler.php";

$dbh = new DatabaseHandler();

if( $_GET[ 'database' ] == "create" ) {
	if( $dbh->databaseExists( "dbhtest" ) == false ) {
		createDatabase();
		echo "Database Created<br><br>";
	}
	else {
		$dbh->useDatabase( "dbhtest" );
		echo "Database Already Exists<br><br>";
	}
}

if( $_GET[ 'database' ] == "destroy" ) {
	if( $dbh->databaseExists( "dbhtest" ) == true ) {
		$dbh->useDatabase( "dbhtest" );
		$dbh->dropDatabase();
		echo "Database Destroyed<br><br>";
	}
	else {
		echo "Database Already Doesn't Exist<br><br>";
	}
}

if( $_GET[ 'database' ] == "rebuild" ) {
	if( $dbh->databaseExists( "dbhtest" ) == true ) {
		$dbh->useDatabase( "dbhtest" );
		$dbh->dropDatabase();
	}
	createDatabase();
	echo "Database Rebuilt<br><br>";
}

unset( $_GET[ 'database' ] );

if( empty( $_GET ) ) {
	
	echo "To perform these deadlock tests, each test employs GET parameters in the links below. Each test has two links that should be run in two <i>different</i> browsers (Firefox and Chrome). For each test, run the top one in a browser first and then the second one in a separate browser quickly afterward. In between each test, rebuild the database using the tools below:<br><br>";
	
	if( $dbh->databaseExists( "dbhtest" ) == true ) {
		echo "Database exists: <a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( [ "database" => "rebuild" ] ) . "'>Rebuild Database</a> / <a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( [ "database" => "destroy" ] ) . "'>Destroy Database</a>";
	}
	else {
		echo "Database does not exist: <a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( [ "database" => "create" ] ) . "'>Create Database</a>";
	}
	
	echo "<br><br>";
	
	echo "Philosophical perspective about select...for update:<br>";
	echo "- When a select...for update happens for a record, that record is 'being updated in progress' starting at that instant and completing upon commit<br>";
	echo "- A normal select without for update should be able to retrieve that value because it doesn't matter to a select whether it gets data before or after an update; this happens all the time with normal selects that happen near normal updates; so the commit is when the update actually happens as far as a normal select is concerned<br>";
	echo "- Regular updates to the same record does block, which makes sense because only one 'update in progress' to a record can happen at a time, so it must wait its turn; a regular update is no different than a select...for update in terms of it being an update that must wait until a current update in progress is finished for a given record<br>";
	echo "- And finally, other select...for updates for that record also block for the same reason, only one 'update in progress' can happen at a time and must wait its turn<br>";
	echo "Another way to phrase it: The instant select...for update is executed, the records fetched from this are in the process of updating until the transaction ends.<br><br>";
	
	echo "Normal first level select...for update deadlock:<br>";
	
	$settings1 = [
		'position' => [
			2 => 2,
			5 => 'sleep',
			6 => 6
		]
	];
	
	$settings2 = [
		'position' => [
			2 => 6,
			6 => 2
		]
	];
	
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 2, sleep, then fetch id 6</a><br>";
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Fetch id 6, then fetch id 2</a><br><br>";
	
	echo "Nested second level select...for update deadlock where the deadlock happens between inside of the two separate inner transactions:<br>";
	
	$settings1 = [
		'position' => [
			4 => 4,
			7 => 'sleep',
			8 => 8
		],
		'inner1' => 1,
		'inner2' => 1
	];
	
	$settings2 = [
		'position' => [
			4 => 8,
			8 => 4
		],
		'inner1' => 1,
		'inner2' => 1
	];
	
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 4, sleep, then fetch id 8</a><br>";
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Fetch id 8, then fetch id 4</a><br><br>";
	
	echo "Nested second level select...for update deadlock where the deadlock happens when selecting from the outer, then an inner transaction:<br>";
	
	$settings1 = [
		'position' => [
			2 => 2,
			3 => 'sleep',
			4 => 4
		],
		'inner1' => 1
	];
	
	$settings2 = [
		'position' => [
			2 => 4,
			4 => 2
		],
		'inner1' => 1
	];
	
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 2, sleep, then fetch id 4</a><br>";
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Fetch id 4, then fetch id 2</a><br><br>";
	
	echo "Nested second level select...for update deadlock where the deadlock happens when selecting from an inner, then the outer transaction:<br>";
	
	$settings1 = [
		'position' => [
			3 => 3,
			4 => 'sleep',
			5 => 5
		],
		'inner1' => 1
	];
	
	$settings2 = [
		'position' => [
			3 => 5,
			5 => 3
		],
		'inner1' => 1
	];
	
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 3, sleep, then fetch id 5</a><br>";
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Fetch id 5, then fetch id 3</a><br><br>";
	
	echo "Retry transaction thrown inside of inner transaction:<br>";
	
	$settings1 = [
		'position' => [
			1 => 1,
			2 => 2,
			3 => 3,
			4 => 'retry',
			5 => 5
		],
		'inner1' => 1,
		'forceDelete' => 1
	];
	
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 1, 2, 3, retry, then 5</a><br><br>";
	
	echo "Abort transaction thrown inside of inner transaction:<br>";
	
	$settings1 = [
		'position' => [
			1 => 10,
			2 => 9,
			3 => 8,
			4 => 'abort',
			5 => 7
		],
		'inner1' => 1,
		'forceDelete' => 1
	];
	
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 10, 9, 8, abort, then 7</a><br><br>";
	
	echo "Simple test of safeFetchForUpdate on record that does not exist:<br>";
	
	$settings1 = [
		'position' => [
			3 => 12,
			5 => 'sleep'
		],
		'inner1' => 1,
		'safe'   => 1
	];
	
	$settings2 = [
		'position' => [
			7 => 12
		],
		'inner2' => 1,
		'safe'   => 1
	];
	
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 12, then sleep</a><br>";
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 12</a><br><br>";
	
	echo "Insert test of safeFetchForUpdate:<br>";
	echo "Gotcha: The entire table blocks other inserts when the first thread inserts id 12, so the other thread is blocked at position 7 until the first thread finishes.<br>";
	
	$settings1 = [
		'position' => [
			1 => 12,
			2 => 'sleep',
			3 => 15
		],
		'inner1' => 1,
		'safe'   => 1
	];
	
	$settings2 = [
		'position' => [
			7 => 15,
			9 => 12  // Remove this line to see that the table is indeed locked as a result of the id 12 insert in the other thread
		],
		'inner2' => 1,
		'safe'   => 1
	];
	
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 12, then sleep, then perform safeFetchForUpdate on id of 15</a><br>";
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 15, then perform safeFetchForUpdate on id of 12</a><br><br>";
	
	echo "Simple test of safeFetchForUpdate on record that doesn't exist, then one that does:<br>";
	echo "Note: While an insert does cause a table-wide block of other inserts for the duration of its transaction, it doesn't block the select-updates of other records in the other thread.<br>";
	
	$settings1 = [
		'position' => [
			3 => 12,
			5 => 'sleep'
		],
		'inner1' => 1,
		'safe'   => 1
	];
	
	$settings2 = [
		'position' => [
			7 => 1
		],
		'inner2' => 1,
		'safe'   => 1
	];
	
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 12, then sleep</a><br>";
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 1</a><br><br>";
	
	echo "Deadlock test of safeFetchForUpdate:<br>";
	
	$settings1 = [
		'position' => [
			1 => 2,
			2 => 'sleep',
			3 => 5
		],
		'inner1' => 1,
		'safe'   => 1
	];
	
	$settings2 = [
		'position' => [
			7 => 5,
			9 => 2
		],
		'inner2' => 1,
		'safe'   => 1
	];
	
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 2, then sleep, then perform safeFetchForUpdate on id of 5</a><br>";
	echo "<a href='" . $_SERVER[ 'SCRIPT_NAME' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 5, then perform safeFetchForUpdate on id of 2</a><br><br>";
}
else {
	if( $dbh->databaseExists( "dbhtest" ) == true ) {
		$dbh->useDatabase( "dbhtest" );
		
		$results = $dbh->fetchGroup( "id", "name", "select * from test" );
		echo "<pre>Initial State: " . print_r( $results, 1 ) . "</pre>";
		
		echo "Start microtime: " . microtime( true ) . "<br>";
		
		echo "<br>" . Transactions::outerTransaction( $_GET );
		
		$results = $dbh->fetchGroup( "id", "name", "select * from test" );
		echo "<pre>Final State: " . print_r( $results, 1 ) . "</pre>";
	}
	else {
		echo "Database does not exist. Return to the parent page and create it.";
	}
}

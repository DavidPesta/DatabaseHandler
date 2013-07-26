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
			
			// Less than or equal to 10, use a normal fetch with for update
			if( $positionValue <= 10 ) {
				$record = $dbh->fetchOne( "select * from test where id = ? for update", $positionValue );
				echo "; SELECT...FOR UPDATE performed; current record: " . $record[ 'id' ] . ": " . $record[ 'name' ] . "; microtime: " . microtime( true );
			}
			
			// Greater than 10, use a safeFetchForUpdate
			if( $positionValue > 10 ) {
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

function setDatabase() {
	global $dbh;
	
	if( $dbh->databaseExists( "dbhtest" ) == true ) {
		$dbh->useDatabase( "dbhtest" );
		return;
	}
	
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
	//	( , '' ),
}

if( empty( $_GET ) ) {
	
	echo "To perform these deadlock tests, each test employs GET parameters in the links below. Each test has two links that should be run in two <i>different</i> browsers (Firefox and Chrome). For each test, run the top one in a browser first and then the second one in a separate browser quickly afterward.<br><br>";
	
	echo "Philosophical perspective about select...for update:<br>";
	echo "- When a select...for update happens for a record, that record is 'being updated in progress' starting at that instant and completing upon commit<br>";
	echo "- A normal select without for update should be able to retrieve that value because it doesn't matter to a select whether it gets data before or after an update; this happens all the time with normal selects that happen near normal updates; so the commit is when the update actually happens as far as a normal select is concerned<br>";
	echo "- Regular updates to the same record does block, which makes sense because only one 'update in progress' to a record can happen at a time, so it must wait its turn; a regular update is no different than a select...for update in terms of it being an update that must wait until a current update in progress is finished<br>";
	echo "- And finally, other select...for updates for that record also block for the same reason, only one 'update in progress' can happen at a time and must wait its turn<br><br>";
	
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
	
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 2, sleep, then fetch id 6</a><br>";
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Fetch id 6, then fetch id 2</a><br><br>";
	
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
	
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 4, sleep, then fetch id 8</a><br>";
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Fetch id 8, then fetch id 4</a><br><br>";
	
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
	
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 2, sleep, then fetch id 4</a><br>";
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Fetch id 4, then fetch id 2</a><br><br>";
	
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
	
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 3, sleep, then fetch id 5</a><br>";
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Fetch id 5, then fetch id 3</a><br><br>";
	
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
	
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 1, 2, 3, retry, then 5</a><br><br>";
	
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
	
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Fetch id 10, 9, 8, abort, then 7</a><br><br>";
	
	echo "Simple test of safeFetchForUpdate:<br>";
	
	$settings1 = [
		'position' => [
			3 => 12,
			5 => 'sleep'
		],
		'inner1' => 1
	];
	
	$settings2 = [
		'position' => [
			7 => 12
		],
		'inner2' => 1
	];
	
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 12, then sleep</a><br>";
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 12</a><br><br>";
	
	echo "Deadlock test of safeFetchForUpdate:<br>";
	
	$settings1 = [
		'position' => [
			1 => 12,
			2 => 'sleep',
			3 => 15
		],
		'inner1' => 1
	];
	
	$settings2 = [
		'position' => [
			7 => 15,
			9 => 12
		],
		'inner2' => 1
	];
	
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings1 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 12, then sleep, then perform safeFetchForUpdate on id of 15</a><br>";
	echo "<a href='" . $_SERVER[ 'REQUEST_URI' ] . "?" . http_build_query( $settings2 ) . "' target='_blank'>Perform safeFetchForUpdate on id of 15, then perform safeFetchForUpdate on id of 12</a><br><br>";
}
else {
	include "../DatabaseHandler.php";
	
	$dbh = new DatabaseHandler();
	
	setDatabase();
	
	echo "<br>" . Transactions::outerTransaction( $_GET );
	
	$results = $dbh->fetchGroup( "id", "name", "select * from test" );
	
	if( $results[ 11 ] != null || $_GET[ 'forceDelete' ] == 1 ) $dbh->dropDatabase();
	else $dbh->execute( "insert into test ( id, name ) values ( 11,  'finished' ) on duplicate key update id = id" );
	
	echo "<pre>" . print_r( $results, 1 ) . "</pre>";
}

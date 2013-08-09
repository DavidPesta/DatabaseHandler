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
	public static function SingleTransaction( $scenario, $pass = 1, $sleep = 0 )
	{
		global $dbh;
		
		try {
			$dbh->beginTransaction();
			
			$power = $scenario . "0" . $pass;
			$soldierId1 = ( $pass - 1 ) * 2 + 1;
			$soldierId2 = ( $pass - 1 ) * 2 + 2;
			
			$dbh->update( "soldiers", array( "soldierId" => $soldierId1, "power" => $power ) );
			if( $scenario == 2 || ( $scenario == 4 && $pass == 1 ) || ( $scenario == 5 && $pass == 2 ) ) throw new Exception( "Scenario " . $scenario . " test exception thrown." );
			$dbh->update( "soldiers", array( "soldierId" => $soldierId2, "power" => $power ) );
			
			if( $sleep > 0 ) sleep( $sleep );
			$dbh->commit();
		}
		catch( exception $ex ) {
			$dbh->rollback();
			throw $ex;
		}
	}
	
	public static function NestedTransaction( $scenario, $sleep = 0 )
	{
		global $dbh;
		
		try {
			$dbh->beginTransaction();
			
			$dbh->update( "soldiers", array( "soldierId" => 11, "power" => 11 ) );
			if( $scenario == 6 ) throw new Exception( "Scenario " . $scenario . " test exception thrown." );
			Transactions::SingleTransaction( $scenario, 1 ); // Do not pass sleep; if we set sleep we don't want to wait for sleep on the inner commits
			$dbh->update( "soldiers", array( "soldierId" => 12, "power" => 12 ) );
			if( $scenario == 7 ) throw new Exception( "Scenario " . $scenario . " test exception thrown." );
			Transactions::SingleTransaction( $scenario, 2 ); // Do not pass sleep; if we set sleep we don't want to wait for sleep on the inner commits
			$dbh->update( "soldiers", array( "soldierId" => 13, "power" => 13 ) );
			if( $scenario == 8 ) throw new Exception( "Scenario " . $scenario . " test exception thrown." );
			
			if( $sleep > 0 ) sleep( $sleep );
			$dbh->commit();
		}
		catch( exception $ex ) {
			$dbh->rollback();
			throw $ex;
		}
	}
}

function resetDatabase() {
	global $dbh;
	
	$dbh->dropDatabase();
	
	$dbh->createDatabase( "dbhtest" );
	
	$dbh->createTable("
		CREATE  TABLE IF NOT EXISTS `dbhtest`.`soldiers` (
			`soldierId` INT UNSIGNED NOT NULL AUTO_INCREMENT ,
			`name` VARCHAR(255) NOT NULL ,
			`rank` VARCHAR(32) NOT NULL ,
			`division` VARCHAR(32) NOT NULL ,
			`power` INT UNSIGNED NOT NULL ,
			`health` INT UNSIGNED NOT NULL ,
			PRIMARY KEY (`soldierId`)
		) ENGINE = InnoDB
	");
	
	$dbh->execute( "
		insert into `soldiers` ( `name`, `rank`, `division`, `power`, `health` ) values
		( 'Brian Holde', 'Private', 'Third', 5, 8 ),
		( 'Jordan Wild', 'Private', 'First', 3, 6 ),
		( 'Mike Barge', 'General', 'Second', 43, 96 ),
		( 'Ray Spring', 'Private', 'First', 2, 4 ),
		( 'Mich Daniels', 'Colonel', 'Third', 29, 63 ),
		( 'Brian O\'Neil', 'General', 'First', 56, 102 ),
		( 'Yoshi Haruka', 'Colonel', 'Second', 35, 78 ),
		( 'Talmage Rock', 'Private', 'First', 4, 9 ),
		( 'Wesley Knight', 'Private', 'Second', 5, 7 ),
		( 'Gordon Richter', 'General', 'Third', 78, 156 ),
		( 'Thomas McKenzie', 'Colonel', 'First', 31, 62 ),
		( 'John MacLeod', 'Private', 'Second', 4, 6 ),
		( 'Jack Nelson', 'Colonel', 'Third', 29, 63 ),
		( 'Henry Finkle', 'Private', 'Second', 3, 4 ),
		( 'Joseph Saddle', 'Colonel', 'Second', 30, 64 ),
		( 'Joshua Nice', 'Private', 'Third', 4, 7 ),
		( 'Mark Porch', 'Private', 'First', 3, 5 ),
		( 'Bob Bunsen', 'Colonel', 'First', 27, 55 ),
		( 'Harold Smith', 'Private', 'Third', 4, 7 );
	" );
	//	( '', '', '', ,  ),
}

include "../DatabaseHandler.php";

$dbh = new DatabaseHandler();


echo "<p><u>Scenarios</u></p>";

echo "<p>1. Single level transaction is started, two items are updated, transaction is committed.</p>";
resetDatabase();
Transactions::SingleTransaction( 1 );
$check = $dbh->fetchGroup( "soldierId", "power", "select soldierId, power from soldiers" );
if( $check[ 1 ] == 101 && $check[ 2 ] == 101 ) echo "<p style='color: #080;'>Scenario 1 - PASSED</p>";
else echo "<p style='color: #E00;'>Scenario 1 - DID NOT PASS</p>";

echo "<p>2. Single level transaction is started, one item is updated and then an exception / rollback before the other one is updated.</p>";
resetDatabase();
try {
	Transactions::SingleTransaction( 2 );
} catch( exception $ex ) {}
$check = $dbh->fetchGroup( "soldierId", "power", "select soldierId, power from soldiers" );
if( $check[ 1 ] == 5 && $check[ 2 ] == 3 ) echo "<p style='color: #080;'>Scenario 2 - PASSED</p>";
else echo "<p style='color: #E00;'>Scenario 2 - DID NOT PASS</p>";

echo "<p>3. Nested transaction is started with two inner transactions, outer updates before, between, and after both inner transactions, and one outer transaction that wraps everything, all transactions successfully committed.</p>";
resetDatabase();
try {
	Transactions::NestedTransaction( 3 );
} catch( exception $ex ) {}
$check = $dbh->fetchGroup( "soldierId", "power", "select soldierId, power from soldiers" );
if( $check[ 1 ] == 301 && $check[ 2 ] == 301 && $check[ 3 ] == 302 && $check[ 4 ] == 302 && $check[ 11 ] == 11 && $check[ 12 ] == 12 && $check[ 13 ] == 13 ) echo "<p style='color: #080;'>Scenario 3 - PASSED</p>";
else echo "<p style='color: #E00;'>Scenario 3 - DID NOT PASS</p>";

echo "<p>4. The same nested transaction is started with an exception inside the first inner transaction.</p>";
resetDatabase();
try {
	Transactions::NestedTransaction( 4 );
} catch( exception $ex ) {}
$check = $dbh->fetchGroup( "soldierId", "power", "select soldierId, power from soldiers" );
if( $check[ 1 ] == 5 && $check[ 2 ] == 3 && $check[ 3 ] == 43 && $check[ 4 ] == 2 && $check[ 11 ] == 31 && $check[ 12 ] == 4 && $check[ 13 ] == 29 ) echo "<p style='color: #080;'>Scenario 4 - PASSED</p>";
else echo "<p style='color: #E00;'>Scenario 4 - DID NOT PASS</p>";

echo "<p>5. The same nested transaction is started with an exception inside the second inner transaction.</p>";
resetDatabase();
try {
	Transactions::NestedTransaction( 5 );
} catch( exception $ex ) {}
$check = $dbh->fetchGroup( "soldierId", "power", "select soldierId, power from soldiers" );
if( $check[ 1 ] == 5 && $check[ 2 ] == 3 && $check[ 3 ] == 43 && $check[ 4 ] == 2 && $check[ 11 ] == 31 && $check[ 12 ] == 4 && $check[ 13 ] == 29 ) echo "<p style='color: #080;'>Scenario 5 - PASSED</p>";
else echo "<p style='color: #E00;'>Scenario 5 - DID NOT PASS</p>";

echo "<p>6. The same nested transaction is started with an exception before entering the first inner transaction.</p>";
resetDatabase();
try {
	Transactions::NestedTransaction( 6 );
} catch( exception $ex ) {}
$check = $dbh->fetchGroup( "soldierId", "power", "select soldierId, power from soldiers" );
if( $check[ 1 ] == 5 && $check[ 2 ] == 3 && $check[ 3 ] == 43 && $check[ 4 ] == 2 && $check[ 11 ] == 31 && $check[ 12 ] == 4 && $check[ 13 ] == 29 ) echo "<p style='color: #080;'>Scenario 6 - PASSED</p>";
else echo "<p style='color: #E00;'>Scenario 6 - DID NOT PASS</p>";

echo "<p>7. The same nested transaction is started with an exception after exiting the first inner transaction and before entering the second inner transaction.</p>";
resetDatabase();
try {
	Transactions::NestedTransaction( 7 );
} catch( exception $ex ) {}
$check = $dbh->fetchGroup( "soldierId", "power", "select soldierId, power from soldiers" );
if( $check[ 1 ] == 5 && $check[ 2 ] == 3 && $check[ 3 ] == 43 && $check[ 4 ] == 2 && $check[ 11 ] == 31 && $check[ 12 ] == 4 && $check[ 13 ] == 29 ) echo "<p style='color: #080;'>Scenario 7 - PASSED</p>";
else echo "<p style='color: #E00;'>Scenario 7 - DID NOT PASS</p>";

echo "<p>8. The same nested transaction is started with an exception after exiting the second inner transaction.</p>";
resetDatabase();
try {
	Transactions::NestedTransaction( 8 );
} catch( exception $ex ) {}
$check = $dbh->fetchGroup( "soldierId", "power", "select soldierId, power from soldiers" );
if( $check[ 1 ] == 5 && $check[ 2 ] == 3 && $check[ 3 ] == 43 && $check[ 4 ] == 2 && $check[ 11 ] == 31 && $check[ 12 ] == 4 && $check[ 13 ] == 29 ) echo "<p style='color: #080;'>Scenario 8 - PASSED</p>";
else echo "<p style='color: #E00;'>Scenario 8 - DID NOT PASS</p>";

echo "<p><b>Special Note:</b> You can pass some # of seconds to the Transactions methods sleep parameters for a delay right before the commits are performed so that you can prepare to watch all the values get updated instantly. You'll need to comment out \$dbh->dropDatabase(); at the bottom of the file.</p>";

$dbh->dropDatabase();

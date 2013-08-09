<?php

/*
* Copyright (c) 2012-2013 David Pesta, https://github.com/DavidPesta/DatabaseHandler
* This file is licensed under the MIT License.
* You should have received a copy of the MIT License along with this program.
* If not, see http://www.opensource.org/licenses/mit-license.php
*/

error_reporting( E_ALL & ~ ( E_STRICT | E_NOTICE ) );

include "../DatabaseHandler.php";

$dbh = new DatabaseHandler();

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

?>
<style>
	a.jumpLink:link { color: #00F; text-decoration: none; }
	a.jumpLink:visited { color: #00F; text-decoration: none; }
	a.jumpLink:hover { color: #00F; text-decoration: underline; }
	a.jumpLink:active { color: #F00; text-decoration: underline; }
</style>
<?php

$soldiers = $dbh->fetch( "select * from soldiers" );

echo "<h2>Soldier Table Data:</h2>";
echo "<table border='1'><tr><td>soldierId</td><td>name</td><td>rank</td><td>division</td><td>power</td><td>health</td></tr>";
foreach( $soldiers as $soldier ) {
	echo "<tr>";
	foreach( $soldier as $value ) echo "<td>" . $value . "</td>";
	echo "</tr>";
}
echo "</table>";

?>

<br>
<u>Click on the links to see how the examples behave:</u><br>
<a href="#fetchValue" class="jumpLink">$dbh->fetchValue( "select name from soldiers where soldierId = 10" );</a><br>
<a href="#fetchOne" class="jumpLink">$dbh->fetchOne( "select * from soldiers where soldierId = 10" );</a><br>
<a href="#fetch" class="jumpLink">$dbh->fetch( "select * from soldiers where rank = 'General'" );</a><br>
<a href="#fetchGroup0" class="jumpLink">$dbh->fetchGroup( null, null, "select * from soldiers where rank = 'General'" );</a><br>
<a href="#fetchGroup1" class="jumpLink">$dbh->fetchGroup( "soldierId", null, "select * from soldiers where rank = 'General'" );</a><br>
<a href="#fetchGroup5" class="jumpLink">$dbh->fetchGroup( null, "name", "select * from soldiers where rank = 'General'" );</a><br>
<a href="#fetchGroup7" class="jumpLink">$dbh->fetchGroup( "soldierId", "name", "select * from soldiers where rank = 'General'" );</a><br>
<a href="#fetchGroup9" class="jumpLink">$dbh->fetchGroup( "soldierId", array( "name" ), "select * from soldiers where rank = 'General'" );</a><br>
<a href="#fetchGroup8" class="jumpLink">$dbh->fetchGroup( "soldierId", array( "name", "power", "health" ), "select * from soldiers where rank = 'General'" );</a><br>
<a href="#fetchGroup6" class="jumpLink">$dbh->fetchGroup( null, array( "name", "power", "health" ), "select * from soldiers where rank = 'General'" );</a><br>
<a href="#fetchGroup2" class="jumpLink">$dbh->fetchGroup( array( "division", "rank", "soldierId" ), "name", "select * from soldiers" );</a><br>
<a href="#fetchGroup3" class="jumpLink">$dbh->fetchGroup( array( "division", "rank", "soldierId" ), array( "name", "power", "health" ), "select * from soldiers" );</a><br>
<a href="#fetchGroup4" class="jumpLink">$dbh->fetchGroup( array( "division", "rank", "soldierId" ), null, "select * from soldiers" );</a><br>
<br>
Key Field Collision Examples (and the simple ways to prevent them):<br>
<a href="#collision1" class="jumpLink">$dbh->fetchGroup( "division", "name", "select * from soldiers" );</a><br>
<a href="#collisionsolution1" class="jumpLink">$dbh->fetchGroup( null, "name", "select * from soldiers" );</a><br>
<a href="#collisionsolution1_2" class="jumpLink">$dbh->fetchGroup( "soldierId", "name", "select * from soldiers" );</a><br>
<a href="#collision2" class="jumpLink">$dbh->fetchGroup( array( "division", "rank" ), "name", "select * from soldiers" );</a><br>
<a href="#collisionsolution2" class="jumpLink">$dbh->fetchGroup( array( "division", "rank", null ), "name", "select * from soldiers" );</a><br>
<a href="#collision3" class="jumpLink">$dbh->fetchGroup( array( "division", "rank" ), array( "name", "power", "health" ), "select * from soldiers" );</a><br>
<a href="#collisionsolution3" class="jumpLink">$dbh->fetchGroup( array( "division", "rank", "soldierId" ), array( "name", "power", "health" ), "select * from soldiers" );</a><br>


<?php
echo "<br>";
?><h2 id="fetchValue">$dbh->fetchValue( "select name from soldiers where soldierId = 10" );</h2><?php
$name = $dbh->fetchValue( "select name from soldiers where soldierId = 10" );
echo "<pre>" . print_r( $name, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetchOne">$dbh->fetchOne( "select * from soldiers where soldierId = 10" );</h2><?php
$soldiers = $dbh->fetchOne( "select * from soldiers where soldierId = 10" );
echo "<pre>" . print_r( $soldiers, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetch">$dbh->fetch( "select * from soldiers where rank = 'General'" );</h2><?php
$soldiers = $dbh->fetch( "select * from soldiers where rank = 'General'" );
echo "<pre>" . print_r( $soldiers, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetchGroup0">$dbh->fetchGroup( null, null, "select * from soldiers where rank = 'General'" );</h2>
* Note: This has the same result as a simple fetch in the example directly above.<?php
$group = $dbh->fetchGroup( null, null, "select * from soldiers where rank = 'General'" );
echo "<pre>" . print_r( $group, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetchGroup1">$dbh->fetchGroup( "soldierId", null, "select * from soldiers where rank = 'General'" );</h2>
* Notice how this changes the array keys from being zero-indexed to being based on soldierId.<?php
$group = $dbh->fetchGroup( "soldierId", null, "select * from soldiers where rank = 'General'" );
echo "<pre>" . print_r( $group, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetchGroup5">$dbh->fetchGroup( null, "name", "select * from soldiers where rank = 'General'" );</h2><?php
$group = $dbh->fetchGroup( null, "name", "select * from soldiers where rank = 'General'" );
echo "<pre>" . print_r( $group, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetchGroup7">$dbh->fetchGroup( "soldierId", "name", "select * from soldiers where rank = 'General'" );</h2><?php
$group = $dbh->fetchGroup( "soldierId", "name", "select * from soldiers where rank = 'General'" );
echo "<pre>" . print_r( $group, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetchGroup9">$dbh->fetchGroup( "soldierId", array( "name" ), "select * from soldiers where rank = 'General'" );</h2><?php
$group = $dbh->fetchGroup( "soldierId", array( "name" ), "select * from soldiers where rank = 'General'" );
echo "<pre>" . print_r( $group, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetchGroup8">$dbh->fetchGroup( "soldierId", array( "name", "power", "health" ), "select * from soldiers where rank = 'General'" );</h2><?php
$group = $dbh->fetchGroup( "soldierId", array( "name", "power", "health" ), "select * from soldiers where rank = 'General'" );
echo "<pre>" . print_r( $group, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetchGroup6">$dbh->fetchGroup( null, array( "name", "power", "health" ), "select * from soldiers where rank = 'General'" );</h2><?php
$group = $dbh->fetchGroup( null, array( "name", "power", "health" ), "select * from soldiers where rank = 'General'" );
echo "<pre>" . print_r( $group, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetchGroup2">$dbh->fetchGroup( array( "division", "rank", "soldierId" ), "name", "select * from soldiers" );</h2><?php
$group = $dbh->fetchGroup( array( "division", "rank", "soldierId" ), "name", "select * from soldiers" );
echo "<pre>" . print_r( $group, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetchGroup3">$dbh->fetchGroup( array( "division", "rank", "soldierId" ), array( "name", "power", "health" ), "select * from soldiers" );</h2><?php
$group = $dbh->fetchGroup( array( "division", "rank", "soldierId" ), array( "name", "power", "health" ), "select * from soldiers" );
echo "<pre>" . print_r( $group, 1 ) . "</pre>";


echo "<br>";
?><h2 id="fetchGroup4">$dbh->fetchGroup( array( "division", "rank", "soldierId" ), null, "select * from soldiers" );</h2><?php
$group = $dbh->fetchGroup( array( "division", "rank", "soldierId" ), null, "select * from soldiers" );
echo "<pre>" . print_r( $group, 1 ) . "</pre>";


echo "<br>";
?><h2 id="collision1">$dbh->fetchGroup( "division", "name", "select * from soldiers" );</h2>
* Notice there are multiple data sets that can be grouped under "division". This leads to key field collisions. So we cannot use "division".<?php
try {
	$group = $dbh->fetchGroup( "division", "name", "select * from soldiers" );
	echo "<pre>" . print_r( $group, 1 ) . "</pre>";
}
catch( Exception $ex ) {
	echo "<pre>" . print_r( $ex->getMessage(), 1 ) . "</pre>";
}


echo "<br>";
?><h2 id="collisionsolution1">$dbh->fetchGroup( array( "division", null ), "name", "select * from soldiers" );</h2>
* Prevent the collision by replacing "division" with an array with "division" followed by a null element... (notice the results are zero-indexed within each division)<?php
try {
	$group = $dbh->fetchGroup( array( "division", null ), "name", "select * from soldiers" );
	echo "<pre>" . print_r( $group, 1 ) . "</pre>";
}
catch( Exception $ex ) {
	echo "<pre>" . print_r( $ex->getMessage(), 1 ) . "</pre>";
}


echo "<br>";
?><h2 id="collisionsolution1_2">$dbh->fetchGroup( array( "division", "soldierId" ), "name", "select * from soldiers" );</h2>
* Or prevent the collision by replacing "division" with an array with "division" followed by a unique key. (results are indexed by soldierId within each division)<?php
try {
	$group = $dbh->fetchGroup( array( "division", "soldierId" ), "name", "select * from soldiers" );
	echo "<pre>" . print_r( $group, 1 ) . "</pre>";
}
catch( Exception $ex ) {
	echo "<pre>" . print_r( $ex->getMessage(), 1 ) . "</pre>";
}


echo "<br>";
?><h2 id="collision2">$dbh->fetchGroup( array( "division", "rank" ), "name", "select * from soldiers" );</h2>
* Another example of a collision.<?php
try {
	$group = $dbh->fetchGroup( array( "division", "rank" ), "name", "select * from soldiers" );
	echo "<pre>" . print_r( $group, 1 ) . "</pre>";
}
catch( Exception $ex ) {
	echo "<pre>" . print_r( $ex->getMessage(), 1 ) . "</pre>";
}


echo "<br>";
?><h2 id="collisionsolution2">$dbh->fetchGroup( array( "division", "rank", null ), "name", "select * from soldiers" );</h2>
* Prevent it by adding a null value at the end of the key fields array.<?php
try {
	$group = $dbh->fetchGroup( array( "division", "rank", null ), "name", "select * from soldiers" );
	echo "<pre>" . print_r( $group, 1 ) . "</pre>";
}
catch( Exception $ex ) {
	echo "<pre>" . print_r( $ex->getMessage(), 1 ) . "</pre>";
}


echo "<br>";
?><h2 id="collision3">$dbh->fetchGroup( array( "division", "rank" ), array( "name", "power", "health" ), "select * from soldiers" );</h2>
* And another example.<?php
try {
	$group = $dbh->fetchGroup( array( "division", "rank" ), array( "name", "power", "health" ), "select * from soldiers" );
	echo "<pre>" . print_r( $group, 1 ) . "</pre>";
}
catch( Exception $ex ) {
	echo "<pre>" . print_r( $ex->getMessage(), 1 ) . "</pre>";
}


echo "<br>";
?><h2 id="collisionsolution3">$dbh->fetchGroup( array( "division", "rank", "soldierId" ), array( "name", "power", "health" ), "select * from soldiers" );</h2>
* Prevent it by adding a unique key like "soldierId" at the end of the key fields array.<?php
try {
	$group = $dbh->fetchGroup( array( "division", "rank", "soldierId" ), array( "name", "power", "health" ), "select * from soldiers" );
	echo "<pre>" . print_r( $group, 1 ) . "</pre>";
}
catch( Exception $ex ) {
	echo "<pre>" . print_r( $ex->getMessage(), 1 ) . "</pre>";
}



$dbh->dropDatabase();
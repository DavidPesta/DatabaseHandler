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

$dbh->createTables( file_get_contents( "ddls/update.sql" ) );

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
<u>Explanation:</u><br>
<br>
The way this works may seem unintuitive at first, depending on how you look at it. You might expect to pass an update function an array of primary keys and a separate array of field/values to update. But instead, they are all found in the same array. There is a particular paradigm behind how update works. Imagine that you had just performed a fetch and have an associative array of field names and their values. The primary key(s) and its value(s) are also found in the array from this fetch. Make a change to one or more of the values, then turn right around and pass that same array directly into the update method to set those values to the database. Simple and elegant. The primary key fields are automatically detected and placed into the where clause. But, what if you are wanting to update stuff for a record that has nothing to do with the primary key? Well, you can do that by specifying any weird combination of fields in your fetch, so this is done at the fetch step. Any strange conditions that you are wanting to do the update on, those strange conditions would be a part of your fetch so that your update would just be normal based on the primary keys that are also returned from fetching the records.<br>

<br>
<u>Click on the links to see how the examples behave:</u><br>
<br>
<a href="#update1" class="jumpLink">
	$dbh->fetch( "select * from soldiers where soldierId in ( 10, 11 )" );<br>
	$dbh->update( "soldiers", array( "soldierId" => 10, "power" => 81 ) );<br>
	$dbh->fetch( "select * from soldiers where soldierId in ( 10, 11 )" );
</a><br>
<br>
<a href="#update2" class="jumpLink">
	$dbh->fetchOne( "select * from soldiers where soldierId = 10" );<br>
	$dbh->update( "soldiers", array( "soldierId" => 10, "power" => 85, "garbageData" => "stuff" ) );<br>
	$dbh->fetchOne( "select * from soldiers where soldierId = 10" );
</a><br>
<br>
<a href="#update3" class="jumpLink">
	$dbh->fetch( "select * from soldiers where soldierId in ( 11, 12 )" );<br>
	$dbh->update( "soldiers", array( array( "soldierId" => 11, "health" => 0 ), array( "soldierId" => 12, "health" => 0 ) ) );<br>
	$dbh->fetch( "select * from soldiers where soldierId in ( 11, 12 )" );
</a><br>
<br>
<a href="#update4" class="jumpLink">
	$dbh->fetchOne( "select * from soldiers where soldierId = 18" );<br>
	$dbh->update( "soldiers", array( "soldierId" => 18, "power" => null ) );<br>
	$dbh->fetchOne( "select * from soldiers where soldierId = 18" );
</a><br>
<br>
<a href="#update5" class="jumpLink">
	$dbh->fetchOne( "select * from soldiers where soldierId = 19" );<br>
	$dbh->update( "soldiers", array( "soldierId" => 19, "division" => "Second" ) );<br>
	$dbh->fetchOne( "select * from soldiers where soldierId = 19" );
</a><br>


<?php
echo "<br>";
?><h2 id="update1">
	$dbh->fetch( "select * from soldiers where soldierId in ( 10, 11 )" );<br>
	$dbh->update( "soldiers", array( "soldierId" => 10, "power" => 81 ) );<br>
	$dbh->fetch( "select * from soldiers where soldierId in ( 10, 11 )" );
</h2><?php
$result = $dbh->fetch( "select * from soldiers where soldierId in ( 10, 11 )" );
echo "<pre>" . print_r( $result, 1 ) . "</pre>";
$dbh->update( "soldiers", array( "soldierId" => 10, "power" => 81 ) );
$result = $dbh->fetch( "select * from soldiers where soldierId in ( 10, 11 )" );
echo "<pre>" . print_r( $result, 1 ) . "</pre>";


echo "<br>";
?><h2 id="update2">
	$dbh->fetchOne( "select * from soldiers where soldierId = 10" );<br>
	$dbh->update( "soldiers", array( "soldierId" => 10, "power" => 85, "garbageData" => "stuff" ) );<br>
	$dbh->fetchOne( "select * from soldiers where soldierId = 10" );
</h2><?php
$result = $dbh->fetchOne( "select * from soldiers where soldierId = 10" );
echo "<pre>" . print_r( $result, 1 ) . "</pre>";
$dbh->update( "soldiers", array( "soldierId" => 10, "power" => 85, "garbageData" => "stuff" ) );
$result = $dbh->fetchOne( "select * from soldiers where soldierId = 10" );
echo "<pre>" . print_r( $result, 1 ) . "</pre>";


echo "<br>";
?><h2 id="update3">
	$dbh->fetch( "select * from soldiers where soldierId in ( 11, 12 )" );<br>
	$dbh->update( "soldiers", array( array( "soldierId" => 11, "health" => 0 ), array( "soldierId" => 12, "health" => 0 ) ) );<br>
	$dbh->fetch( "select * from soldiers where soldierId in ( 11, 12 )" );
</h2><?php
$result = $dbh->fetch( "select * from soldiers where soldierId in ( 11, 12 )" );
echo "<pre>" . print_r( $result, 1 ) . "</pre>";
$dbh->update( "soldiers", array( array( "soldierId" => 11, "health" => 0 ), array( "soldierId" => 12, "health" => 0 ) ) );
$result = $dbh->fetch( "select * from soldiers where soldierId in ( 11, 12 )" );
echo "<pre>" . print_r( $result, 1 ) . "</pre>";


echo "<br>";
?><h2 id="update4">
	$dbh->fetchOne( "select * from soldiers where soldierId = 18" );<br>
	$dbh->update( "soldiers", array( "soldierId" => 18, "power" => null ) );<br>
	$dbh->fetchOne( "select * from soldiers where soldierId = 18" );
</h2>
* Passing a value of null will successfully set it to null in the database
<?php
$result = $dbh->fetchOne( "select * from soldiers where soldierId = 18" );
echo "<pre>" . print_r( $result, 1 ) . "</pre>";
$dbh->update( "soldiers", array( "soldierId" => 18, "power" => null ) );
$result = $dbh->fetchOne( "select * from soldiers where soldierId = 18" );
echo "<pre>" . print_r( $result, 1 ) . "</pre>";


echo "<br>";
?><h2 id="update5">
	$dbh->fetchOne( "select * from soldiers where soldierId = 19" );<br>
	$dbh->update( "soldiers", array( "soldierId" => 19, "division" => "Second" ) );<br>
	$dbh->fetchOne( "select * from soldiers where soldierId = 19" );
</h2>
* Not passing a value does not set it to null, but ignores it
<?php
$result = $dbh->fetchOne( "select * from soldiers where soldierId = 19" );
echo "<pre>" . print_r( $result, 1 ) . "</pre>";
$dbh->update( "soldiers", array( "soldierId" => 19, "division" => "Second" ) );
$result = $dbh->fetchOne( "select * from soldiers where soldierId = 19" );
echo "<pre>" . print_r( $result, 1 ) . "</pre>";


$soldiers = $dbh->fetch( "select * from soldiers" );

echo "<h2>Soldier Table Data (AFTER):</h2>";
echo "<table border='1'><tr><td>soldierId</td><td>name</td><td>rank</td><td>division</td><td>power</td><td>health</td></tr>";
foreach( $soldiers as $soldier ) {
	echo "<tr>";
	foreach( $soldier as $value ) echo "<td>" . $value . "</td>";
	echo "</tr>";
}
echo "</table>";


$dbh->dropDatabase();
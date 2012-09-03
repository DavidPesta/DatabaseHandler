<?php

/*
* Copyright (c) 2012 David Pesta, https://github.com/DavidPesta/DatabaseHandler
* This file is licensed under the MIT License.
* You should have received a copy of the MIT License along with this program.
* If not, see http://www.opensource.org/licenses/mit-license.php
*/

error_reporting( E_ALL & ~ ( E_STRICT | E_NOTICE ) );

include "../DatabaseHandler.php";

$dbh = new DatabaseHandler( array( "cache" => true ) );

$dbh->createDatabase( "dbhtest" );

$dbh->createTable( "
	CREATE TABLE IF NOT EXISTS `soldiers` (
		`soldierId` INT UNSIGNED NOT NULL AUTO_INCREMENT,
		`name` VARCHAR(255) NOT NULL,
		`rank` VARCHAR(32) NOT NULL,
		`division` VARCHAR(32) NOT NULL,
		`power` INT UNSIGNED,
		`health` INT UNSIGNED NOT NULL DEFAULT 5,
		PRIMARY KEY (`soldierId`)
	) ENGINE = InnoDB;
" );

$dbh->bulkInsert( "soldiers",
	array(
		       "name",            "rank",    "division", "power", "health"
	),
	array(
		array( 'Brian Holde',     'Private', 'Third',    5,       8 ),
		array( 'Jordan Wild',     'Private', 'First',    3,       6 ),
		array( 'Mike Barge',      'General', 'Second',   43,      96 ),
		array( 'Ray Spring',      'Private', 'First',    2,       4 ),
		array( 'Mich Daniels',    'Colonel', 'Third',    29,      63 ),
		array( 'Brian O\'Neil',   'General', 'First',    56,      102 ),
		array( 'Yoshi Haruka',    'Colonel', 'Second',   null,    78 ),
		array( 'Talmage Rock',    'Private', 'First',    4,       9 ),
		array( 'Wesley Knight',   'Private', 'Second',   5,       7 ),
		array( 'Gordon Richter',  'General', 'Third',    78,      156 ),
		array( 'Thomas McKenzie', 'Colonel', null,    31,      62 ),
		array( 'John MacLeod',    'Private', 'Second',   4,       6 ),
		array( 'Jack Nelson',     'Colonel', 'Third',    29,      63 ),
		array( 'Henry Finkle',    'Private', 'Second',   3,       4 ),
		array( 'Joseph Saddle',   null, 'Second',   null,    64 ),
		array( 'Joshua Nice',     'Private', 'Third',    4,       7 ),
		array( 'Mark Porch',      'Private', 'First',    3,       null ),
		array( 'Bob Bunsen',      'Colonel', 'First',    27,      55 ),
		array( 'Harold Smith',    'Private', 'Third',    4,       7 )
	)
);

/*
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
*/
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
<br>

we want to do a multi-insert, a single insert, with null values and without null values

do a single record insert and receive and echo the last insert id returned

do a multiple record insert and receive and echo the last insert id returned

do an insert with null values and then do a fetch and a var_dump to see that they are in fact null

?do an insert with not null values being absent and do a fetch to show them empty

do an insert with an empty string for a not nullable field

do an insert with a whitespace string for a not nullable field

do an insert with an empty string for a nullable field

do an insert with a whitespace string for a nullable field

do an insert with 0 as values


<?php

$item = $dbh->insert( "soldiers", array(
	"name" => "happy day",
	"rank" => "stuff",
	"division" => "fun"
));

echo "<pre>" . print_r( $item, 1 ) . "</pre>";

$items = $dbh->insert( "soldiers", array(
	array(
		"soldierId" => 22,
		"name" => "happy day2",
		"rank" => "stuff2",
		"division" => "fun2",
		"power" => 568,
		"health" => 675
	),
	array(
		"solderId" => null,
		"name" => "happy day3",
		"rank" => "stuff3",
		"division" => "fun3",
		"health" => null
	)
));

echo "<pre>" . print_r( $items, 1 ) . "</pre>";

$items = $dbh->insert( "soldiers", [ 28, "Funny Joe", "Joker", "great!", 222, 333 ] );

echo "<pre>" . print_r( $items, 1 ) . "</pre>";

$items = $dbh->insert( "soldiers", [
	[ null,  "One",   "RankOne",   "DivisionOne",   111, false ],
	[ false, "Two",   "RankTwo",   "DivisionTwo",   222 ],
	[ null,  "Three", "RankThree", "DivisionThree", 333, null ]
]);

echo "<pre>" . print_r( $items, 1 ) . "</pre>";

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
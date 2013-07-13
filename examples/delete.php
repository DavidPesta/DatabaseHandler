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

$dbh->createTables( file_get_contents( "ddls/delete.sql" ) );

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
		array( 'Yoshi Haruka',    'Colonel', 'Second',   35,    78 ),
		array( 'Talmage Rock',    'Private', 'First',    4,       9 ),
		array( 'Wesley Knight',   'Private', 'Second',   5,       7 ),
		array( 'Gordon Richter',  'General', 'Third',    78,      156 ),
		array( 'Thomas McKenzie', 'Colonel', 'First',    31,      62 ),
		array( 'John MacLeod',    'Private', 'Second',   4,       6 ),
		array( 'Jack Nelson',     'Colonel', 'Third',    29,      63 ),
		array( 'Henry Finkle',    'Private', 'Second',   3,       4 ),
		array( 'Joseph Saddle',   'Colonel', 'Second',   30,    64 ),
		array( 'Joshua Nice',     'Private', 'Third',    4,       7 ),
		array( 'Mark Porch',      'Private', 'First',    3,       5 ),
		array( 'Bob Bunsen',      'Colonel', 'First',    27,      55 ),
		array( 'Harold Smith',    'Private', 'Third',    4,       7 )
	)
);

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
<b>Here are the delete queries that are run to produce the resulting table below:</b><br>
<br>
$dbh->delete( "delete from soldiers where soldierId in ( ?, ?, ? )", 1, 3, 5 );<br>
<br>
$dbh->delete( "soldiers", array( "soldierId" => 10 ) );<br>
<br>
$dbh->delete( "soldiers", array( "soldierId" => 13, "other" => "stuff" ) );<br>
<br>
$dbh->delete( "soldiers", array(<br>
&nbsp;&nbsp;&nbsp;&nbsp;array( "soldierId" => 8, "other" => "stuff" ),<br>
&nbsp;&nbsp;&nbsp;&nbsp;array( "soldierId" => 15, "other" => "stuff" ),<br>
&nbsp;&nbsp;&nbsp;&nbsp;array( "soldierId" => 18, "other" => "stuff" )<br>
));<br>
<br>
$dbh->delete( "soldiers", [ 9 ] );<br>
<br>
$dbh->delete( "soldiers", [ 12, "John MacLeod" ] );<br>
<br>
$dbh->delete( "soldiers", [ "division" => "First" ] );<br>
<br>
$dbh->delete( "soldiers", [<br>
&nbsp;&nbsp;&nbsp;&nbsp;"soldierId" => 16,<br>
&nbsp;&nbsp;&nbsp;&nbsp;"name" => "Joshua Nice",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"rank" => "Private",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"division" => "Third",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"power" => 4,<br>
&nbsp;&nbsp;&nbsp;&nbsp;"health" => 7<br>
]);<br>

<?php

$dbh->delete( "delete from soldiers where soldierId in ( ?, ?, ? )", 1, 3, 5 );

$dbh->delete( "soldiers", array( "soldierId" => 10 ) );

$dbh->delete( "soldiers", array( "soldierId" => 13, "other" => "stuff" ) );

$dbh->delete( "soldiers", array(
	array( "soldierId" => 8, "other" => "stuff" ),
	array( "soldierId" => 15, "other" => "stuff" ),
	array( "soldierId" => 18, "other" => "stuff" )
));

$dbh->delete( "soldiers", [ 9 ] );

$dbh->delete( "soldiers", [ 12, "John MacLeod" ] );

$dbh->delete( "soldiers", [ "division" => "First" ] );

$dbh->delete( "soldiers", [
	"soldierId" => 16,
	"name" => "Joshua Nice",
	"rank" => "Private",
	"division" => "Third",
	"power" => 4,
	"health" => 7
]);


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
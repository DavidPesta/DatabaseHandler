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
		`division` VARCHAR(32) NULL ,
		`power` INT UNSIGNED NULL ,
		`health` INT UNSIGNED NULL ,
		PRIMARY KEY (`soldierId`)
	) ENGINE = InnoDB
");

$dbh->insert( "soldiers", [
	[ null, 'Brian Holde',     'Private', 'Third',  5,    8    ],
	[ null, 'Jordan Wild',     'Private', 'First',  3,    6    ],
	[ null, 'Mike Barge',      'General', 'Second', 43,   96   ],
	[ null, 'Ray Spring',      'Private', 'First',  2,    4    ],
	[ null, 'Mich Daniels',    'Colonel', 'Third',  29,   63   ],
	[ null, 'Brian O\'Neil',   'General', 'First',  56,   102  ],
	[ null, 'Yoshi Haruka',    'Colonel', null,     35,   78   ],
	[ null, 'Talmage Rock',    'Private', 'First',  4,    9    ],
	[ null, 'Wesley Knight',   'Private', 'Second', 5,    7    ],
	[ null, 'Gordon Richter',  'General', 'Third',  78,   156  ],
	[ null, 'Thomas McKenzie', 'Colonel', 'First',  31,   62   ],
	[ null, 'John MacLeod',    'Private', 'Second', 4,    6    ],
	[ null, 'Jack Nelson',     'Colonel', 'Third',  29,   63   ],
	[ null, 'Henry Finkle',    'Private', 'Second', 3,    4    ],
	[ null, 'Joseph Saddle',   'Colonel', 'Second', 30,   64   ],
	[ null, 'Joshua Nice',     'Private', 'Third',  4,    7    ],
	[ null, 'Mark Porch',      'Private', 'First',  3,    5    ],
	[ null, 'Bob Bunsen',      'Colonel', 'First',  27,   55   ],
	[ null, 'Harold Smith',    'Private', 'Third',  null, 7    ],
	[ null, 'Mike Sherman',    'Private', 'Second', 34,   54   ],
	[ null, 'Bill Norman',     'Private', 'Third',  0,    5    ],
	[ null, 'Chris Martin',    '',        'Second', 12,   18   ], // rank gets inserted into database as ""
	[ null, 'Jack Morning',    'Private', '',       16,   32   ], // division gets inserted into database as null
	[ null, 'Mack Mann',       'Private', 'Third',  76,   null ]
]);

$dbh->execute( "insert into soldiers ( name, rank, division, power, health ) values ( 'Fred Frost', 'General', '', 58, 110 )" ); // division gets inserted into database as ""

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
$dbh->execute( "delete from soldiers where soldierId in ( ?, ?, ? )", 1, 3, 5 );<br>
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
<br>
$dbh->delete( "soldiers", [ false, "Henry Finkle", false, false, 3 ] );<br>
<br>
$dbh->delete( "soldiers", [ false, false, false, null ] );<br>
<br>
$dbh->delete( "soldiers", [ "power" => null ] );<br>
<br>
$dbh->delete( "soldiers", [ "rank" => null ] );<br>

<?php

$dbh->execute( "delete from soldiers where soldierId in ( ?, ?, ? )", 1, 3, 5 );

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

$dbh->delete( "soldiers", [ false, "Henry Finkle", false, false, 3 ] );

$dbh->delete( "soldiers", [ false, false, false, null ] );

$dbh->delete( "soldiers", [ "power" => null ] );

$dbh->delete( "soldiers", [ "rank" => null ] );

$dbh->delete( "soldiers", [ "health" => true ] );
//$dbh->delete( "soldiers", [ false, false, false, false, false, true ] ); // This has the same result as immediately above

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
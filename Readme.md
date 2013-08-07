DatabaseHandler
===============

A powerful library class that makes it easy to handle MySQL data.

<b>Initial Release:</b> February 8, 2012<br>
<b>Last Updated:</b> August 6, 2013


## Documentation

The examples folder provides an exceptional set of self-explanatory documentation. Run the examples and learn from them.


## Helpful Tips and Best Practices

* Create DatabaseHandler object, assign it early to something like $dbh, then bring it into scope with "global $dbh;"

* There's no need for a countRecords method as we can use fetchValue( "select count(...) ..." ) for this
  * When possible, be sure to do a "select count( PrimaryKeyId )" instead of "select count( * )" for a huge performance improvement

* If you need "select ... for update", the "for update" can be added manually at the end of the query string

* The update method is faster if you pass to it a small array with just the changes you want to make to it

* When inserting or updating a datetime, you can pass an integer timestamp to it and it will auto format it for the database
  * Use the following to convert a time string to a timestamp: strtotime( str_replace( array( "-", "." ), "/", $timeString ) );
  * strtotime() interprets hyphens as a MySQL datetime field; we want to treat "-", ".", and "/" the same when creating timestamp

* When passing a record array to the delete method, if there are really large text fields in that array, it is a best practice to unset those fields from the array before passing it to the delete method so that it is not used as part of the search criteria for deleting the record

* For arrays passed to query methods whose values go into the where clause, null gets translated to 'is null', true gets translated to 'is not null', and false causes that field to get skipped (not appear in the where clause)


## To Do List

* Revisit delete method and consider whether Method 1 should allow you to pass in other fields, not just primary key

* Revisit update and consider adding a Method 2 like delete that allows you to input the SQL manually

* Two additional methods in bulkInsert:
  * Input 1 multidimensional array of zero-indexed data, which must contain *all* fields in the right order
  * Input 1 multidimensional array of fieldname indexed data

* To insert, add a Method 2 that allows arrays to be passed with zero-indexed data in the same order as the fields

* Improve and clean up the examples for insert

* Implement method that takes care of cases where "insert ... on duplicate key update" is needed

* Implement and test various transaction methods with example files
  * beginTransaction
  * rollBack
  * commit
  * inTransaction (if implemented for MySQL yet, which it seems to be now: https://bugs.php.net/bug.php?id=60213)
  * deadlockSafeTransaction
  * safeFetchForUpdate

* Add methods to return a table's schema and return a table's primary keys; create an example file to demonstrate misc methods

* Implement various dumpTable and dumpData methods and options


## License 

The MIT License

Copyright (c) 2012-2013 David Pesta

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
'Software'), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

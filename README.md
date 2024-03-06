Simple wrapper class for using PDO to interact with a MySQL database.

This class is, if you can believe it, the culmination of nearly a decade of tweaking. Originally it began as a more traditional database wrapper with dedicated methods for actions like `SELECT`, `UPDATE`, `DELETE`, etc. But over the years of working with more complex queries I realized I was only ever using the generic `query` method, especially when working with stored procedures. I also realized just how powerful the native PDOStatment methods are. Why reinvent the wheel when beautiful methods like `PDOStatement::fetch()` and `PDOStatement::fetchAll()` accept arguments such as `PDO::FETCH_ASSOC`, `PDO::FETCH_COLUMN`, and `PDO::FETCH_KEY_PAIR`? I can't think of a single reason. So, I trimmed the fat. One `Database::query()` method which, by default, returns the resultant `PDOStatement` object.

This class supports multiple connections, which was the reasoning behind the ini loading stuff, as having every connection laid out in your `.env` file can quickly become a pain of not only bloat, but keeping track of prefixes/suffixes to differentiate the different variables. So you can easily limit the scope of your connections to further secure your database by having a "read only" connection and a "read/write", even if you're only working with one database.

To use transactions, simply call the `Database::beginTransaction()` method with your desired connection and then execute your queries normally. When you're done, simply call `Database::RollBack()` or `Database::commit()` on your connection.

Since this was built for a web app, a persistent connection is assumed to be desirable. If you do not want a persistent connection, simply add the line `PERSISTENT=FALSE` to your connection's ini file. If you aren't using ini files and are simply passing connection arrays, simply include the `persistent` key with the value `false`.

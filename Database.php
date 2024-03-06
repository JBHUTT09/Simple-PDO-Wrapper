<?php

/**
 * Simple PDO database wrapper class.
 */
class Database {
	/* Constants */
	public const int QUERY_RETURN_PDO = 0;
	public const int QUERY_RETURN_ID = 1;
	public const int QUERY_RETURN_ROWS = 2;
	
	/**
	 * An array of database connection arrays. These connection arrays represent
	 * connections available during execution.
	 * 
	 * <code>
	 *     // an example of $connections
	 *     array(
	 *         'rw' => array(
	 *             'connection_id' => 'rw',
	 *             'host' => 'localhost',
	 *             'database' => 'my_database',
	 *             'username' => 'my_database_user',
	 *             'password' => 'my_user_password',
	 *         ),
	 *         ...
	 *     );
	 * </code>
	 * 
	 * @var array
	 */
	protected $connections;
	
	/**
	 * The required keys of a connection array.
	 * 
	 * @var array
	 */
	protected $required_connection_keys = [
		'connection_id',
		'host',
		'database',
		'username',
		'password',
	];
	
	/**
	 * @param string|array $connections A path to an ini file, a directory of ini files, or an array of connection arrays.
	 */
	public function __construct( string|array $connections ) {
		if ( is_string( $connections ) ) {
			$connections = is_dir( $connections ) ? static::parseConnectionInis( $connections ) : [ static::parceConnectionIni( $connections ) ];
		}
		foreach ( $connections as $connection ) {
			$this->addConnection( $connection );
		}
	}
	
	/**
	 * Parses a directory of connection ini files into a connections array.
	 * 
	 * @param string $dir The directory containing the ini files.
	 * @return array
	 */
	public static function parseConnectionInis( string $dir ):array {
		$connections = [];
		foreach ( new \DirectoryIterator( $dir ) as $item ) {
			if ( !$item->isDot() ) {
				$connections[] = static::parceConnectionIni( $item->getRealPath() );
			}
		}
		return $connections;
	}
	
	/**
	 * Parses an ini file into a connection array.
	 * Broken out into its own method for potential future cases
	 * in which someone extends this class and needs to further manipulate the data
	 * before returning it.
	 * 
	 * @param string $file The path to the ini file.
	 * @return array
	 */
	public static function parceConnectionIni( string $file ):array {
		return array_change_key_case( parse_ini_file( $file ) );
	}
	
	/**
	 * Adds a new connection to be available for queries.
	 * 
	 * @param array $connection An array containing the required keys for a database connection.
	 */
	public function addConnection( array $connection ):void {
		// ultimately an arbitrary limitation, but it should make debugging potential issues much simpler
		if ( isset( $this->connections[ $connection[ 'connection_id' ] ][ 'connection' ] ) ) {
			throw new \Exception( 'Cannot override established database connection.' );
		}
		foreach ( $this->required_connection_keys as $key ) {
			if ( !isset( $connection[ $key ] ) ) {
				throw new \Exception( "Second argument missing required key '{$key}'." );
			}
		}
		$this->connections[ $connection[ 'connection_id' ] ] = $connection;
	}
	
	/**
	 * Returns a PDO object representing the specified connection.
	 * 
	 * @param string $connection_id
	 * @return PDO
	 */
	public function &connect( string $connection_id ): \PDO {
		if ( !isset( $this->connections[ $connection_id ] ) ) {
			throw new \Exception( "Unknown database connection '{$connection_id}'." );
		}
		// establish a connection if one doesn't already exist
		if ( !isset( $this->connections[ $connection_id ][ 'connection' ] ) ) {
			$this->connections[  $connection_id ][ 'connection' ] = new \PDO(
				"mysql:host={$this->connections[ $connection_id ][ 'host' ]};dbname={$this->connections[ $connection_id ][ 'database' ]};charset=utf8",
				"{$this->connections[ $connection_id ][ 'username' ]}",
				"{$this->connections[ $connection_id ][ 'password' ]}",
				[ \PDO::ATTR_PERSISTENT => $this->connections[ $connection_id ][ 'persistent' ] ?? true ]
			);
		}
		return $this->connections[ $connection_id ][ 'connection' ];
	}
	
	/**
	 * Execute a query on the specified connection.
	 * 
	 * If you choose to have a PDOStatement returned, remember to call PDOStatement::closeCursor()
	 * when you're done with it or you may run into issues executing multiple queries, especially
	 * if you are using stored procedures.
	 * 
	 * @param string $connection_id
	 * @param string $query_text
	 * @param array $prepared (Optional) Array of values to be prepared.
	 * @param int $return (Optional) What to return. Defaults to the PDOStatement.
	 * @return PDOStatement|int The PDOStatement representing the query, the last inserted id, or the count of affected rows.
	 */
	public function query( string $connection_id, string $query_text, array $prepared=[], int $return=self::QUERY_RETURN_PDO ):\PDOStatement|int {
		$database = $this->connect( $connection_id );
		$pdo = $database->prepare( $query_text );
		// As of PHP 8.0.0 PDOStatement::execute() throws a PDOException on failure by
		// default, so there is no need to check if the returned value is false.
		// See: https://www.php.net/manual/en/pdo.error-handling.php
		$pdo->execute( $prepared );
		switch( $return ) {
			case self::QUERY_RETURN_ID:
				$pdo->closeCursor();
				return $database->lastInsertId();
			case self::QUERY_RETURN_ROWS:
				$count = $pdo->rowCount();
				$pdo->closeCursor();
				return $count;
			default:
				return $pdo;
		}
	}
	
	/**
	 * Begins a transaction on the specified connection.
	 * 
	 * @param string $connection_id
	 */
	public function beginTransaction( string $connection_id ):void {
		$this->connect( $connection_id )->beginTransaction();
	}
	
	/**
	 * Rolls back a transaction on the specified connection.
	 * 
	 * @param string $connection_id
	 */
	public function rollBack( string $connection_id ):void {
		$this->connect( $connection_id )->rollBack();
	}
	
	/**
	 * Commits a transaction on the specified connection.
	 * 
	 * @param string $connection_id
	 */
	public function commit( string $connection_id ):void {
		$this->connect( $connection_id )->commit();
	}
}

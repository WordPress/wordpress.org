#!/usr/bin/env php
<?php

$opts = getopt( 'u:p:h:d:f:' );
if ( ! isset( $opts['u'], $opts['p'], $opts['h'], $opts['d'], $opts['f'] ) ) {
	echo "This script migrates a Trac SQLite file to a MySQL database that is pre-initialized with empty tables.\n\n";
	echo "Required flags:\n -u MySQL user\n -p MySQL password\n -h MySQL host name\n -d MySQL DB name\n -f Path to SQLite file\n";
	exit( 1 );
}

define( 'SQLITE_DB', $opts['f'] );
define( 'DB_NAME',   $opts['d'] );
define( 'DB_USER',   $opts['u'] );
define( 'DB_PASS',   $opts['p'] );
define( 'DB_HOST',   $opts['h'] );

$start = microtime( true );

$tables = array(
	'attachment', 'auth_cookie', 'cache', 'component', 'enum', 'milestone', 'node_change',
	'permission', 'report', 'repository', 'revision', 'session', 'session_attribute',
	'system', 'ticket', 'ticket_change', 'ticket_custom', 'version', 'wiki',
);

$mysql = new wpdb_insert( DB_USER, DB_PASS, DB_NAME, DB_HOST );

try {
	$sqlite = new PDO( 'sqlite:' . SQLITE_DB );
} catch ( PDOException $Exception ) {
	print_r( $Exception );
	unset( $sqlite, $mysql );
	return;
}

// Strict mode is on to ensure we don't have silent truncation.
$mysql->set_strict_mode();

// Cheap but it works. Tested on a Trac with 300,000 ticket changes, 23,000 tickets, 60,000 node changes. Took ~3 minutes.
foreach ( $tables as $table ) {
	$raw_fields = $mysql->get_results( "DESCRIBE $table" );
	$fields = $formats = array();

	foreach ( $raw_fields as $field ) {
		$fields[] = $field->Field;
		$formats[] = false === strpos( $field->Type, 'int(' ) ? '%s' : '%d';
	}

	foreach ( $sqlite->query( "SELECT * FROM $table" ) as $item ) {
		$values = array();
		foreach ( $fields as $field ) {
			$values[ $field ] = $item[ $field ];
		}
		$mysql->insert( $table, $values, $formats );
	}
}

echo "Done. " . ( microtime( true ) - $start ) . " seconds.\n";

if ( $mysql->errors ) {
	echo "There were errors:\n";
	var_dump( $mysql->errors );
}

class wpdb_insert {
	protected $dbh;

	public $errors = array();

	function __construct( $dbuser, $dbpassword, $dbname, $dbhost ) {
		if ( ! $this->dbh = mysql_connect( $dbhost, $dbuser, $dbpassword ) ) {
			die( 'Could not connect to MySQL DB.' );
		}
		if ( ! mysql_select_db( $dbname, $this->dbh ) ) {
			die( 'Could not select the MySQL database.' );
		}
	}

	function escape( &$string ) {
		if ( ! is_float( $string ) ) {
			$string = mysql_real_escape_string( $string, $this->dbh );
		}
	}

	function insert( $table, $data, $formats ) {
		$query = "INSERT INTO `$table` (`" . implode( '`,`', array_keys( $data ) ) . "`) VALUES (" . implode( ", ", $formats ) . ")";
		$query = preg_replace( '|(?<!%)%s|', "'%s'", $query );
		array_walk( $data, array( $this, 'escape' ) );
		$query = @vsprintf( $query, $data );
		mysql_query( $query );
		if ( $error = mysql_error() ) {
			error_log( sprintf( 'Database error %1$s for query %2$s', $error, $query ) );
			$this->errors[ $query ] = $error;
		}
	}

	function set_strict_mode() {
		mysql_query( "SET SQL_MODE='STRICT_ALL_TABLES'" );
	}

	function get_results( $query ) {
		$resource = mysql_query( $query, $this->dbh );
		$results = array();
		while ( $row = mysql_fetch_object( $resource ) ) {
			$results[] = $row;
		}
		return $results;
	}
}

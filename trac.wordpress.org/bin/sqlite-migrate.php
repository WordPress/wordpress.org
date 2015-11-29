#!/usr/bin/env php
<?php

error_reporting(-1);
ini_set('memory_limit', '2G');

$opts = getopt( 'u:p:h:d:f:' );
if ( ! isset( $opts['u'], $opts['p'], $opts['h'], $opts['d'], $opts['f'] ) ) {
	echo "This script migrates a MySQL database to a Trac SQLite file that is pre-initialized with empty tables.\n\n";
	echo "Required flags:\n -u MySQL user\n -p MySQL password\n -h MySQL host name\n -d MySQL DB name\n -f Path to SQLite file\n";
	exit( 1 );
}

define( 'SQLITE_DB', realpath( $opts['f'] ) );
define( 'DB_NAME',   $opts['d'] );
define( 'DB_USER',   $opts['u'] );
define( 'DB_PASS',   $opts['p'] );
define( 'DB_HOST',   $opts['h'] );

$start = microtime( true );

$tables = array(
	'attachment', 'auth_cookie', 'cache',
	'report', 'repository', 'revision',
	'system', 'ticket', 'ticket_change', 'wiki',
);

// Remaining tables are ASCII data, and some of them are huge (session, session_attribute, node_change).
// See sqlite-migrate.sh for the rest.

$mysql = new wpdb_quick( DB_USER, DB_PASS, DB_NAME, DB_HOST );

try {
	$sqlite = new PDO( 'sqlite:' . SQLITE_DB );
} catch ( PDOException $Exception ) {
	print_r( $Exception );
	unset( $sqlite, $mysql );
	return;
}

$errors = array();

foreach ( $tables as $table ) {
	$sqlite->query( "BEGIN TRANSACTION" );

	$sqlite->query( "PRAGMA synchronous=OFF" );
	$sqlite->query( "PRAGMA count_changes=OFF" );
	$sqlite->query( "PRAGMA journal_mode=MEMORY" );
	$sqlite->query( "PRAGMA temp_store=MEMORY" );

	$raw_fields = $mysql->get_results( "DESCRIBE $table" );
	$fields = $numbers = array();

	foreach ( $raw_fields as $field ) {
		$fields[] = $field->Field;
		if ( false !== strpos( $field->Type, 'int(' ) ) {
			$numbers[ $field->Field ] = true;
		}
	}

	$placeholders = implode( ', ', array_fill( 0, count( $fields ), '?' ) );
	$fields = implode( ', ', $fields );

	$sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";
	$query = $sqlite->prepare( $sql );
	$results = $mysql->get_results( "SELECT * FROM $table" );
	printf( "Processing $table with %d results.\n", count( $results ) );

	foreach ( $results as $row ) {
		try {
			$row = (array) $row;
			foreach ( $numbers as $key => $true ) {
				$row[ $key ] = (int) $row[ $key ];
			}
			$query->execute( (array) $item );
			// var_dump( $row, $query->errorInfo() );
		} catch ( PDOException $e ) {
			$errors[] = $e->getCode() . ': ' . $e->getMessage();
		}
	}

	$sqlite->query( "END TRANSACTION" );
}

echo "Done.. " . ( microtime( true ) - $start ) . " seconds.\n";

if ( $errors ) {
	echo "There were errors:\n";
	var_dump( $errors );
}

class wpdb_quick {
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

	function get_results( $query ) {
		$resource = mysql_query( $query, $this->dbh );
		$results = array();
		while ( $row = mysql_fetch_object( $resource ) ) {
			$results[] = $row;
		}
		return $results;
	}
}

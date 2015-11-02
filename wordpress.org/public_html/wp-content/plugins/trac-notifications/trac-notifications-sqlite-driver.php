<?php

define( 'OBJECT_K', PDO::FETCH_OBJ | PDO::FETCH_GROUP | PDO::FETCH_UNIQUE );
define( 'OBJECT',   PDO::FETCH_OBJ );
define( 'ARRAY_A',  PDO::FETCH_ASSOC );
define( 'ARRAY_N',  PDO::FETCH_NUM );

class Trac_Notifications_SQLite_Driver /* implements wpdb_interface */ {
	function __construct( $path ) {
		$this->db = new PDO( 'sqlite:' . $path );
	}

	public function prepare( $query, $args ) {
		$args = func_get_args();
		array_shift( $args );
		// If args were passed as an array (as in vsprintf), move them up
		if ( isset( $args[0] ) && is_array($args[0]) ) {
			$args = $args[0];
		}
		$args = array_map( array( $this->db, 'quote' ), $args );
		$query = preg_replace( '|(?<!%)%d|', '%s', $query );
		return vsprintf( $query, $args );
	}

	public function get_results( $query, $output = OBJECT ) {
		if ( $q = $this->db->query( $query ) ) {
			return $q->fetchAll( $output );
		}
	}

	public function get_col( $query ) {
		if ( $q = $this->db->query( $query ) ) {
			return $q->fetchAll( PDO::FETCH_COLUMN );
		}
	}

	public function get_var( $query ) {
		if ( $q = $this->db->query( $query ) ) {
			$var = $q->fetchColumn();
			if ( $var !== false ) {
				return $var;
			}
		}
		return null;
	}

	public function get_row( $query, $output = OBJECT ) {
		if ( $q = $this->db->query( $query ) ) {
			return $q->fetch( $output );
		}
	}

	public function delete( $table, $where ) {
		$fields = 'AND ' . implode( ' = %s AND ', array_keys( $where ) ) . ' = %s';
		$query = $this->prepare(
			"DELETE FROM $table WHERE 1=1 $fields",
			array_values( $where )
		);
		$this->db->query( $query );
	}

	public function insert( $table, $args ) {
		$fields = "'" . implode( "', '", array_keys( $args ) ) . "'";
		$placeholders = implode( ', ', array_fill( 0, count( $args ), '%s' ) );
		$query = $this->prepare(
			"INSERT INTO $table ($fields) VALUES ($placeholders)",
			array_values( $args )
		);
		return (bool) $this->db->query( $query );
	}
}

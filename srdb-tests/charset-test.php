<?php

// Connection details
$user = 'root';
$pass = '123';
$host = '127.0.0.1';

// Test data
$original = array(
				'number' => 123,
				'float' => 12.345,
				'string' => 'serialised string',
				'accented' => 'föó ßåŗ',
				'unicode' => '❤ ☀ ☆ ☂ ☻ ♞ ☯',
				'url' => 'http://example.com/'
			);
$serialised = serialize( $original );

// Connect
$x = new PDO( "mysql:host={$host}", $user, $pass );

// Create our schema
$x->query( "CREATE DATABASE IF NOT EXISTS `encode` CHARACTER SET = 'utf8' COLLATE = 'utf8_general_ci';" );
$x->query( 'SET NAMES utf8;' );

// Create a table for each encoding type and stick the encoded array in it.
$charsets = $x->query( 'SELECT CHARACTER_SET_NAME as charset, COLLATION_NAME as collation FROM information_schema.COLLATION_CHARACTER_SET_APPLICABILITY;' );

if ( method_exists( $charsets, 'fetch' ) ) {
	while( $collation = $charsets->fetch() ) {

		$col = $collation[ 'collation' ];
		$charset = $collation[ 'charset' ];
		$tbl_name = $collation[ 'collation' ];

		// Create the table for the collation
		$x->query( "DROP TABLE IF EXISTS `encode`.`{$tbl_name}`" );
		$x->query( "CREATE TABLE  `encode`.`{$tbl_name}` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`number` decimal(10,0) NOT NULL,
			`float` float NOT NULL,
			`string` longtext COLLATE {$col} NOT NULL,
			`accented` longtext COLLATE {$col} NOT NULL,
			`unicode` longtext COLLATE {$col} NOT NULL,
			`url` longtext COLLATE {$col} NOT NULL,
			`serialised` longtext CHARACTER SET {$charset} NOT NULL,
			PRIMARY KEY (`id`)
		  ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET={$charset} COLLATE={$col};" );

		// Set the name space to match to charset
		switch( $charset ) {
			// If I uncomment this utf-16 and utf-32 work, I don't know why though.
			//case 'utf16':
			//case 'utf32':
			//	$x->query( "SET NAMES utf8;" );
			//	break;

			default:
				$x->query( "SET NAMES {$charset};" );
		}

		// Insert our test data
		if ( !$x->query( "INSERT INTO encode.`{$tbl_name}` ( number, float, string, accented, unicode, url, serialised )
							VALUES (
								 {$original['number']},
								 {$original['float']},
								'{$original['string']}',
								'{$original['accented']}',
								'{$original['unicode']}',
								'{$original['url']}',
								'{$serialised}'
							);" ) )
			echo "<pre style=\"color:blue\">Insert Failed: {$col}:{$charset}</pre>";

		// Set names to match table's charset
		$x->query( "SET NAMES {$charset};" );

		// Reclaim what we just dumped into the db and compare
		$q = $x->query( "SELECT serialised FROM encode.{$tbl_name} ORDER BY id DESC LIMIT 1;" );
		if ( method_exists( $q, 'fetch' ) ) {
			while(  $var = $q->fetch( )[0] ) {
				$unserialized = @unserialize( $var );

				if ( !$unserialized || array_diff( $unserialized, $original ) ) {
					echo "<pre style=\"color:red\">Failed: {$col}:{$charset}</pre>";
				}
				else {
					echo "<pre style=\"color:green\">Success: {$col}:{$charset}</pre>";
				}
			}
		}
	}
}

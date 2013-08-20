#!/usr/bin/php -q

<?php
/**
 * To run this script, execute something like this:
 * `./searchreplacedb2cli.php -h localhost -u root -d test -c utf\-8 -s "findMe" -r "replaceMe"`
 * use the --dry-run flag to do a dry run without searching/replacing.
 * 
 * You can restrict the search/replace to specific tables, use arg 
 * `--tables = "table1,table2"` or `-ttable1,table2`
 */
// Wrap require with OB functions for not display HTML on shell
ob_start();

// source: https://github.com/interconnectit/Search-Replace-DB/blob/master/searchreplacedb2.php
require_once('searchreplacedb2.php'); // include the proper srdb script
echo "########################### Ignore Above ###############################".PHP_EOL.PHP_EOL;

ob_end_clean();

/* Flags for options, all values required */
$shortopts = "";
$shortopts .= "h:"; // host name // $host
$shortopts .= "d:"; // database name // $data
$shortopts .= "u:"; // user name // $user
$shortopts .= "p:"; // password // $pass
$shortopts .= "c:"; // character set // $char
$shortopts .= "t:"; // tables // $tables
$shortopts .= "s:"; // search // $srch
$shortopts .= "r:"; // replace // $rplc
$shortopts .= ""; // These options do not accept values
// All long options require values
$longopts = array(
	"host:", // $host
	"database:", // $data
	"user:", // $user
	"pass:", // $pass
	"charset:", // $char
	"tables:", // $tables
	"search:", // $srch
	"replace:", // $rplc
	"help", // $help_text
	//@TODO write dry-run to also do a search without a replace.
	"dry-run", // engage in a dry run, print options, show results
);

/* Store arg values */
$arg_count = $_SERVER["argc"];
$args_array = $_SERVER["argv"];
$options = getopt( $shortopts, $longopts ); // Store array of options and values

/* Map options to correct vars from srdb script */
if ( isset( $options["h"] ) ) {
	$host = $options["h"];
} elseif ( isset( $options["host"] ) ) {
	$host = $options["host"];
} else {
	die("Abort! Host name required, use --host or -h".PHP_EOL);
}

if ( isset( $options["d"] ) ) {
	$data = $options["d"];
} elseif ( isset( $options["database"] ) ) {
	$data = $options["database"];
} else {
	die("Abort! Database name required, use --database or -d".PHP_EOL);
}

if ( isset( $options["u"] ) ) {
	$user = $options["u"];
} elseif ( isset( $options["user"] ) ) {
	$user = $options["user"];
}

if ( isset( $options["p"] ) ) {
	$pass = $options["p"];
} elseif ( isset( $options["pass"] ) ) {
	$pass = $options["pass"];
}

if ( isset( $options["c"] ) ) {
	$char = $options["c"];
} elseif ( isset( $options["charset"] ) ) {
	$char = $options["charset"];
}

if ( isset( $options["s"] ) ) {
	$srch = $options["s"];
} elseif ( isset( $options["search"] ) ) {
	$srch = $options["search"];
}

if ( isset( $options["r"] ) ) {
	$rplc = $options["r"];
} elseif ( isset( $options["replace"] ) ) {
	$rplc = $options["replace"];
}

// Tables to scanned
if ( isset( $options["t"] ) ) {
	$tables = $options["t"];
} elseif ( isset( $options["tables"] ) ) {
	$tables = $options["tables"];
} else {
	$tables = "";
}

echo "########################### Welcome to Search Replace DB script ###############################".PHP_EOL.PHP_EOL;

/* Show values if this is a dry-run */
if ( isset( $options["dry-run"] ) ) {
	echo "Are you sure these are correct?".PHP_EOL;
}
echo "host: " . $host . PHP_EOL;
echo "database: " . $data . PHP_EOL;
echo "user: " . $user . PHP_EOL;
echo "pass: " . $pass . PHP_EOL;
echo "charset: " . $char . PHP_EOL;
echo "search: " . $srch . PHP_EOL;
echo "replace: " . $rplc . PHP_EOL;
echo "tables restriction: " . $tables . PHP_EOL . PHP_EOL;


/* Reproduce what's done in Case 3 to test the server before proceeding */
$connection = @mysql_connect( $host, $user, $pass );
if ( !$connection ) {
	die( 'MySQL Connection Error: ' . mysql_error() );
}

if ( !empty( $char ) ) {
	if ( function_exists( 'mysql_set_charset' ) ) {
		mysql_set_charset( $char, $connection );
	} else {
		mysql_query( 'SET NAMES ' . $char, $connection ); // Shouldn't really use this, but there for backwards compatibility
	}
}

// Test database select
$db_selected = @mysql_select_db( $data, $connection );
if ( !$db_selected ) {
	die( 'MySQL Can\'t use database: ' . mysql_error() );
}

// Do we have any tables and if so build the all tables array
$all_tables = array( );
$all_tables_mysql = @mysql_query( 'SHOW TABLES', $connection );
if ( !$all_tables_mysql ) {
	 die('MySQL Invalid query: ' . mysql_error());
} else {
	while ( $table = mysql_fetch_array( $all_tables_mysql ) ) {
		$all_tables[] = $table[0];
	}
	echo "All tables: ";
	foreach ( $all_tables as $a_table ) {
		echo $a_table . ", ";
	}
	mysql_free_result($all_tables_mysql);
}

// Tables restriction ?
if ( !empty( $tables ) ) {
	// Explode strings to array
	$tables = explode( ',', $tables );

	// Remove superfluous whitespace
	$tables = array_map( 'trim', $tables );

	// Check and clean the tables array
	$tables = array_filter( $tables, 'check_table_array' );

	// Make an error message if no tables
	if ( empty( $tables ) ) {
		die( 'You didn\'t select any tables, or not existing tables.' );
	}
} else {
	// No restriction ? Use all tables !
	$tables = $all_tables;
}

/* Execute Case 5 with the actual search + replace */

if ( !isset( $options["dry-run"] ) ) { // check if dry-run
	echo PHP_EOL.PHP_EOL."Working...";

	if ( !defined( 'STDIN' ) ) { // Only for NO CLI call, CLI set no timeout, no memory limit
		@ set_time_limit( 60 * 10 );
		// Try to push the allowed memory up, while we're at it
		@ ini_set( 'memory_limit', '1024M' );
	}

	// Process the tables
	if ( isset( $connection ) ) {
		$report = icit_srdb_replacer( $connection, $srch, $rplc, $tables );
	}

	// Output any errors encountered during the db work.
	if ( !empty( $report['errors'] ) && is_array( $report['errors'] ) ) {
		echo "Find/Replace Errors: ".PHP_EOL;
		foreach ( $report['errors'] as $error ) {
			echo $error . PHP_EOL;
		}
	}

	// Calc the time taken.
	$time = array_sum( explode( ' ', $report['end'] ) ) - array_sum( explode( ' ', $report['start'] ) );

	echo "Done. Report:".PHP_EOL.PHP_EOL;
	printf( 'In the process of replacing "%s" with "%s" we scanned %d tables with a total of %d rows, %d cells were changed and %d db update performed and it all took %f seconds.', $srch, $rplc, $report['tables'], $report['rows'], $report['change'], $report['updates'], $time );
	echo PHP_EOL.PHP_EOL;
}
?>
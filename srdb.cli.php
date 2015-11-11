#!/usr/bin/php -q
<?php

/**
 * To run this script, execute something like this:
 * `./srdb.cli.php -h localhost -u root -n test -s "findMe" -r "replaceMe"`
 * use the --dry-run flag to do a dry run without searching/replacing.
 */

// php 5.3 date timezone requirement, shouldn't affect anything
date_default_timezone_set( 'Europe/London' );

// include the srdb class
require_once( realpath( dirname( __FILE__ ) ) . '/srdb.class.php' );

$opts = array(
	'h:' => 'host:',
	'n:' => 'name:',
	'u:' => 'user:',
	'p:' => 'pass:',
	'c:' => 'char:',
	's:' => 'search:',
	'r:' => 'replace:',
	't:' => 'tables:',
	'i:' => 'include-cols:',
	'x:' => 'exclude-cols:',
	'g' => 'regex',
	'l:' => 'pagesize:',
	'z' => 'dry-run',
	'e:' => 'alter-engine:',
	'a:' => 'alter-collation:',
	'v:' => 'verbose:',
	'port:',
	'help'
);

$required = array(
	'h:',
	'n:',
	'u:',
	'p:'
);

function strip_colons( $string ) {
	return str_replace( ':', '', $string );
}

// store arg values
$arg_count 	= $_SERVER[ 'argc' ];
$args_array = $_SERVER[ 'argv' ];

$short_opts = array_keys( $opts );
$short_opts_normal = array_map( 'strip_colons', $short_opts );

$long_opts = array_values( $opts );
$long_opts_normal = array_map( 'strip_colons', $long_opts );

// store array of options and values
$options = getopt( implode( '', $short_opts ), $long_opts );

if ( isset( $options[ 'help' ] ) ) {
	echo "
#####################################################################

interconnect/it Safe Search & Replace tool

#####################################################################

This script allows you to search and replace strings in your database
safely without breaking serialised PHP.

Please report any bugs or fork and contribute to this script via
Github: https://github.com/interconnectit/search-replace-db

Argument values are strings unless otherwise specified.

ARGS
  -h, --host
    Required. The hostname of the database server.
  -n, --name
    Required. Database name.
  -u, --user
    Required. Database user.
  -p, --pass
    Required. Database user's password.
  --port
    Optional. Port on database server to connect to.
    The default is 3306. (MySQL default port).
  -s, --search
    String to search for or `preg_replace()` style
    regular expression.
  -r, --replace
    None empty string to replace search with or
    `preg_replace()` style replacement.
  -t, --tables
    If set only runs the script on the specified table, comma
    separate for multiple values.
  -i, --include-cols
    If set only runs the script on the specified columns, comma
    separate for multiple values.
  -x, --exclude-cols
    If set excludes the specified columns, comma separate for
    multiple values.
  -g, --regex [no value]
    Treats value for -s or --search as a regular expression and
    -r or --replace as a regular expression replacement.
  -l, --pagesize
    How rows to fetch at a time from a table.
  -z, --dry-run [no value]
    Prevents any updates happening so you can preview the number
    of changes to be made
  -e, --alter-engine
    Changes the database table to the specified database engine
    eg. InnoDB or MyISAM. If specified search/replace arguments
    are ignored. They will not be run simultaneously.
  -a, --alter-collation
    Changes the database table to the specified collation
    eg. utf8_unicode_ci. If specified search/replace arguments
    are ignored. They will not be run simultaneously.
  -v, --verbose [true|false]
    Defaults to true, can be set to false to run script silently.
  --help
    Displays this help message ;)
";
	exit;
}

// missing field flag, show all missing instead of 1 at a time
$missing_arg = false;

// check required args are passed
foreach( $required as $key ) {
	$short_opt = strip_colons( $key );
	$long_opt = strip_colons( $opts[ $key ] );
	if ( ! isset( $options[ $short_opt ] ) && ! isset( $options[ $long_opt ] ) ) {
		fwrite( STDERR, "Error: Missing argument, -{$short_opt} or --{$long_opt} is required.\n" );
		$missing_arg = true;
	}
}

// bail if requirements not met
if ( $missing_arg ) {
	fwrite( STDERR, "Please enter the missing arguments.\n" );
	exit( 1 );
}

// new args array
$args = array(
	'verbose' => true,
	'dry_run' => false
);

// create $args array
foreach( $options as $key => $value ) {

	// transpose keys
	if ( ( $is_short = array_search( $key, $short_opts_normal ) ) !== false )
		$key = $long_opts_normal[ $is_short ];

	// boolean options as is, eg. a no value arg should be set true
	if ( in_array( $key, $long_opts ) )
		$value = true;
	
	switch ( $key ) {
		// boolean options.
		case 'verbose':
			$value = (boolean)filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		break;
	}

	// change to underscores
	$key = str_replace( '-', '_', $key );

	$args[ $key ] = $value;
}

// modify the log output
class icit_srdb_cli extends icit_srdb {

	public function log( $type = '' ) {

		$args = array_slice( func_get_args(), 1 );

		$output = "";

		switch( $type ) {
			case 'error':
				list( $error_type, $error ) = $args;
				$output .= "$error_type: $error";
				break;
			case 'search_replace_table_start':
				list( $table, $search, $replace ) = $args;
				$output .= "{$table}: replacing {$search} with {$replace}";
				break;
			case 'search_replace_table_end':
				list( $table, $report ) = $args;
				$time = number_format( $report[ 'end' ] - $report[ 'start' ], 8 );
				$output .= "{$table}: {$report['rows']} rows, {$report['change']} changes found, {$report['updates']} updates made in {$time} seconds";
				break;
			case 'search_replace_end':
				list( $search, $replace, $report ) = $args;
				$time = number_format( $report[ 'end' ] - $report[ 'start' ], 8 );
				$dry_run_string = $this->dry_run ? "would have been" : "were";
				$output .= "
Replacing {$search} with {$replace} on {$report['tables']} tables with {$report['rows']} rows
{$report['change']} changes {$dry_run_string} made
{$report['updates']} updates were actually made
It took {$time} seconds";
				break;
			case 'update_engine':
				list( $table, $report, $engine ) = $args;
				$output .= $table . ( $report[ 'converted' ][ $table ] ? ' has been' : 'has not been' ) . ' converted to ' . $engine;
				break;
			case 'update_collation':
				list( $table, $report, $collation ) = $args;
				$output .= $table . ( $report[ 'converted' ][ $table ] ? ' has been' : 'has not been' ) . ' converted to ' . $collation;
				break;
		}

		if ( $this->verbose )
			echo $output . "\n";

	}

}

$report = new icit_srdb_cli( $args );

// Only print a separating newline if verbose mode is on to separate verbose output from result
if ($args[ 'verbose' ]) {
	echo "\n";
}

if ( $report && ( ( isset( $args[ 'dry_run' ] ) && $args[ 'dry_run' ] ) || empty( $report->errors[ 'results' ] ) ) ) {
	echo "And we're done!\n";
} else {
	echo "Check the output for errors. You may need to ensure verbose output is on by using -v or --verbose.\n";
}

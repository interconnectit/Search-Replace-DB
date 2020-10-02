#!/usr/bin/env -S php -q
<?php
/*
 * This file is part of Search-Replace-DB.
 * Copyright © 2020  Interconnect IT Limited
 *
 * Search-Replace-DB is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or any later version.
 *
 * Search-Replace-DB is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Search-Replace-DB.
 * If not, see <https://www.gnu.org/licenses/>.
 */

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
    [ 'h:', 'host:', 'Required. The hostname of the database server.', ],
    [ 'n:', 'name:', 'Required. Database name.', ],
    [ 'u:', 'user:', 'Required. Database user.', ],
    [ 'p:', 'pass:', 'Database user\'s password.', ],
    [ 'P:', 'port:', 'Optional. Port on database server to connect to. The default is 3306. (MySQL default port).', ],
    [ 's:', 'search:', 'String to search for or `preg_replace()` style regular expression.', ],
    [ 'r:', 'replace:', 'None empty string to replace search with or `preg_replace()` style replacement.', ],
    [ 't:', 'tables:', 'If set only runs the script on the specified table, comma separate for multiple values.', ],
    [ 'w:', 'exclude-tables:', 'If set excluded the specified tables, comma separate for multuple values.', ],
    [
        'i:',
        'include-cols:',
        'If set only runs the script on the specified columns, comma separate for multiple values.',
    ],
    [ 'x:', 'exclude-cols:', 'If set excludes the specified columns, comma separate for multiple values.', ],
    [
        'g',
        'regex',
        'Treats value for -s or --search as a regular expression and -r or --replace as a regular expression replacement.',
        '[no value]'
    ],
    [ 'l:', 'pagesize:', 'How rows to fetch at a time from a table.', ],
    [
        'z',
        'dry-run',
        'Prevents any updates happening so you can preview the number of changes to be made',
        '[no value]'
    ],
    [
        'e:',
        'alter-engine:',
        'Changes the database table to the specified database engine eg. InnoDB or MyISAM. If specified search/replace arguments are ignored. They will not be run simultaneously.',
    ],
    [
        'a:',
        'alter-collation:',
        'Changes the database table to the specified collation eg. utf8_unicode_ci. If specified search/replace arguments are ignored. They will not be run simultaneously.',
    ],
    [ 'v:', 'verbose:', 'Defaults to true, can be set to false to run script silently.', '[true|false]' ],
    [ '', 'debug:', 'Defaults to false, prints more verbose errors.', '[true|false]' ],

    [ '', 'ssl-key:', 'Define the path to the SSL KEY file.', ],
    [ '', 'ssl-cert:', 'Define the path to the SSL certificate file.', ],
    [ '', 'ssl-ca:', 'Define the path to the certificate authority file.', ],
    [ '', 'ssl-ca-dir:', 'Define the path to a directory that contains trusted SSL CA certificates in PEM format.', ],
    [ '', 'ssl-cipher:', 'Define the cipher to use for SSL.', ],
    [ '', 'ssl-check:', 'Check the SSL certificate, default to True.', '[true|false]' ],

    [ '', 'allow-old-php', 'Suppress the check for PHP version, use it at your own risk!' ],

    [ '', 'help', 'Displays this help message ;)', ],
);

$required = array(
    'h' => 'host',
    'n' => 'name',
    'u' => 'user'
);

function strip_colons( $string ) {
    return str_replace( ':', '', $string );
}

// store arg values
$arg_count  = $_SERVER['argc'];
$args_array = $_SERVER['argv'];

$short_opts        = array_filter( array_column( $opts, 0 ) );
$short_opts_normal = array_map( 'strip_colons', $short_opts );

$long_opts        = array_filter( array_column( $opts, 1 ) );
$long_opts_normal = array_map( 'strip_colons', $long_opts );

// store array of options and values
$options = getopt( implode( '', $short_opts ), $long_opts );

if ( isset( $options['help'] ) ) {
    echo "
#####################################################################

Interconnect/it Safe Search & Replace tool

#####################################################################

This script allows you to search and replace strings in your database
safely without breaking serialised PHP.

Please report any bugs or fork and contribute to this script via
Github: https://github.com/interconnectit/search-replace-db

Argument values are strings unless otherwise specified.

ARGS

";

    foreach ( $opts as $argument ) {
        echo '  ';
        if ( $argument[0] ) {
            echo '-' . strip_colons( $argument[0] ) . ', ';
        }
        if ( $argument[1] ) {
            echo '--' . strip_colons( $argument[1] ) . ' ';
        }
        if ( isset( $argument[3] ) ) {
            echo $argument[3];
        }
        echo "\n";
        if ( $argument[2] ) {
            echo '    ' . wordwrap( $argument[2], 65, "\n    " );
        }
        echo "\n\n";
    }

    echo "\nSearch-Replace-DB  Copyright © 2020  Interconnect IT Limited
This program comes with ABSOLUTELY NO WARRANTY;
This is free software, and you are welcome to redistribute it
under certain conditions; see README for details.

";
    exit;
}

// missing field flag, show all missing instead of 1 at a time
$missing_arg = false;

if ( ! extension_loaded( "mbstring" ) ) {
    fwrite( STDERR, "This script requires mbstring. Please install mbstring and try again.\n" );
    exit ( 1 );
}

// check required args are passed
foreach ( $required as $short_opt => $long_opt ) {

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
    'verbose'       => true,
    'ssl_check'     => true,
    'dry_run'       => false,
    'debug'         => false,
    'allow_old_php' => false
);

if ( isset( $options['allow-old-php'] ) ) {
    $args['allow_old_php'] = true;
}

// create $args array
foreach ( $options as $key => $value ) {

    // transpose keys
    if ( ( $is_short = array_search( $key, $short_opts_normal ) ) !== false ) {
        $key = $long_opts_normal[ $is_short ];
    }

    if ( in_array( $key, [ 'search', 'replace' ] ) && is_array( $jsonVal = @json_decode( $value, true ) ) ) {
        $args[ $key ] = $jsonVal;
        continue;
    }

    // boolean options as is, eg. a no value arg should be set true
    if ( in_array( $key, $long_opts ) ) {
        $value = true;
    }

    switch ( $key ) {
        // boolean options.
        case 'debug':
        case 'ssl-check':
        case 'verbose':
            $value = (boolean) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
            break;
    }

    // change to underscores
    $key = str_replace( '-', '_', $key );

    $args[ $key ] = $value;
}

if ( $args['allow_old_php'] === false ) {
    if ( version_compare( PHP_VERSION, '7.3' ) < 0 ) {
        fwrite( STDERR,
            "This script has been tested using PHP7.3 +, whereas your version is: " . PHP_VERSION . ". Although this script may work with older versions you do so at your own risk. Please update php and try again. \n" );
        exit( 1 );
    }
}

// modify the log output
class icit_srdb_cli extends icit_srdb {
    public function log( $type = '' ) {

        $args = array_slice( func_get_args(), 1 );

        $output = "";

        switch ( $type ) {
            case 'error':
                list( $error_type, $error ) = $args;
                $output .= "$error_type: $error";
                break;
            case 'search_replace_table_start':
                list( $table, $search, $replace ) = $args;

                if ( is_array( $search ) ) {
                    $search = implode( ' or ', $search );
                }
                if ( is_array( $replace ) ) {
                    $replace = implode( ' or ', $replace );
                }
                $output .= "{$table}: replacing {$search} with {$replace}";

                break;
            case 'search_replace_table_end':
                list( $table, $report ) = $args;
                $time = number_format( floatval( $report['end'] ) - floatval( $report['start'] ), 8 );
                if ( $time < 0 ) {
                    $time = $time * - 1;
                }
                $output .= "{$table}: {$report['rows']} rows, {$report['change']} changes found, {$report['updates']} updates made in {$time} seconds";
                break;
            case 'search_replace_end':
                list( $search, $replace, $report ) = $args;
                if ( is_array( $search ) ) {
                    $search = implode( ' or ', $search );
                }
                if ( is_array( $replace ) ) {
                    $replace = implode( ' or ', $replace );
                }
                $time = number_format( floatval( $report['end'] ) - floatval( $report['start'] ), 8 );
                if ( $time < 0 ) {
                    $time = $time * - 1;
                }
                $dry_run_string = $this->dry_run ? "would have been" : "were";
                $output         .= "
Replacing {$search} with {$replace} on {$report['tables']} tables with {$report['rows']} rows
{$report['change']} changes {$dry_run_string} made
{$report['updates']} updates were actually made
It took {$time} seconds";
                break;
            case 'update_engine':
                list( $table, $report, $engine ) = $args;
                $output .= $table . ( $report['converted'][ $table ] ? ' has been' : 'has not been' ) . ' converted to ' . $engine;
                break;
            case 'update_collation':
                list( $table, $report, $collation ) = $args;
                $output .= $table . ( $report['converted'][ $table ] ? ' has been' : 'has not been' ) . ' converted to ' . $collation;
                break;
        }

        if ( $this->verbose ) {
            echo $output . "\n";
        }

    }

}

$report = new icit_srdb_cli( $args );

// Only print a separating newline if verbose mode is on to separate verbose output from result
if ( $args['verbose'] ) {
    echo "\n";
}

if ( $report && ( ( isset( $args['dry_run'] ) && $args['dry_run'] ) || empty( $report->errors['results'] ) ) ) {
    echo "And we're done!\n";
} else {
    echo "Check the output for errors. You may need to ensure verbose output is on by using -v or --verbose.\n";
}

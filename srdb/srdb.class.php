<?php

/**
 *
 * Safe Search and Replace on Database with Serialized Data v2.2.0
 *
 * This script is to solve the problem of doing database search and replace when
 * some data is stored within PHP serialized arrays or objects.
 *
 * For more information, see
 * http://interconnectit.com/124/search-and-replace-for-wordpress-databases/
 *
 * To contribute go to
 * http://github.com/interconnectit/search-replace-db
 *
 * To use, load the script on your server and point your web browser to it.
 * In some situations, consider using the command line interface version.
 *
 * BIG WARNING!  Take a backup first, and carefully test the results of this
 * code. If you don't, and you vape your data then you only have yourself to
 * blame. Seriously.  And if your English is bad and you don't fully
 * understand the instructions then STOP. Right there. Yes. Before you do any
 * damage.
 *
 * USE OF THIS SCRIPT IS ENTIRELY AT YOUR OWN RISK. I/We accept no liability
 * from its use.
 *
 * First Written 2009-05-25 by David Coveney of Interconnect IT Ltd (UK)
 * http://www.davidcoveney.com or http://interconnectit.com
 * and released under the GPL v3
 * ie, do what ever you want with the code, and we take no responsibility for it
 * OK? If you don't wish to take responsibility, hire us at Interconnect IT Ltd
 * on +44 (0)151 331 5140 and we will do the work for you at our hourly rate,
 * minimum 1hr
 *
 * License: GPL v3
 * License URL: http://www.gnu.org/copyleft/gpl.html
 *
 *
 * Version 3.0:
 * 		* Major overhaul
 * 		* Multibyte string replacements
 * 		* Convert tables to InnoDB
 * 		* Convert tables to utf8_unicode_ci
 * 		* Preview/view changes in report
 * 		* Optionally use preg_replace()
 * 		* Better error/exception handling & reporting
 * 		* Reports per table
 * 		* Exclude/include multiple columns
 *
 * Version 2.2.0:
 * 		* Added remove script patch from David Anderson (wordshell.net)
 * 		* Added ability to replace strings with nothing
 *		* Copy changes
 * 		* Added code to recursive_unserialize_replace to deal with objects not
 * 		just arrays. This was submitted by Tina Matter.
 * 		ToDo: Test object handling. Not sure how it will cope with object in the
 * 		db created with classes that don't exist in anything but the base PHP.
 *
 * Version 2.1.0:
 *              - Changed to version 2.1.0
 *		* Following change by Sergei Biryukov - merged in and tested by Dave Coveney
 *              - Added Charset Support (tested with UTF-8, not tested on other charsets)
 *		* Following changes implemented by James Whitehead with thanks to all the commenters and feedback given!
 * 		- Removed PHP warnings if you go to step 3+ without DB details.
 * 		- Added options to skip changing the guid column. If there are other
 * 		columns that need excluding you can add them to the $exclude_cols global
 * 		array. May choose to add another option to the table select page to let
 * 		you add to this array from the front end.
 * 		- Minor tweak to label styling.
 * 		- Added comments to each of the functions.
 * 		- Removed a dead param from icit_srdb_replacer
 * Version 2.0.0:
 * 		- returned to using unserialize function to check if string is
 * 		serialized or not
 * 		- marked is_serialized_string function as deprecated
 * 		- changed form order to improve usability and make use on multisites a
 * 		bit less scary
 * 		- changed to version 2, as really should have done when the UI was
 * 		introduced
 * 		- added a recursive array walker to deal with serialized strings being
 * 		stored in serialized strings. Yes, really.
 * 		- changes by James R Whitehead (kudos for recursive walker) and David
 * 		Coveney 2011-08-26
 *  Version 1.0.2:
 *  	- typos corrected, button text tweak - David Coveney / Robert O'Rourke
 *  Version 1.0.1
 *  	- styling and form added by James R Whitehead.
 *
 *  Credits:  moz667 at gmail dot com for his recursive_array_replace posted at
 *            uk.php.net which saved me a little time - a perfect sample for me
 *            and seems to work in all cases.
 *
 */

class icit_srdb {

	/**
	 * @var array List of all the tables in the database
	 */
	public $all_tables = array();

	/**
	 * @var array Tables to run the replacement on
	 */
	public $tables = array();

	/**
	 * @var bool Use regular expressions to perform search and replace
	 */
	public $regex = false;

	/**
	 * @var bool Leave guid column alone
	 */
	public $guid = false;


	/**
	 * @var array Available engines
	 */
	public $engines = array();

	/**
	 * @var bool|string Convert to new engine
	 */
	public $alter_engine = false;

	/**
	 * @var bool|string Convert to new collation
	 */
	public $alter_collate = false;

	/**
	 * @var array Column names to exclude
	 */
	public $exclude_cols = array();

	/**
	 * @var array Column names to include
	 */
	public $include_cols = array();

	/**
	 * @var bool True if doing a dry run
	 */
	public $dry_run = true;

	/**
	 * @var string Database connection details
	 */
	public $name = '';
	public $user = '';
	public $pass = '';
	public $host = '127.0.0.1';
	public $charset = 'utf8';
	public $collate = '';


	/**
	 * @var array Stores a list of exceptions
	 */
	public $errors = array(
						'search' => array(),
						'db' => array(),
						'tables' => array(),
						'results' => array()
					);

	public $error_type = 'search';


	/**
	 * @var array Stores the report array
	 */
	public $report = array();


	/**
	 * @var int Number of modifications to return in report array
	 */
	public $report_change_num = 30;


	/**
	 * @var resource Database connection
	 */
	public $db;


	/**
	 * @var use PDO
	 */
	public $use_pdo = true;


	/**
	 * @var int How many rows to select at a time when replacing
	 */
	public $page_size = 50000;


	/**
	 * Searches for WP or Drupal context
	 * Checks for $_POST data
	 * Initialises database connection
	 * Handles ajax
	 * Runs replacement
	 *
	 * @param string $name    database name
	 * @param string $user    database username
	 * @param string $pass    database password
	 * @param string $host    database hostname
	 * @param string $search  search string / regex
	 * @param string $replace replacement string
	 * @param array $tables  tables to run replcements against
	 * @param bool $live    live run
	 * @param array $exclude_cols  tables to run replcements against
	 *
	 * @return void
	 */
	public function __construct( $args ) {

		$args = array_merge( array(
			'name' 			=> '',
			'user' 			=> '',
			'pass' 			=> '',
			'host' 			=> '',
			'search' 		=> '',
			'replace' 		=> '',
			'tables'		=> array(),
			'exclude_cols' 	=> array(),
			'include_cols' 	=> array(),
			'dry_run' 		=> true,
			'regex' 		=> false,
			'utf8' 			=> false,
			'innodb' 		=> false,
			'pagesize' 		=> 50000,
			'alter_engine' 	=> false,
			'alter_collate' => false
		), $args );

		// handle exceptions
		set_exception_handler( array( $this, 'exceptions' ) );
		// handle errors
		set_error_handler( array( $this, 'errors' ), E_ERROR | E_WARNING );

		// use unicode support for replacements
		mb_internal_encoding( 'UTF-8' );

		// allow a string for columns
		foreach( array( 'exclude_cols', 'include_cols' ) as $maybe_string_arg ) {
			if ( is_string( $args[ $maybe_string_arg ] ) )
				$args[ $maybe_string_arg ] = array_filter( array_map( 'trim', explode( ',', $args[ $maybe_string_arg ] ) ) );
		}

		// set class vars
		foreach( $args as $name => $value ) {
			if ( is_string( $value ) )
				$value = stripcslashes( $value );
			if ( is_array( $value ) )
				$value = array_map( 'stripcslashes', $value );
			$this->set( $name, $value );
		}

		// set up db connection
		$this->connection();

		// modify db table engine
		if ( $this->alter_engine ) {

			if ( in_array( $this->alter_engine, $this->engines ) ) {

				$this->report = $this->update_engine( $this->alter_engine, $this->tables );

				return $this->report;

			} else {

				$this->add_error( 'Cannot convert tables to unsupported table engine &rdquo;' . $this->alter_engine . '&ldquo;', 'results' );

			}

		// modify db table collation & charset
		} elseif ( $this->alter_collate ) {

			if ( is_string( $this->alter_collate ) ) {

				$this->report = $this->update_collation( $this->alter_collate, $this->tables );

				return $this->report;

			} else {

				$this->add_error( 'Collation must be a valid string', 'results' );

			}

		// search & replace
		} else {

			// if we have search string
			if ( ! empty( $this->search ) ) {

				// go ahead with dry/live run
				$this->report = $this->replacer( $this->search, $this->replace, $this->tables );

				return $this->report;

			} else {

				$this->add_error( 'Search string is empty', 'search' );

			}

		}

	}


	public function __destruct() {
		$this->db_close();
	}


	public function get( $property ) {
		return $this->$property;
	}

	public function set( $property, $value ) {
		$this->$property = $value;
	}


	public function exceptions( $exception ) {
		echo $exception->getMessage() . "\n";
	}

	public function errors( $no, $message, $file, $line ) {
		echo $message . "\n";
	}


	public function add_error( $error, $type = null ) {
		if ( $type !== null )
			$this->error_type = $type;
		$this->errors[ $this->error_type ][] = $error;
	}


	public function use_pdo() {
		return $this->get( 'use_pdo' );
	}


	/**
	 * Creates the database connection and gathers table names
	 *
	 * @return resource DB connection
	 */
	public function connection() {

		if ( ! class_exists( 'PDO' ) )
			$this->set( 'use_pdo', false );

		if ( $this->use_pdo() ) {
			try {
				$connection = new PDO( "mysql:host={$this->host};dbname={$this->name}", $this->user, $this->pass );
			} catch( PDOException $e ) {
				return $this->add_error( $e->getMessage(), 'db' );
			}
		}
		else {
			$connection = @mysql_connect( $this->host, $this->user, $this->pass );
		}

		// set database resource
		$this->set( 'db', $connection );

		// unset if not available
		if ( ! $this->use_pdo() && ! $connection ) {
			$this->set( 'db', false );
			return $this->add_error( 'Unable to connect to database. Please check your host', 'db' );
		}

		// select the database for non PDO
		if ( ! $this->use_pdo() && ! mysql_select_db( $this->name, $connection ) )
			return $this->add_error( 'Could not select the database. Please check the database name, user and password', 'db' );

		// set the character set
		$this->db_set_charset( $this->get( 'charset' ) );

		// get tables
		$all_tables_mysql = $this->db_query( 'SHOW TABLE STATUS' );

		// check if there's a problem with our database at this stage
		if ( $this->use_pdo() && ! $all_tables_mysql ) {
			$this->add_error( $this->db_error(), 'db' );
			$this->set( 'db', false );
			return;;
		}

		if ( ! $all_tables_mysql ) {
			$this->add_error( $this->db_error( ), 'db' );
		} else {
			while ( $table = $this->db_fetch( $all_tables_mysql ) ) {
				$this->all_tables[ $table[0] ] = $table;
			}
		}

		// get available engines
		$mysql_engines = $this->db_query( 'SHOW ENGINES;' );

		if ( ! $mysql_engines ) {
			$this->add_error( $this->db_error( ), 'db' );
		} else {
			while ( $engine = $this->db_fetch( $mysql_engines ) ) {
				if ( in_array( $engine[ 'Support' ], array( 'YES', 'DEFAULT' ) ) )
					$this->engines[] = $engine[ 'Engine' ];
			}
		}

		return $this->get( 'db' );
	}


	public function db_query( $query ) {
		if ( $this->use_pdo() )
			return $this->db->query( $query );
		else
			return mysql_query( $query, $this->db );
	}

	public function db_error() {
		if ( $this->use_pdo() )
			return array_pop( $this->db->errorInfo() );
		else
			return mysql_error();
	}

	public function db_fetch( $data ) {
		if ( $this->use_pdo() )
			return $data->fetch();
		else
			return mysql_fetch_array( $data );
	}

	public function db_escape( $string ) {
		if ( $this->use_pdo() )
			return $this->db->quote( $string );
		else
			return mysql_real_escape_string( $string );
	}

	public function db_free_result( $data ) {
		if ( $this->use_pdo() )
			return $data->closeCursor();
		else
			return mysql_free_result( $data );
	}

	public function db_set_charset( $charset = '' ) {
		if ( ! empty( $charset ) ) {
			if ( ! $this->use_pdo() && function_exists( 'mysql_set_charset' ) )
				mysql_set_charset( $charset, $this->db );
			else
				$this->db_query( 'SET NAMES ' . $charset );
		}
	}

	public function db_close() {
		if ( $this->use_pdo() )
			unset( $this->db );
		else
			mysql_close( $this->db );
	}

	public function db_valid() {
		return (bool)$this->db;
	}


	/**
	 * Used to check the $_post tables array and remove any that don't exist.
	 *
	 * @param array $table The list of tables from the $_post var to be checked.
	 *
	 * @return array	Same array as passed in but with any tables that don'e exist removed.
	 */
	public function check_table_array( $table = '' ) {
		return in_array( $table, $this->all_tables );
	}


	/**
	 * Walk an array replacing one element for another. ( NOT USED ANY MORE )
	 *
	 * @param string $find    The string we want to replace.
	 * @param string $replace What we'll be replacing it with.
	 * @param array $data    Used to pass any subordinate arrays back to the
	 * function for searching.
	 *
	 * @return array    The original array with the replacements made.
	 */
	public function recursive_array_replace( $find, $replace, $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				if ( is_array( $value ) ) {
					$this->recursive_array_replace( $find, $replace, $data[ $key ] );
				} else {
					// have to check if it's string to ensure no switching to string for booleans/numbers/nulls - don't need any nasty conversions
					if ( is_string( $value ) )
						$data[ $key ] = $this->str_replace( $find, $replace, $value );
				}
			}
		} else {
			if ( is_string( $data ) )
				$data = $this->str_replace( $find, $replace, $data );
		}
	}


	/**
	 * Take a serialised array and unserialise it replacing elements as needed and
	 * unserialising any subordinate arrays and performing the replace on those too.
	 *
	 * @param string $from       String we're looking to replace.
	 * @param string $to         What we want it to be replaced with
	 * @param array  $data       Used to pass any subordinate arrays back to in.
	 * @param bool   $serialised Does the array passed via $data need serialising.
	 *
	 * @return array	The original array with all elements replaced as needed.
	 */
	public function recursive_unserialize_replace( $from = '', $to = '', $data = '', $serialised = false ) {

		// some unserialised data cannot be re-serialised eg. SimpleXMLElements
		try {

			if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {
				$data = $this->recursive_unserialize_replace( $from, $to, $unserialized, true );
			}

			elseif ( is_array( $data ) ) {
				$_tmp = array( );
				foreach ( $data as $key => $value ) {
					$_tmp[ $key ] = $this->recursive_unserialize_replace( $from, $to, $value, false );
				}

				$data = $_tmp;
				unset( $_tmp );
			}

			// Submitted by Tina Matter
			elseif ( is_object( $data ) ) {
				// $data_class = get_class( $data );
				$_tmp = $data; // new $data_class( );
				$props = get_object_vars( $data );
				foreach ( $props as $key => $value ) {
					$_tmp->$key = $this->recursive_unserialize_replace( $from, $to, $value, false );
				}

				$data = $_tmp;
				unset( $_tmp );
			}

			else {
				if ( is_string( $data ) )
					$data = $this->str_replace( $from, $to, $data );
			}

			if ( $serialised )
				return serialize( $data );

		} catch( Exception $error ) {

			$this->add_error( $error->getMessage(), 'results' );

		}

		return $data;
	}


	/**
	 * The main loop triggered in step 5. Up here to keep it out of the way of the
	 * HTML. This walks every table in the db that was selected in step 3 and then
	 * walks every row and column replacing all occurences of a string with another.
	 * We split large tables into 50,000 row blocks when dealing with them to save
	 * on memmory consumption.
	 *
	 * @param mysql  $connection The db connection object
	 * @param string $search     What we want to replace
	 * @param string $replace    What we want to replace it with.
	 * @param array  $tables     The tables we want to look at.
	 *
	 * @return array    Collection of information gathered during the run.
	 */
	public function replacer( $search = '', $replace = '', $tables = array( ) ) {

		$report = array( 'tables' => 0,
						 'rows' => 0,
						 'change' => 0,
						 'updates' => 0,
						 'start' => microtime( ),
						 'end' => microtime( ),
						 'errors' => array( ),
						 'table_reports' => array( )
						 );

		$table_report = array(
						 'rows' => 0,
						 'change' => 0,
						 'changes' => array( ),
						 'updates' => 0,
						 'start' => microtime( ),
						 'end' => microtime( ),
						 'errors' => array( ),
						 );

		if ( $this->dry_run ) { 	// Report this as a search-only run.
			$this->add_error( 'The dry-run option was selected. No replacements were actually made.', 'results' );
		}

		// if no tables selected assume all
		if ( empty( $tables ) )
			$tables = array_keys( $this->all_tables );

		if ( is_array( $tables ) && ! empty( $tables ) ) {
			foreach( $tables as $table ) {

				$report[ 'tables' ]++;

				$report[ 'table_reports' ][ $table ] = $table_report;
				$report[ 'table_reports' ][ $table ][ 'start' ] = microtime();

				$columns = array( );

				// Get a list of columns in this table
				$fields = $this->db_query( 'DESCRIBE ' . $table );
				if ( ! $fields ) {
					$this->add_error( $this->db_error( ), 'db' );
					continue;
				}
				while( $column = $this->db_fetch( $fields ) ) {
					$columns[ $column[ 'Field' ] ] = $column[ 'Key' ] == 'PRI' ? true : false;
				}

				// Count the number of rows we have in the table if large we'll split into blocks, This is a mod from Simon Wheatley
				$row_count = $this->db_query( 'SELECT COUNT(*) FROM ' . $table );
				$rows_result = $this->db_fetch( $row_count );
				$row_count = $rows_result[ 0 ];
				if ( $row_count == 0 )
					continue;

				$page_size = $this->get( 'page_size' );
				$pages = ceil( $row_count / $page_size );

				for( $page = 0; $page < $pages; $page++ ) {

					$current_row = 0;
					$start = $page * $page_size;
					//$end = $start + $page_size;
					// Grab the content of the table
					$data = $this->db_query( sprintf( 'SELECT * FROM %s LIMIT %d, %d', $table, $start, $page_size ) );

					if ( ! $data )
						$this->add_error( $this->db_error( ), 'db' );

					while ( $row = $this->db_fetch( $data ) ) {

						$report[ 'rows' ]++; // Increment the row counter
						$report[ 'table_reports' ][ $table ][ 'rows' ]++;
						$current_row++;

						$update_sql = array( );
						$where_sql = array( );
						$upd = false;

						foreach( $columns as $column => $primary_key ) {

							$edited_data = $data_to_fix = $row[ $column ];

							if ( $primary_key )
								$where_sql[] = $column . ' = "' . $this->db_escape( $data_to_fix ) . '"';

							// exclude cols
							if ( in_array( $column, $this->exclude_cols ) )
								continue;

							// include cols
							if ( ! empty( $this->include_cols ) && ! in_array( $column, $this->include_cols ) )
								continue;

							// Run a search replace on the data that'll respect the serialisation.
							$edited_data = $this->recursive_unserialize_replace( $search, $replace, $data_to_fix );

							// Something was changed
							if ( $edited_data != $data_to_fix ) {
								$report[ 'change' ]++;
								$report[ 'table_reports' ][ $table ][ 'change' ]++;
								// log first 20 changes
								if ( $report[ 'change' ] <= $this->report_change_num )
									$report[ 'table_reports' ][ $table ][ 'changes' ][] = array( 'row' => $report[ 'rows' ], 'column' => $column, 'from' => $data_to_fix, 'to' => $edited_data );
								$update_sql[] = $column . ' = "' . $this->db_escape( $edited_data ) . '"';
								$upd = true;
							}

						}

						if ( $this->dry_run ) {
							// nothing for this state
						}
						elseif ( $upd && ! empty( $where_sql ) ) {
							$sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $update_sql ) . ' WHERE ' . implode( ' AND ', array_filter( $where_sql ) );
							$result = $this->db_query( $sql );
							if ( ! $result ) {
								$this->add_error( $this->db_error( ), 'results' );
							}
							else {
								$report[ 'updates' ]++;
								$report[ 'table_reports' ][ $table ][ 'updates' ]++;
							}

						} elseif ( $upd ) {
							$this->add_error( sprintf( '"%s" has no primary key, manual change needed on row %s.', $table, $current_row ), 'results' );
						}

					}

					$this->db_free_result( $data );

				}

				$report[ 'table_reports' ][ $table ][ 'end' ] = microtime();
			}

		}

		$report[ 'end' ] = microtime( );

		// store in the class
		$this->set( 'report', $report );

		return $report;
	}


	public function update_engine( $engine = 'MyISAM', $tables = array() ) {

		$report = array( 'engine' => $engine, 'converted' => array() );

		if ( empty( $tables ) )
			$tables = array_keys( $this->all_tables );

		foreach( $tables as $table ) {
			$table_info = $this->all_tables[ $table ];

			// are we updating the engine?
			if ( $table_info[ 'Engine' ] != $engine ) {
				$engine_converted = $this->db_query( "alter table {$table} engine = {$engine};" );
				if ( ! $engine_converted )
					$this->add_error( $this->db_error( ), 'results' );
				else
					$report[ 'converted' ][] = $table;
				continue;
			}
		}

		return $report;
	}


	public function update_collation( $collate = 'utf8_unicode_ci', $tables = array() ) {

		$report = array( 'collation' => $collate, 'converted' => array() );

		if ( empty( $tables ) )
			$tables = array_keys( $this->all_tables );

		// charset is same as collation up to first underscore
		$charset = preg_replace( '/^([^_]+).*$/', '$1', $collate );

		foreach( $tables as $table ) {
			$table_info = $this->all_tables[ $table ];

			// are we updating the engine?
			if ( $table_info[ 'Collate' ] != $collate ) {
				$engine_converted = $this->db_query( "alter table {$table} convert to character set {$charset} collate {$collate};" );
				if ( ! $engine_converted )
					$this->add_error( $this->db_error( ), 'results' );
				else
					$report[ 'converted' ][] = $table;
				continue;
			}
		}

		return $report;
	}


	/**
	 * Take an array and turn it into an English formatted list. Like so:
	 * array( 'a', 'b', 'c', 'd' ); = a, b, c, or d.
	 *
	 * @param array $input_arr The source array
	 *
	 * @return string    English formatted string
	 */
	public function eng_list( $input_arr = array( ), $sep = ', ', $before = '"', $after = '"' ) {
		if ( ! is_array( $input_arr ) )
			return false;

		$_tmp = $input_arr;

		if ( count( $_tmp ) >= 2 ) {
			$end2 = array_pop( $_tmp );
			$end1 = array_pop( $_tmp );
			array_push( $_tmp, $end1 . $after . ' or ' . $before . $end2 );
		}

		return $before . implode( $before . $sep . $after, $_tmp ) . $after;
	}

	/**
	 * Replace all occurrences of the search string with the replacement string.
	 *
	 * @author Sean Murphy <sean@iamseanmurphy.com>
	 * @copyright Copyright 2012 Sean Murphy. All rights reserved.
	 * @license http://creativecommons.org/publicdomain/zero/1.0/
	 * @link http://php.net/manual/function.str-replace.php
	 *
	 * @param mixed $search
	 * @param mixed $replace
	 * @param mixed $subject
	 * @param int $count
	 * @return mixed
	 */
	public static function mb_str_replace( $search, $replace, $subject, &$count = 0 ) {
		if ( ! is_array( $subject ) ) {
			// Normalize $search and $replace so they are both arrays of the same length
			$searches = is_array( $search ) ? array_values( $search ) : array( $search );
			$replacements = is_array( $replace ) ? array_values( $replace ) : array( $replace );
			$replacements = array_pad( $replacements, count( $searches ), '' );

			foreach ( $searches as $key => $search ) {
				$parts = mb_split( preg_quote( $search ), $subject );
				$count += count( $parts ) - 1;
				$subject = implode( $replacements[ $key ], $parts );
			}
		} else {
			// Call mb_str_replace for each subject in array, recursively
			foreach ( $subject as $key => $value ) {
				$subject[ $key ] = self::mb_str_replace( $search, $replace, $value, $count );
			}
		}

		return $subject;
	}


	/**
	 * Wrapper for regex/non regex search & replace
	 *
	 * @param string $search
	 * @param string $replace
	 * @param string $string
	 * @param int $count
	 *
	 * @return string
	 */
	public function str_replace( $search, $replace, $string, &$count = 0 ) {
		if ( $this->get( 'regex' ) ) {
			return preg_replace( $search, $replace, $string, -1, $count );
		} elseif( function_exists( 'mb_split' ) ) {
			return self::mb_str_replace( $search, $replace, $string, $count );
		} else {
			return str_replace( $search, $replace, $string, $count );
		}
	}

}

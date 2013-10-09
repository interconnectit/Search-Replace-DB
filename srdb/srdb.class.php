<?php

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
				$this->report = $this->replacer( $this->db, $this->search, $this->replace, $this->tables );

				return $this->report;

			} else {

				$this->add_error( 'Search string is empty', 'search' );

			}

		}

	}


	public function __destruct() {
		if ( is_resource( $this->db ) )
			mysql_close( $this->db );
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


	/**
	 * Creates the database connection and gathers table names
	 *
	 * @return resource DB connection
	 */
	public function connection() {

		$connection = @mysql_connect( $this->host, $this->user, $this->pass );

		if ( ! $connection )
			return $this->add_error( 'Unable to connect to database. Please check your host', 'db' );

		if ( ! empty( $this->charset ) ) {
			if ( function_exists( 'mysql_set_charset' ) )
				mysql_set_charset( $this->charset, $connection );
			else
				mysql_query( 'SET NAMES ' . $this->charset, $connection );  // Shouldn't really use this, but there for backwards compatibility
		}

		// Do we have any tables and if so build the all tables array
		if ( ! mysql_select_db( $this->name, $connection ) )
			return $this->add_error( 'Could not select the database. Please check the database name, user and password', 'db' );

		// get tables
		$all_tables_mysql = @mysql_query( 'SHOW TABLE STATUS', $connection );

		if ( ! $all_tables_mysql ) {
			$this->add_error( mysql_error( ), 'db' );
		} else {
			while ( $table = mysql_fetch_array( $all_tables_mysql ) ) {
				$this->all_tables[ $table[0] ] = $table;
			}
		}

		// get available engines
		$mysql_engines = @mysql_query( 'SHOW ENGINES;', $connection );

		if ( ! $mysql_engines ) {
			$this->add_error( mysql_error( ), 'db' );
		} else {
			while ( $engine = mysql_fetch_array( $mysql_engines ) ) {
				if ( in_array( $engine[ 'Support' ], array( 'YES', 'DEFAULT' ) ) )
					$this->engines[] = $engine[ 'Engine' ];
			}
		}

		$this->set( 'db', $connection );

		return $this->get( 'db' );
	}

	public function disconnect( $connection = null ) {
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
	public function replacer( $connection, $search = '', $replace = '', $tables = array( ) ) {

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
				$fields = mysql_query( 'DESCRIBE ' . $table, $connection );
				if ( ! $fields ) {
					$this->add_error( mysql_error( ), 'db' );
					continue;
				}
				while( $column = mysql_fetch_array( $fields ) ) {
					$columns[ $column[ 'Field' ] ] = $column[ 'Key' ] == 'PRI' ? true : false;
				}

				// Count the number of rows we have in the table if large we'll split into blocks, This is a mod from Simon Wheatley
				$row_count = mysql_query( 'SELECT COUNT(*) FROM ' . $table, $connection );
				$rows_result = mysql_fetch_array( $row_count );
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
					$data = mysql_query( sprintf( 'SELECT * FROM %s LIMIT %d, %d', $table, $start, $page_size ), $connection );

					if ( ! $data )
						$this->add_error( mysql_error( ), 'db' );

					while ( $row = mysql_fetch_array( $data ) ) {

						$report[ 'rows' ]++; // Increment the row counter
						$report[ 'table_reports' ][ $table ][ 'rows' ]++;
						$current_row++;

						$update_sql = array( );
						$where_sql = array( );
						$upd = false;

						foreach( $columns as $column => $primary_key ) {

							$edited_data = $data_to_fix = $row[ $column ];

							if ( $primary_key )
								$where_sql[] = $column . ' = "' . mysql_real_escape_string( $data_to_fix ) . '"';

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
								$update_sql[] = $column . ' = "' . mysql_real_escape_string( $edited_data ) . '"';
								$upd = true;
							}

						}

						if ( $this->dry_run ) {
							// nothing for this state
						}
						elseif ( $upd && ! empty( $where_sql ) ) {
							$sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $update_sql ) . ' WHERE ' . implode( ' AND ', array_filter( $where_sql ) );
							$result = mysql_query( $sql, $connection );
							if ( ! $result ) {
								$this->add_error( mysql_error( ), 'results' );
							}
							else {
								$report[ 'updates' ]++;
								$report[ 'table_reports' ][ $table ][ 'updates' ]++;
							}

						} elseif ( $upd ) {
							$this->add_error( sprintf( '"%s" has no primary key, manual change needed on row %s.', $table, $current_row ), 'results' );
						}

					}

					mysql_free_result( $data );

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
				$engine_converted = mysql_query( "alter table {$table} engine = {$engine};", $this->db );
				if ( ! $engine_converted )
					$this->add_error( mysql_error( ), 'results' );
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
				$engine_converted = mysql_query( "alter table {$table} convert to character set {$charset} collate {$collate}", $this->db );
				if ( ! $engine_converted )
					$this->add_error( mysql_error( ), 'results' );
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

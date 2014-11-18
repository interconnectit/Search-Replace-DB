<?php

/**
 *
 * Safe Search and Replace on Database with Serialized Data v3.0.0
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
     * @staticvar string MySQL PDO driver name.
     */
    const MYSQL_PDO_DRIVER      = 'mysql';

    /**
     * @staticvar string PostgreSQL PDO driver name.
     */
    const POSTGRESQL_PDO_DRIVER = 'pgsql';

    /**
     * @staticvar string Default PDO driver to use.
     */
    const DEFAULT_PDO_DRIVER    = self::MYSQL_PDO_DRIVER;
     
    /**
     * @var array Supported PDO drivers. Keys are PDO driver names and values
     * are arrays of the following form:
     * array('ok'=> true|false, 'description'=>'This driver is supported ...')
     */
    protected $SUPPORTED_DRIVERS = array(
        self::MYSQL_PDO_DRIVER => array(
            'ok' => true,
            'description' => 'Complete MySQL support',
        ),
        self::POSTGRESQL_PDO_DRIVER => array(
            'ok' => true,
            'description' => 'Partial PostgreSQL support',
        ),
    );

    /**
     * @var array SQL Queries for supported PDO drivers. First-level keys are
     * query type and second level keys are PDO driver names.
     */
    protected $QUERIES = array(
        'get_tables' => array(
            self::MYSQL_PDO_DRIVER => "SELECT
                  t.TABLE_NAME as Name,
                  t.ENGINE as Engine,
                  t.version as Version,
                  t.ROW_FORMAT AS Row_format,
                  t.TABLE_ROWS AS Rows,
                  t.AVG_ROW_LENGTH AS Avg_row_length,
                  t.DATA_LENGTH AS Data_length,
                  t.MAX_DATA_LENGTH AS Max_data_length,
                  t.INDEX_LENGTH AS Index_length,
                  t.DATA_FREE AS Data_free,
                  t.AUTO_INCREMENT as Auto_increment,
                  t.CREATE_TIME AS Create_time,
                  t.UPDATE_TIME AS Update_time,
                  t.CHECK_TIME AS Check_time,
                  t.TABLE_COLLATION as Collation,
                  c.CHARACTER_SET_NAME as Character_set,
                  t.Checksum,
                  t.Create_options,
                  t.table_Comment as Comment
                FROM information_schema.TABLES t
                    LEFT JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY c
                        ON ( t.TABLE_COLLATION = c.COLLATION_NAME )
                  WHERE t.TABLE_SCHEMA = :table_schema;
                ",
            self::POSTGRESQL_PDO_DRIVER => "SELECT
                    t.table_name AS \"Name\",
                    '' AS \"Engine\",
                    t.table_type AS \"Comment\",
                    c.datcollate AS \"Collation\"
                FROM information_schema.tables t
                    JOIN pg_catalog.pg_database c ON (c.datname = t.table_catalog)
                WHERE
                    t.table_catalog = :table_schema
                    AND t.table_schema = 'public'
                ;",
        ),
        'get_table_character_set' => array(
            self::MYSQL_PDO_DRIVER => "SELECT
                    c.character_set_name
                FROM information_schema.TABLES t
                    LEFT JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY c
                    ON (t.TABLE_COLLATION = c.COLLATION_NAME)
                WHERE t.table_schema = :schema
                    AND t.table_name = :table_name
                LIMIT 1;",
            self::POSTGRESQL_PDO_DRIVER => "SELECT
                    c.datcollate AS \"character_set_name\"
                FROM pg_catalog.pg_database c
                WHERE c.datname = :schema
                    AND :table_name IS NOT NULL
                ;",
        ),
        'get_engines' => array(
            self::MYSQL_PDO_DRIVER => "SHOW ENGINES;",
            self::POSTGRESQL_PDO_DRIVER => "SELECT '' AS \"Engine\", 'NO' AS \"Support\";",
        ),
        'get_columns' => array(
            self::MYSQL_PDO_DRIVER => "DESCRIBE :table;",
            // see https://wiki.postgresql.org/wiki/Retrieve_primary_key_columns
            self::POSTGRESQL_PDO_DRIVER => "SELECT
              pg_attribute.attname as \"Field\",
              format_type(pg_attribute.atttypid, pg_attribute.atttypmod) AS \"Type\",
              CASE (pg_attribute.attnotnull)
                  WHEN TRUE THEN 'NO'
                  WHEN FALSE THEN 'YES'
              END AS \"Null\",
              CASE(pg_attribute.attnum = any(pg_index.indkey))
                  WHEN TRUE THEN 'PRI'
                  WHEN FALSE THEN ''
              END AS \"Key\",
              pg_attrdef.adsrc AS \"Default\"
            FROM
              pg_class
                JOIN pg_index ON (pg_class.oid = pg_index.indrelid)
                JOIN pg_namespace ON (pg_class.relnamespace = pg_namespace.oid)
                JOIN pg_attribute ON (pg_class.oid = pg_attribute.attrelid)
                LEFT JOIN pg_attrdef ON (pg_attribute.attrelid = pg_attrdef.adrelid AND pg_attribute.attnum = pg_attrdef.adnum)
            WHERE
              pg_class.oid = :table::regclass
              AND pg_namespace.nspname = 'public'
              AND pg_attribute.attnum > 0
              AND pg_index.indisprimary
            ORDER BY pg_attribute.attnum ASC
            ;",
/*
            self::POSTGRESQL_PDO_DRIVER => "SELECT
                    c.column_name AS \"Field\",
                    c.udt_name AS \"Type\",
                    c.is_nullable AS \"Null\",
                    CASE (c.column_default LIKE 'nextval%' AND c.is_nullable = 'NO')
                        WHEN TRUE THEN 'PRI'
                        WHEN FALSE THEN ''
                    END AS \"Key\",
                    c.column_default AS \"Default\"
                FROM information_schema.columns c
                WHERE c.table_catalog = CURRENT_CATALOG
                    AND c.table_schema = 'public'
                    AND c.table_name = :table
            ;",
*/
        ),
        'table_content' => array(
            self::MYSQL_PDO_DRIVER => "SELECT * FROM {table} LIMIT :start, :page_size;",
            self::POSTGRESQL_PDO_DRIVER => "SELECT * FROM {table} LIMIT :page_size OFFSET :start;",
        ),
        /*
        'query_name' => array(
            self::MYSQL_PDO_DRIVER => "MYSQL QUERY",
            self::POSTGRESQL_PDO_DRIVER => "POSTGRESQL QUERY",
        ),
        // use: $sql_query = $this->get_sql_query('query_name');
        */
    );

	/**
	 * @var array List of all the tables in the database
	 */
	public $all_tables = array();

	/**
	 * @var array Tables to run the replacement on
	 */
	public $tables = array();

	/**
	 * @var string Search term
	 */
	public $search = false;

	/**
	 * @var string Replacement
	 */
	public $replace = false;

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
	 * @var bool Whether to echo report as script runs
	 */
	public $verbose = false;


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
			'name' 				=> '',
			'user' 				=> '',
			'pass' 				=> '',
			'host' 				=> '',
            'pdo_driver'        => self::DEFAULT_PDO_DRIVER,
			'search' 			=> '',
			'replace' 			=> '',
			'tables'			=> array(),
			'exclude_cols' 		=> array(),
			'include_cols' 		=> array(),
			'dry_run' 			=> true,
			'regex' 			=> false,
			'pagesize' 			=> 50000,
			'alter_engine' 		=> false,
			'alter_collation' 	=> false,
			'verbose'			=> false
		), $args );

		// handle exceptions
		set_exception_handler( array( $this, 'exceptions' ) );

		// handle errors
		set_error_handler( array( $this, 'errors' ), E_ERROR | E_WARNING );

		// allow a string for columns
		foreach( array( 'exclude_cols', 'include_cols', 'tables' ) as $maybe_string_arg ) {
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

		// only for non cli call, cli set no timeout, no memory limit
		if( ! defined( 'STDIN' ) ) {

			// increase time out limit
			@set_time_limit( 60 * 10 );

			// try to push the allowed memory up, while we're at it
			@ini_set( 'memory_limit', '1024M' );

		}

		// set up db connection
		$this->db_setup();

		if ( $this->db_valid() ) {

			// update engines
			if ( $this->alter_engine ) {
				$report = $this->update_engine( $this->alter_engine, $this->tables );
			}

			// update collation
			elseif ( $this->alter_collation ) {
				$report = $this->update_collation( $this->alter_collation, $this->tables );
			}

			// default search/replace action
			else {
				$report = $this->replacer( $this->search, $this->replace, $this->tables );
			}

		} else {

			$report = $this->report;

		}

		// store report
		$this->set( 'report', $report );
		return $report;
	}


	/**
	 * Terminates db connection
	 *
	 * @return void
	 */
	public function __destruct() {
		if ( $this->db_valid() )
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


	public function log( $type = '' ) {
		$args = array_slice( func_get_args(), 1 );
		if ( $this->get( 'verbose' ) ) {
			echo "{$type}: ";
			print_r( $args );
			echo "\n";
		}
		return $args;
	}


	public function add_error( $error, $type = null ) {
		if ( $type !== null )
			$this->error_type = $type;
		$this->errors[ $this->error_type ][] = $error;
		$this->log( 'error', $this->error_type, $error );
	}


	public function use_pdo() {
		return $this->get( 'use_pdo' );
	}


	/**
	 * Setup connection, populate tables array
	 *
	 * @return void
	 */
	public function db_setup() {

		$connection_type = class_exists( 'PDO' ) ? 'pdo' : 'mysql';
        
		// connect
		$this->set( 'db', $this->connect( $connection_type ) );

	}


	/**
	 * Database connection type router
	 *
	 * @param string $type
	 *
	 * @return callback
	 */
	public function connect( $type = '' ) {
		$method = "connect_{$type}";
		return $this->$method();
	}


	/**
	 * Creates the database connection using old mysql functions
	 *
	 * @return resource|bool
	 */
	public function connect_mysql() {

        // check if another PDO driver than 'mysql' was specified
        if ($this->pdo_driver && ($this->pdo_driver != 'mysql')) {
			$connection = false;
			$this->add_error( 'PDO not available!', 'db' );
        }
        else {
            // switch off PDO
            $this->set( 'use_pdo', false );

            $connection = @mysql_connect( $this->host, $this->user, $this->pass );

            // unset if not available
            if ( ! $connection ) {
                $connection = false;
                $this->add_error( mysql_error(), 'db' );
            }

            // select the database for non PDO
            if ( $connection && ! mysql_select_db( $this->name, $connection ) ) {
                $connection = false;
                $this->add_error( mysql_error(), 'db' );
            }
        }

		return $connection;
	}


	/**
	 * Sets up database connection using PDO
	 *
	 * @return PDO|bool
	 */
	public function connect_pdo() {

        # make sure a supported PDO driver as been specified
        if ( ! $this->pdo_driver ) {
            $this->add_error( 'No PDO driver specified! Please select one using the pdo-driver argument.', 'db' );
            $connection = false;
        }
        elseif ( ! array_key_exists($this->pdo_driver, $this->SUPPORTED_DRIVERS) ) {
            $this->add_error( "Unsupported PDO driver '" . $this->pdo_driver . "'! Supported drivers are :'" . implode("', '", array_keys($this->SUPPORTED_DRIVERS)) . "'", 'db' );
            $connection = false;
        }
        elseif ( ! array_key_exists('ok', $this->SUPPORTED_DRIVERS[$this->pdo_driver] )
            || ! $this->SUPPORTED_DRIVERS[$this->pdo_driver]['ok'] ) {
            $this->add_error( 'The PDO driver ' . $this->pdo_driver . ' is not supported.' . (array_key_exists('description', $this->SUPPORTED_DRIVERS[$this->pdo_driver]) ? ' Description: ' . $this->SUPPORTED_DRIVERS[$this->pdo_driver]['description'] : ''), 'db' );
            $connection = false;
        }
        else {

            # got a valid driver
            try {
                $connection = new PDO( $this->pdo_driver . ":host={$this->host};dbname={$this->name}", $this->user, $this->pass );
            } catch( PDOException $e ) {
                $this->add_error( $e->getMessage(), 'db' );
                $connection = false;
            }

            // check if there's a problem with our database at this stage
            if ( $connection && ! $connection->query( 'SELECT 1' ) ) {
                $error_info = $connection->errorInfo();
                if ( !empty( $error_info ) && is_array( $error_info ) )
                    $this->add_error( array_pop( $error_info ), 'db' ); // Array pop will only accept a $var..
                $connection = false;
            }
        }

		return $connection;
	}

	/**
	 * Retrieve the corresponding SQL query according to selected PDO driver
	 *
	 * @return string
	 */
	public function get_sql_query($query_name) {
        if ( ! array_key_exists($query_name, $this->QUERIES) ) {
            $this->add_error( "SQL query '$query_name' nor available!", 'db' );
            return '';
        }
        elseif ( ! array_key_exists($this->pdo_driver, $this->QUERIES[$query_name]) ) {
            $this->add_error( "SQL query '$query_name' nor available for PDO driver '" . $this->pdo_driver . "'!", 'db' );
            return '';
        }
        return $this->QUERIES[$query_name][$this->pdo_driver];
    }


	/**
	 * Retrieve all tables from the database
	 *
	 * @return array
	 */
	public function get_tables() {
		// get tables

		// A clone of show table status but with character set for the table.
		$show_table_status = $this->get_sql_query('get_tables');

		$all_tables_mysql = $this->db_query( $show_table_status , array(':table_schema' => $this->name));
		$all_tables = array();

		if ( ! $all_tables_mysql ) {

			$this->add_error( $this->db_error( ), 'db' );

		} else {

			// set the character set
			//$this->db_set_charset( $this->get( 'charset' ) );

			while ( $table = $this->db_fetch( $all_tables_mysql ) ) {
				// ignore views
				if ( $table[ 'Comment' ] == 'VIEW' )
					continue;

				$all_tables[ $table[0] ] = $table;
			}

		}

		return $all_tables;
	}


	/**
	 * Get the character set for the current table
	 *
	 * @param string $table_name The name of the table we want to get the char
	 * set for
	 *
	 * @return string    The character encoding;
	 */
	public function get_table_character_set( $table_name = '' ) {
		$table_name = $this->db_escape( $table_name );
		$schema = $this->db_escape( $this->name );

        $table_character_set_query = $this->get_sql_query('get_table_character_set');
		$charset = $this->db_query($table_character_set_query, array(':schema' => $schema, ':table_name' => $table_name, ));

		$encoding = false;
		if ( ! $charset ) {
			$this->add_error( $this->db_error( ), 'db' );
		}
		else {
			$result = $this->db_fetch( $charset );
			$encoding = isset( $result[ 'character_set_name' ] ) ? $result[ 'character_set_name' ] : false;
		}

		return $encoding;
	}


	/**
	 * Retrieve all supported database engines
	 *
	 * @return array
	 */
	public function get_engines() {

		// get available engines
        $engine_query = $this->get_sql_query('get_engines');
		$mysql_engines = $this->db_query( $engine_query );
		$engines = array();

		if ( ! $mysql_engines ) {
			$this->add_error( $this->db_error( ), 'db' );
		} else {
			while ( $engine = $this->db_fetch( $mysql_engines ) ) {
				if ( in_array( $engine[ 'Support' ], array( 'YES', 'DEFAULT' ) ) )
					$engines[] = $engine[ 'Engine' ];
			}
		}

		return $engines;
	}


	public function db_query( $query , $bindings = null) {
		if ( $this->use_pdo() )
            if ($bindings) {
                $sth = $this->db->prepare( $query );
                foreach ( $bindings as $key => &$value ) {
                    $sth->bindParam( $key, $value );
                }
                $sth->execute();
                return $sth;
            }
            else {
                return $this->db->query( $query );
            }
		else
			return mysql_query( $query, $this->db );
	}

	public function db_update( $query ) {
		if ( $this->use_pdo() )
			return $this->db->exec( $query );
		else
			return mysql_query( $query, $this->db );
	}

	public function db_error() {
		if ( $this->use_pdo() ) {
			$error_info = $this->db->errorInfo();
			return !empty( $error_info ) && is_array( $error_info ) ? array_pop( $error_info ) : 'Unknown error';
		}
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
			return "'" . mysql_real_escape_string( $string ) . "'";
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
				if ( is_string( $data ) ) {
					$data = $this->str_replace( $from, $to, $data );

				}
			}

			if ( $serialised ) {
				$data = serialize( $data );
            }
            
		} catch( Exception $error ) {

			$this->add_error( $error->getMessage(), 'results' );

		}

		return $data;
	}


	/**
	 * Regular expression callback to fix serialised string lengths
	 *
	 * @param array $matches matches from the regular expression
	 *
	 * @return string
	 */
	public function preg_fix_serialised_count( $matches ) {
		$length = mb_strlen( $matches[ 2 ] );
		if ( $length !== intval( $matches[ 1 ] ) )
			return "s:{$length}:\"{$matches[2]}\";";
		return $matches[ 0 ];
	}


	/**
	 * The main loop triggered in step 5. Up here to keep it out of the way of the
	 * HTML. This walks every table in the db that was selected in step 3 and then
	 * walks every row and column replacing all occurrences of a string with another.
	 * We split large tables into 50,000 row blocks when dealing with them to save
	 * on memory consumption.
	 *
	 * @param string $search     What we want to replace
	 * @param string $replace    What we want to replace it with.
	 * @param array  $tables     The tables we want to look at.
	 *
	 * @return array    Collection of information gathered during the run.
	 */
	public function replacer( $search = '', $replace = '', $tables = array( ) ) {

		// check we have a search string, bail if not
		if ( empty( $search ) ) {
			$this->add_error( 'Search string is empty', 'search' );
			return false;
		}

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

		$dry_run = $this->get( 'dry_run' );

		if ( $this->get( 'dry_run' ) ) 	// Report this as a search-only run.
			$this->add_error( 'The dry-run option was selected. No replacements will be made.', 'results' );

		// if no tables selected assume all
		if ( empty( $tables ) ) {
			$all_tables = $this->get_tables();
			$tables = array_keys( $all_tables );
		}

		if ( is_array( $tables ) && ! empty( $tables ) ) {

			foreach( $tables as $table ) {

				$encoding = $this->get_table_character_set( $table );
				switch( $encoding ) {

					// Tables encoded with this work for me only when I set names to utf8. I don't trust this in the wild so I'm going to avoid.
					case 'utf16':
					case 'utf32':
						//$encoding = 'utf8';
						$this->add_error( "The table \"{$table}\" is encoded using \"{$encoding}\" which is currently unsupported.", 'results' );
						continue;
						break;

					default:
						$this->db_set_charset( $encoding );
						break;
				}


				$report[ 'tables' ]++;

				// get primary key and columns
				list( $primary_key, $columns ) = $this->get_columns( $table );

				if ( $primary_key === null ) {
					$this->add_error( "The table \"{$table}\" has no primary key. Changes will have to be made manually.", 'results' );
					continue;
				}

				// create new table report instance
				$new_table_report = $table_report;
				$new_table_report[ 'start' ] = microtime();

				$this->log( 'search_replace_table_start', $table, $search, $replace );

				// Count the number of rows we have in the table if large we'll split into blocks, This is a mod from Simon Wheatley
				$row_count = $this->db_query( "SELECT COUNT(1) FROM {$table};" );
				$rows_result = $this->db_fetch( $row_count );
				$row_count = $rows_result[ 0 ];

				$page_size = $this->get( 'page_size' );
				$pages = ceil( $row_count / $page_size );

				for( $page = 0; $page < $pages; $page++ ) {

					$start = $page * $page_size;

					// Grab the content of the table
                    $table_content_query = str_replace('{table}', $table, $this->get_sql_query('table_content'));
					$data = $this->db_query( $table_content_query, array(':start' => $start, ':page_size' => $page_size, ) );
					if ( ! $data )
						$this->add_error( $this->db_error( ), 'results' );

					while ( $row = $this->db_fetch( $data ) ) {

						$report[ 'rows' ]++; // Increment the row counter
						$new_table_report[ 'rows' ]++;

						$update_sql = array( );
						$where_sql = array( );
						$update = false;

						foreach( $columns as $column ) {

							$edited_data = $data_to_fix = $row[ $column ];

                            // handle streams as strings
                            if ( is_resource($data_to_fix) ) {
                                if ( 'stream' == get_resource_type($data_to_fix) ) {
                                    $edited_data = $data_to_fix = stream_get_contents($data_to_fix);
                                }
                                else {
                                    $this->add_error( 'unsupported resource type: ' . get_resource_type($data_to_fix), 'results' );
                                }
                            }

							if ( $primary_key == $column ) {
								$where_sql[] = "{$column} = " . $this->db_escape( $data_to_fix );
								continue;
							}

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
								$new_table_report[ 'change' ]++;

								// log first x changes
								if ( $new_table_report[ 'change' ] <= $this->get( 'report_change_num' ) ) {
									$new_table_report[ 'changes' ][] = array(
										'row' => $new_table_report[ 'rows' ],
										'column' => $column,
										'from' => utf8_encode( is_string($data_to_fix)? $data_to_fix : '"' . gettype($data_to_fix) . '"'),
										'to' => utf8_encode( $edited_data )
									);
								}

								$update_sql[] = "{$column} = " . $this->db_escape( $edited_data );
								$update = true;

							}

						}

						if ( $dry_run ) {
							// nothing for this state
						} elseif ( $update && ! empty( $where_sql ) ) {

							$sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $update_sql ) . ' WHERE ' . implode( ' AND ', array_filter( $where_sql ) );
							$result = $this->db_update( $sql );

							if ( ! is_int( $result ) && ! $result ) {

								$this->add_error( $this->db_error( ), 'results' );

							} else {

								$report[ 'updates' ]++;
								$new_table_report[ 'updates' ]++;
							}

						}

					}

					$this->db_free_result( $data );

				}

				$new_table_report[ 'end' ] = microtime();

				// store table report in main
				$report[ 'table_reports' ][ $table ] = $new_table_report;

				// log result
				$this->log( 'search_replace_table_end', $table, $new_table_report );
			}

		}

		$report[ 'end' ] = microtime( );

		$this->log( 'search_replace_end', $search, $replace, $report );

		return $report;
	}


	public function get_columns( $table ) {

		$primary_key = null;
		$columns = array( );
		// Get a list of columns in this table
        $columns_query = $this->get_sql_query('get_columns');
		$fields = $this->db_query( $columns_query, array( ':table' => $table, ) );
		if ( ! $fields ) {
			$this->add_error( $this->db_error( ), 'db' );
		} else {
			while( $column = $this->db_fetch( $fields ) ) {
				$columns[] = $column[ 'Field' ];
				if ( $column[ 'Key' ] == 'PRI' ) {
					$primary_key = $column[ 'Field' ];
                }
			}
		}

		return array( $primary_key, $columns );
	}


	public function do_column() {

	}


	/**
	 * Convert table engines
	 *
	 * @param string $engine Engine type
	 * @param array $tables
	 *
	 * @return array    Modification report
	 */
	public function update_engine( $engine = 'MyISAM', $tables = array() ) {

		$report = false;

		if ( empty( $this->engines ) )
			$this->set( 'engines', $this->get_engines() );

		if ( in_array( $engine, $this->get( 'engines' ) ) ) {

			$report = array( 'engine' => $engine, 'converted' => array() );

			if ( empty( $tables ) ) {
				$all_tables = $this->get_tables();
				$tables = array_keys( $all_tables );
			}

			foreach( $tables as $table ) {
				$table_info = $all_tables[ $table ];

				// are we updating the engine?
				if ( $table_info[ 'Engine' ] != $engine ) {
					$engine_converted = $this->db_query( "ALTER TABLE {$table} ENGINE = {$engine};" );
					if ( ! $engine_converted )
						$this->add_error( $this->db_error( ), 'results' );
					else
						$report[ 'converted' ][ $table ] = true;
					continue;
				} else {
					$report[ 'converted' ][ $table ] = false;
				}

				if ( isset( $report[ 'converted' ][ $table ] ) )
					$this->log( 'update_engine', $table, $report, $engine );
			}

		} else {

			$this->add_error( 'Cannot convert tables to unsupported table engine &rdquo;' . $engine . '&ldquo;', 'results' );

		}

		return $report;
	}


	/**
	 * Updates the characterset and collation on the specified tables
	 *
	 * @param string $collate table collation
	 * @param array $tables  tables to modify
	 *
	 * @return array    Modification report
	 */
	public function update_collation( $collation = 'utf8_unicode_ci', $tables = array() ) {

		$report = false;

		if ( is_string( $collation ) ) {

			$report = array( 'collation' => $collation, 'converted' => array() );

			if ( empty( $tables ) ) {
				$all_tables = $this->get_tables();
				$tables = array_keys( $all_tables );
			}

			// charset is same as collation up to first underscore
			$charset = preg_replace( '/^([^_]+).*$/', '$1', $collation );

			foreach( $tables as $table ) {
				$table_info = $all_tables[ $table ];

				// are we updating the engine?
				if ( $table_info[ 'Collation' ] != $collation ) {
					$engine_converted = $this->db_query( "ALTER TABLE {$table} CONVERT TO CHARACTER SET {$charset} COLLATE {$collation};" );
					if ( ! $engine_converted )
						$this->add_error( $this->db_error( ), 'results' );
					else
						$report[ 'converted' ][ $table ] = true;
					continue;
				} else {
					$report[ 'converted' ][ $table ] = false;
				}

				if ( isset( $report[ 'converted' ][ $table ] ) )
					$this->log( 'update_collation', $table, $report, $collation );
			}

		} else {

			$this->add_error( 'Collation must be a valid string', 'results' );

		}

		return $report;
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

	/**
	 * Convert a string containing unicode into HTML entities for front end display
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public function charset_decode_utf_8( $string ) {
		/* Only do the slow convert if there are 8-bit characters */
		/* avoid using 0xA0 (\240) in ereg ranges. RH73 does not like that */
		if ( ! preg_match( "/[\200-\237]/", $string ) and ! preg_match( "/[\241-\377]/", $string ) )
			return $string;

		// decode three byte unicode characters
		$string = preg_replace( "/([\340-\357])([\200-\277])([\200-\277])/e",
			"'&#'.((ord('\\1')-224)*4096 + (ord('\\2')-128)*64 + (ord('\\3')-128)).';'",
			$string );

		// decode two byte unicode characters
		$string = preg_replace( "/([\300-\337])([\200-\277])/e",
			"'&#'.((ord('\\1')-192)*64+(ord('\\2')-128)).';'",
			$string );

		return $string;
	}

}

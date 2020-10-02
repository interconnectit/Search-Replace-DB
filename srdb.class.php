<?php
/**
 *
 * Safe Search and Replace on Database with Serialized Data
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
 *
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

class icit_srdb {

    /**
     * @var array List of all the tables in the database
     */
    public $all_tables = array();

    /**
     * @var array Tables to run the replacement on
     */
    public $tables = array();

    public $exclude_tables = array();

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
    public $port = 0;
    public $charset = 'utf8';
    public $collate = '';
    public $ssl_key = false;
    public $ssl_cert = false;
    public $ssl_ca = false;
    public $ssl_ca_dir = null;
    public $ssl_cipher = null;
    public $ssl_check = true;

    public $debug = false;


    /**
     * @var array Stores a list of exceptions
     */
    public $errors = array(
        'search'         => array(),
        'db'             => array(),
        'tables'         => array(),
        'results'        => array(),
        'exclude_tables' => array(),
        'compatibility'  => array()
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
     * @var mysqli|PDO connection
     */
    public $db;


    /**
     * @var bool
     */
    public $use_pdo = true;


    /**
     * How many rows to select at a time when replacing
     * @var int
     */
    public $page_size = 50000;


    /**
     * Searches for WP or Drupal context
     * Checks for $_POST data
     * Initialises database connection
     * Handles ajax
     * Runs replacement
     *
     * @param string $name database name
     * @param string $user database username
     * @param string $pass database password
     * @param string $host database hostname
     * @param string $port database connection port
     * @param string $search search string / regex
     * @param string $replace replacement string
     * @param array $tables tables to run replcements against
     * @param bool $live live run
     * @param array $exclude_cols tables to run replcements against
     *
     * @return array|void
     */

    public function __construct( $args ) {

        $args = array_merge( array(
            'name'            => '',
            'user'            => '',
            'pass'            => '',
            'host'            => '',
            'port'            => 3306,
            'search'          => '',
            'replace'         => '',
            'tables'          => array(),
            'exclude_tables'  => array(),
            'exclude_cols'    => array(),
            'include_cols'    => array(),
            'dry_run'         => true,
            'regex'           => false,
            'pagesize'        => 50000,
            'alter_engine'    => false,
            'alter_collation' => false,
            'verbose'         => false,
            'ssl_key'         => false,
            'ssl_cert'        => false,
            'ssl_ca'          => false,
            'ssl_check'       => true,
            'ssl_ca_dir'      => null,
            'ssl_cipher'      => null,
            'debug'           => false
        ), $args );

        // handle exceptions
        set_exception_handler( array( $this, 'exceptions' ) );

        // handle errors
        if ( $args['debug'] === false ) {
            set_error_handler( array( $this, 'error_handler' ), E_ERROR | E_WARNING );
        }

        // Setting this so that mb_split works correctly.
        // BEAR IN MIND that this affects the handling of strings INTERNALLY rather than
        // at the html output interface, the console interface, json interface, or the database interface.
        // This means that if the DB has a different charset (utf16?), we need to make sure that it's
        // normalised to utf-8 internally and output in the appropriate charset.
        mb_regex_encoding( 'UTF-8' );

        // allow a string for columns
        foreach ( array( 'exclude_cols', 'include_cols', 'tables', 'exclude_tables' ) as $maybe_string_arg ) {

            ini_set( 'unserialize_callback_func', 'object_serializer' );

            if ( is_string( $args[ $maybe_string_arg ] ) ) {
                $args[ $maybe_string_arg ] = array_filter(
                    array_map( 'trim',
                        explode( ',', $args[ $maybe_string_arg ] )
                    )
                );
            }
        }

        // verify that the port number is logical
        // work around PHPs inability to stringify a zero without making it an empty string
        // AND without casting away trailing characters if they are present.
        $port_as_string = (string) $args['port'] ? (string) $args['port'] : "0";
        if ( (string) abs( (int) $args['port'] ) !== $port_as_string ) {
            $port_error = 'Port number must be a positive integer if specified.';
            $this->add_error( $port_error, 'db' );
            if ( defined( 'STDIN' ) ) {
                echo 'Error: ' . $port_error;
            }

            return;
        }

        // set class vars
        foreach ( $args as $property => $value ) {
            if ( is_string( $value ) ) {
                $value = stripcslashes( $value );
            }
            if ( is_array( $value ) ) {
                $value = array_map( 'stripcslashes', $value );
            }
            $this->$property = $value;
        }

        // only for non cli call, cli set no timeout, no memory limit
        if ( ! defined( 'STDIN' ) ) {

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
            } // update collation
            elseif ( $this->alter_collation ) {
                $report = $this->update_collation( $this->alter_collation, $this->tables );
            } elseif ( is_array( $this->search ) ) {
                $report = array();
                for ( $i = 0; $i < count( $this->search ); $i ++ ) {
                    $report[ $i ] = $this->replacer( $this->search[ $i ], $this->replace[ $i ], $this->tables,
                        $this->exclude_tables );
                }

            } else {
                $report = $this->replacer( $this->search, $this->replace, $this->tables, $this->exclude_tables );
            }

        } else {
            $report = $this->report;
        }

        // store report
        $this->report = $report;

        return $report;
    }


    /**
     * Terminates db connection
     *
     * @return void
     */
    public function __destruct() {
        if ( $this->db_valid() ) {
            $this->db_close();
        }
    }


    public function get( $property ) {
        return $this->$property;
    }

    public function set( $property, $value ) {
        $this->$property = $value;
    }


    /**
     * @param $exception Exception
     */
    public function exceptions( $exception ) {
        echo $exception->getMessage() . "\n";
    }

    /**
     * Custom error handler
     *
     * @param $errno
     * @param $message
     * @param $file
     * @param $line
     *
     * @return bool
     */
    public function error_handler( $errno, $message, $file, $line ) {
        if ( ! ( error_reporting() & $errno ) ) {
            return false;
        }

        switch ( $errno ) {
            case E_USER_ERROR:
                echo "ERROR [$errno]: $message\n";
                echo "Aborting...\n";
                exit( $errno );
                break;

            case E_USER_WARNING:
                echo "WARNING [$errno] $message\n";
                break;

            default:
                echo "Unknown error: [$errno] $message in $file on line $line\n";
                exit( $errno );
                break;
        }

        /* Don't execute PHP internal error handler */

        return true;
    }


    public function log( $type = '' ) {
        $args = array_slice( func_get_args(), 1 );
        if ( $this->verbose ) {
            echo "{$type}: ";
            print_r( $args );
            echo "\n";
        }

        return $args;
    }


    public function add_error( $error, $type = null ) {
        if ( $type !== null ) {
            $this->error_type = $type;
        }
        $this->errors[ $this->error_type ][] = $error;
        $this->log( 'error', $this->error_type, $error );
    }

    /**
     * Setup connection, populate tables array
     * Also responsible for selecting the type of connection to use.
     *
     * @return void|bool
     */
    public function db_setup() {
        $mysqli_available = class_exists( 'mysqli' );
        $pdo_available    = class_exists( 'PDO' );

        $connection_type = '';

        // Default to mysqli type.
        // Only advance to PDO if all conditions are met.
        if ( $mysqli_available ) {
            $connection_type = 'mysqli';
        }

        if ( $pdo_available ) {
            // PDO is the interface, but it may not have the 'mysql' module.
            $mysql_driver_present = in_array( 'mysql', pdo_drivers() );

            if ( $mysql_driver_present ) {
                $connection_type = 'pdo';
            }
        }

        // Abort if mysqli and PDO are both broken.
        if ( '' === $connection_type ) {
            $this->add_error( 'Could not find any MySQL database drivers. (MySQLi or PDO required.)', 'db' );

            return false;
        }


        // connect
        $this->db = $this->connect( $connection_type );

    }


    /**
     * Database connection type router
     *
     * @param string $type
     *
     * @return PDO|mysqli|null
     */
    public function connect( $type = '' ) {
        if ( $type == 'mysqli' ) {
            return $this->connect_mysqli();
        }

        if ( $type == 'pdo' ) {
            return $this->connect_pdo();
        }

        $this->add_error( 'Cannot connect using the method ' . $type );

        return null;
    }


    /**
     * Creates the database connection using newer mysqli functions
     *
     * @return mysqli
     */
    public function connect_mysqli() {

        // switch off PDO
        $this->use_pdo = false;
        $connection    = mysqli_init();
        if ( PHP_MAJOR_VERSION >= 5 && PHP_MINOR_VERSION >= 3 ) {
            mysqli_options( $connection, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $this->ssl_check );
        }

        if ( $this->ssl_key || $this->ssl_cert || $this->ssl_ca ) {
            $status = mysqli_ssl_set(
                $connection,
                $this->ssl_key,
                $this->ssl_cert,
                $this->ssl_ca,
                $this->ssl_ca_dir,
                $this->ssl_cipher
            );

            if ( $status === false ) {
                $this->add_error( 'Cannot use SSL using mysqli', 'db' );
            }
        }

        if ( $this->debug ) {
            mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
        }

        // unset if not available
        if ( mysqli_real_connect( $connection, $this->host, $this->user, $this->pass, $this->name,
                $this->port ) === false ) {
            $this->add_error( mysqli_connect_error(), 'db' );
        }

        return $connection;
    }


    /**
     * Sets up database connection using PDO
     *
     * @return PDO|bool
     */
    public function connect_pdo() {

        try {
            $params_map = array(
                'ssl_key'    => PDO::MYSQL_ATTR_SSL_KEY,
                'ssl_cert'   => PDO::MYSQL_ATTR_SSL_CERT,
                'ssl_ca'     => PDO::MYSQL_ATTR_SSL_CA,
                'ssl_ca_dir' => PDO::MYSQL_ATTR_SSL_CAPATH,
                'ssl_cipher' => PDO::MYSQL_ATTR_SSL_CIPHER
            );

            $params = array();

            foreach ( $params_map as $property => $pdo_param ) {
                if ( $this->$property ) {
                    $params[ $pdo_param ] = $this->$property;
                }
            }

            if ( PHP_MAJOR_VERSION >= 7 && ! empty( $params ) ) {
                $params[ PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT ] = $this->ssl_check;
            }

            if ( $this->debug ) {
                $params[ PDO::ATTR_ERRMODE ] = PDO::ERRMODE_EXCEPTION;
            }

            $connection = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->name}",
                $this->user,
                $this->pass,
                $params
            );

        } catch ( PDOException $exception ) {
            if ( $this->debug ) {
                throw $exception;
            }
            $this->add_error( $exception->getMessage(), 'db' );
            $connection = false;
        }

        // check if there's a problem with our database at this stage
        if ( $connection && ! $connection->query( 'SHOW TABLES' ) ) {
            $error_info = $connection->errorInfo();
            if ( ! empty( $error_info ) && is_array( $error_info ) ) {
                $this->add_error( array_pop( $error_info ), 'db' );
            } // Array pop will only accept a $var..
            $connection = false;
        }

        return $connection;
    }


    /**
     * Retrieve all tables from the database
     *
     * @return array
     */
    public function get_tables() {
        // get tables

        // A clone of show table status but with character set for the table.
        $show_table_status = "SELECT
		  t.`TABLE_NAME` as Name,
		  t.`ENGINE` as `Engine`,
		  t.`version` as `Version`,
		  t.`ROW_FORMAT` AS `Row_format`,
		  t.`TABLE_ROWS` AS `Rows`,
		  t.`AVG_ROW_LENGTH` AS `Avg_row_length`,
		  t.`DATA_LENGTH` AS `Data_length`,
		  t.`MAX_DATA_LENGTH` AS `Max_data_length`,
		  t.`INDEX_LENGTH` AS `Index_length`,
		  t.`DATA_FREE` AS `Data_free`,
		  t.`AUTO_INCREMENT` as `Auto_increment`,
		  t.`CREATE_TIME` AS `Create_time`,
		  t.`UPDATE_TIME` AS `Update_time`,
		  t.`CHECK_TIME` AS `Check_time`,
		  t.`TABLE_COLLATION` as Collation,
		  c.`CHARACTER_SET_NAME` as Character_set,
		  t.`Checksum`,
		  t.`Create_options`,
		  t.`table_Comment` as `Comment`
		FROM information_schema.`TABLES` t
			LEFT JOIN information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` c
				ON ( t.`TABLE_COLLATION` = c.`COLLATION_NAME` )
		  WHERE t.`TABLE_SCHEMA` = '{$this->name}'
		  ORDER BY t.`TABLE_NAME`;
		";

        $all_tables_mysql = $this->db_query( $show_table_status );
        $all_tables       = array();

        if ( ! $all_tables_mysql ) {

            $this->add_error( $this->db_error(), 'db' );

        } else {

            // set the character set
            //$this->db_set_charset( $this->charset );

            while ( $table = $this->db_fetch( $all_tables_mysql ) ) {
                // ignore views
                if ( $table['Comment'] == 'VIEW' ) {
                    continue;
                }

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
        $schema     = $this->db_escape( $this->name );

        $charset = $this->db_query( "SELECT c.`character_set_name`
			FROM information_schema.`TABLES` t
				LEFT JOIN information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` c
				ON (t.`TABLE_COLLATION` = c.`COLLATION_NAME`)
			WHERE t.table_schema = {$schema}
				AND t.table_name = {$table_name}
			LIMIT 1;" );

        $encoding = false;
        if ( ! $charset ) {
            $this->add_error( $this->db_error(), 'db' );
        } else {
            $result   = $this->db_fetch( $charset );
            $encoding = isset( $result['character_set_name'] ) ? $result['character_set_name'] : false;
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
        $mysql_engines = $this->db_query( 'SHOW ENGINES;' );
        $engines       = array();

        if ( ! $mysql_engines ) {
            $this->add_error( $this->db_error(), 'db' );
        } else {
            while ( $engine = $this->db_fetch( $mysql_engines ) ) {
                if ( in_array( $engine['Support'], array( 'YES', 'DEFAULT' ) ) ) {
                    $engines[] = $engine['Engine'];
                }
            }
        }

        return $engines;
    }


    public function db_query( $query ) {
        if ( $this->use_pdo ) {
            return $this->db->query( $query );
        } else {
            return mysqli_query( $this->db, $query );
        }
    }

    public function db_update( $query ) {
        if ( $this->use_pdo ) {
            return $this->db->exec( $query );
        } else {
            return mysqli_query( $this->db, $query );
        }
    }

    public function db_error() {
        if ( $this->use_pdo ) {
            $error_info = $this->db->errorInfo();

            return ! empty( $error_info ) && is_array( $error_info ) ? array_pop( $error_info ) : 'Unknown error';
        } else {
            return mysqli_error( $this->db );
        }
    }

    /**
     * @param $data mysqli_result|PDOStatement
     *
     * @return array|null
     */
    public function db_fetch( $data ) {
        if ( $this->use_pdo ) {
            return $data->fetch();
        } else {
            return mysqli_fetch_array( $data );
        }
    }

    public function db_escape( $string ) {
        if ( $this->use_pdo ) {
            return $this->db->quote( $string );
        } else {
            return "'" . mysqli_real_escape_string( $this->db, $string ) . "'";
        }
    }

    public function db_free_result( $data ) {
        if ( $this->use_pdo ) {
            return $data->closeCursor();
        } else {
            return mysqli_free_result( $data );
        }
    }

    public function db_set_charset( $charset = '' ) {
        if ( ! empty( $charset ) ) {
            if ( ! $this->use_pdo && function_exists( 'mysqli_set_charset' ) ) {
                mysqli_set_charset( $this->db, $charset );
            } else {
                $this->db_query( 'SET NAMES ' . $charset );
            }
        }
    }

    public function db_close() {
        if ( $this->use_pdo ) {
            unset( $this->db );
        } else {
            mysqli_close( $this->db );
        }
    }

    public function db_valid() {
        return (bool) $this->db;
    }


    /**
     * Walk an array replacing one element for another. ( NOT USED ANY MORE )
     *
     * @param string $find The string we want to replace.
     * @param string $replace What we'll be replacing it with.
     * @param array $data Used to pass any subordinate arrays back to the
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
                    if ( is_string( $value ) ) {
                        $data[ $key ] = $this->str_replace( $find, $replace, $value );
                    }
                }
            }
        } else {
            if ( is_string( $data ) ) {
                $data = $this->str_replace( $find, $replace, $data );
            }
        }
    }


    /**
     * Take a serialised array and unserialise it replacing elements as needed and
     * unserialising any subordinate arrays and performing the replace on those too.
     *
     * @param string $from String we're looking to replace.
     * @param string $to What we want it to be replaced with
     * @param array $data Used to pass any subordinate arrays back to in.
     * @param bool $serialised Does the array passed via $data need serialising.
     *
     * @return array    The original array with all elements replaced as needed.
     */
    public function recursive_unserialize_replace( $from = '', $to = '', $data = '', $serialised = false ) {

        // some unserialised data cannot be re-serialised eg. SimpleXMLElements
        try {

            if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {
                $data = $this->recursive_unserialize_replace( $from, $to, $unserialized, true );
            } elseif ( is_array( $data ) ) {
                $_tmp = array();
                foreach ( $data as $key => $value ) {
                    $_tmp[ $key ] = $this->recursive_unserialize_replace( $from, $to, $value, false );
                }

                $data = $_tmp;
                unset( $_tmp );
            } elseif ( is_object( $data ) && ! is_a( $data, '__PHP_Incomplete_Class' ) ) {
                $_tmp  = $data;
                $props = get_object_vars( $data );
                foreach ( $props as $key => $value ) {
                    $_tmp->$key = $this->recursive_unserialize_replace( $from, $to, $value, false );
                }

                $data = $_tmp;
                unset( $_tmp );
            } else {
                if ( is_string( $data ) ) {
                    $data = $this->str_replace( $from, $to, $data );
                }
            }

            if ( $serialised ) {
                return serialize( $data );
            }

        } catch ( Error $error ) {
            $this->add_error( $error->getMessage(), 'results');
        } catch ( Exception $error ) {
            $this->add_error( $error->getMessage() . ':: This is usually caused by a plugin storing classes as a
		    serialised string which other PHP classes can\'t then access. It is not possible to unserialise this data
		    because the PHP can\'t access this class. P.S. It\'s most commonly a Yoast plugin that causes this error.',
                'results' );
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
        $length = mb_strlen( $matches[2] );
        if ( $length !== intval( $matches[1] ) ) {
            return "s:{$length}:\"{$matches[2]}\";";
        }

        return $matches[0];
    }


    /**
     * The main loop triggered in step 5. Up here to keep it out of the way of the
     * HTML. This walks every table in the db that was selected in step 3 and then
     * walks every row and column replacing all occurences of a string with another.
     * We split large tables into 50,000 row blocks when dealing with them to save
     * on memmory consumption.
     *
     * @param string $search What we want to replace
     * @param string $replace What we want to replace it with.
     * @param array $tables The tables we want to look at.
     *
     * @return array|bool    Collection of information gathered during the run.
     */
    public function replacer( $search = '', $replace = '', $tables = array(), $exclude_tables = array() ) {
        $search = (string) $search;
        // check we have a search string, bail if not
        if ( '' === $search ) {
            $this->add_error( 'Search string is empty', 'search' );

            return false;
        }

        $report = array(
            'tables'        => 0,
            'rows'          => 0,
            'change'        => 0,
            'updates'       => 0,
            'start'         => microtime( true ),
            'end'           => microtime( true ),
            'errors'        => array(),
            'table_reports' => array()
        );

        $table_report = array(
            'rows'    => 0,
            'change'  => 0,
            'changes' => array(),
            'updates' => 0,
            'start'   => microtime( true ),
            'end'     => microtime( true ),
            'errors'  => array(),
        );

        $dry_run = $this->dry_run;
        $errors  = $this->errors;
        if ( $this->dry_run and ! ( in_array( 'The dry-run option was selected. No replacements will be made.',
                $errors['results'] ) ) )    // Report this as a search-only run.
        {
            $this->add_error( 'The dry-run option was selected. No replacements will be made.', 'results' );
        }

        // if no tables selected assume all
        if ( empty( $tables ) ) {
            $all_tables = $this->get_tables();
            $tables     = array_keys( $all_tables );
        }

        if ( is_array( $tables ) && ! empty( $tables ) ) {

            foreach ( $tables as $table ) {
                if ( in_array( $table, $exclude_tables ) ) {
                    $this->add_error( 'Ignoring Table: ' . $table );
                    continue;
                }
                $encoding = $this->get_table_character_set( $table );
                switch ( $encoding ) {

                    // Tables encoded with this work for me only when I set names to utf8. I don't trust this in the wild so I'm going to avoid.
                    case 'utf16':
                    case 'utf32':
                        //$encoding = 'utf8';
                        $this->add_error( "The table \"{$table}\" is encoded using \"{$encoding}\" which is currently unsupported.",
                            'results' );
                        continue 2;
                        break;

                    default:
                        $this->db_set_charset( $encoding );
                        break;
                }


                $report['tables'] ++;

                // get primary key and columns
                list( $primary_key, $columns ) = $this->get_columns( $table );

                if ( $primary_key === null || empty( $primary_key ) ) {
                    $this->add_error( "The table \"{$table}\" has no primary key. Changes will have to be made manually.",
                        'results' );
                    continue;
                }

                // create new table report instance
                $new_table_report          = $table_report;
                $new_table_report['start'] = microtime( true );

                $this->log( 'search_replace_table_start', $table, $search, $replace );

                // Count the number of rows we have in the table if large we'll split into blocks, This is a mod from Simon Wheatley
                $row_count   = $this->db_query( "SELECT COUNT(*) FROM `{$table}`" );
                $rows_result = $this->db_fetch( $row_count );
                $row_count   = $rows_result[0];

                $page_size = $this->page_size;
                $pages     = ceil( $row_count / $page_size );

                for ( $page = 0; $page < $pages; $page ++ ) {

                    $start = $page * $page_size;

                    // Grab the content of the table
                    $data = $this->db_query( sprintf( 'SELECT * FROM `%s` LIMIT %d, %d', $table, $start, $page_size ) );

                    if ( ! $data ) {
                        $this->add_error( $this->db_error(), 'results' );
                    }

                    while ( $row = $this->db_fetch( $data ) ) {

                        $report['rows'] ++; // Increment the row counter
                        $new_table_report['rows'] ++;

                        $update_sql = array();
                        $where_sql  = array();
                        $update     = false;

                        foreach ( $columns as $column ) {

                            $edited_data = $data_to_fix = $row[ $column ];

                            if ( in_array( $column, $primary_key ) ) {
                                $where_sql[] = "`{$column}` = " . $this->db_escape( $data_to_fix );
                                continue;
                            }

                            // exclude cols
                            if ( in_array( $column, $this->exclude_cols ) ) {
                                continue;
                            }

                            // include cols
                            if ( ! empty( $this->include_cols ) && ! in_array( $column, $this->include_cols ) ) {
                                continue;
                            }

                            // Run a search replace on the data that'll respect the serialisation.
                            $edited_data = $this->recursive_unserialize_replace( $search, $replace, $data_to_fix );

                            // Something was changed
                            if ( $edited_data != $data_to_fix ) {

                                $report['change'] ++;
                                $new_table_report['change'] ++;

                                // log first x changes
                                if ( $new_table_report['change'] <= $this->report_change_num ) {
                                    $new_table_report['changes'][] = array(
                                        'row'    => $new_table_report['rows'],
                                        'column' => $column,
                                        'from'   => ( $data_to_fix ),
                                        'to'     => ( $edited_data )
                                    );
                                }

                                $update_sql[] = "`{$column}` = " . $this->db_escape( $edited_data );
                                $update       = true;

                            }

                        }

                        if ( $dry_run ) {
                            // nothing for this state
                        } elseif ( $update && ! empty( $where_sql ) ) {

                            $sql = 'UPDATE ' . $table . ' SET ' . implode( ', ',
                                    $update_sql ) . ' WHERE ' . implode( ' AND ', array_filter( $where_sql ) );

                            $result = $this->db_update( $sql );

                            if ( ! is_int( $result ) && ! $result ) {

                                $this->add_error( $this->db_error(), 'results' );

                            } else {

                                $report['updates'] ++;
                                $new_table_report['updates'] ++;
                            }

                        }

                    }

                    $this->db_free_result( $data );

                }

                $new_table_report['end'] = microtime( true );

                // store table report in main
                $report['table_reports'][ $table ] = $new_table_report;

                // log result
                $this->log( 'search_replace_table_end', $table, $new_table_report );
            }

        }

        $report['end'] = microtime( true );

        $this->log( 'search_replace_end', $search, $replace, $report );

        return $report;
    }


    public function get_columns( $table ) {
        $primary_key = array();
        $columns     = array();

        // Get a list of columns in this table
        $fields = $this->db_query( "DESCRIBE {$table}" );
        if ( ! $fields ) {
            $this->add_error( $this->db_error(), 'db' );
        } else {
            while ( $column = $this->db_fetch( $fields ) ) {
                $columns[] = $column['Field'];
                if ( $column['Key'] == 'PRI' ) {
                    $primary_key[] = $column['Field'];
                }
            }
        }

        return array( $primary_key, $columns );
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

        if ( empty( $this->engines ) ) {
            $this->engines = $this->get_engines();
        }

        if ( in_array( $engine, $this->engines ) ) {

            $report = array( 'engine' => $engine, 'converted' => array() );

            $all_tables = $this->get_tables();

            if ( empty( $tables ) ) {
                $tables = array_keys( $all_tables );
            }

            foreach ( $tables as $table ) {
                $table_info = $all_tables[ $table ];

                // are we updating the engine?
                if ( $table_info['Engine'] != $engine ) {
                    $engine_converted = $this->db_query( "alter table {$table} engine = {$engine};" );
                    if ( ! $engine_converted ) {
                        $this->add_error( $this->db_error(), 'results' );
                    } else {
                        $report['converted'][ $table ] = true;
                    }
                    continue;
                } else {
                    $report['converted'][ $table ] = false;
                }

                if ( isset( $report['converted'][ $table ] ) ) {
                    $this->log( 'update_engine', $table, $report, $engine );
                }
            }

        } else {

            $this->add_error( 'Cannot convert tables to unsupported table engine &rdquo;' . $engine . '&ldquo;',
                'results' );

        }

        return $report;
    }


    /**
     * Updates the characterset and collation on the specified tables
     *
     * @param string $collate table collation
     * @param array $tables tables to modify
     *
     * @return array    Modification report
     */
    public function update_collation( $collation = 'utf8_unicode_ci', $tables = array() ) {

        $report = false;

        if ( is_string( $collation ) ) {

            $report = array( 'collation' => $collation, 'converted' => array() );

            $all_tables = $this->get_tables();

            if ( empty( $tables ) ) {
                $tables = array_keys( $all_tables );
            }

            // charset is same as collation up to first underscore
            $charset = preg_replace( '/^([^_]+).*$/', '$1', $collation );

            foreach ( $tables as $table ) {
                $table_info = $all_tables[ $table ];

                // are we updating the engine?
                if ( $table_info['Collation'] != $collation ) {
                    $engine_converted = $this->db_query( "alter table {$table} convert to character set {$charset} collate {$collation};" );
                    if ( ! $engine_converted ) {
                        $this->add_error( $this->db_error(), 'results' );
                    } else {
                        $report['converted'][ $table ] = true;
                    }
                    continue;
                } else {
                    $report['converted'][ $table ] = false;
                }

                if ( isset( $report['converted'][ $table ] ) ) {
                    $this->log( 'update_collation', $table, $report, $collation );
                }
            }

        } else {

            $this->add_error( 'Collation must be a valid string', 'results' );

        }

        return $report;
    }


    /**
     * Replace all occurrences of the search string with the replacement string.
     *
     * @param mixed $search
     * @param mixed $replace
     * @param mixed $subject
     * @param int $count
     *
     * @return mixed
     * @copyright Copyright 2012 Sean Murphy. All rights reserved.
     * @license http://creativecommons.org/publicdomain/zero/1.0/
     * @link http://php.net/manual/function.str-replace.php
     *
     * @author Sean Murphy <sean@iamseanmurphy.com>
     */
    public static function mb_str_replace( $search, $replace, $subject, &$count = 0 ) {
        if ( ! is_array( $subject ) ) {
            // Normalize $search and $replace so they are both arrays of the same length
            $searches     = is_array( $search ) ? array_values( $search ) : array( $search );
            $replacements = is_array( $replace ) ? array_values( $replace ) : array( $replace );
            $replacements = array_pad( $replacements, count( $searches ), '' );

            foreach ( $searches as $key => $search ) {
                $parts = mb_split( preg_quote( $search ), $subject );
                if ( ! is_array( $parts ) ) {
                    continue;
                }
                $count   += count( $parts ) - 1;
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
        if ( $this->regex ) {
            return preg_replace( $search, $replace, $string, - 1, $count );
        } elseif ( function_exists( 'mb_split' ) ) {
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
        if ( ! preg_match( "/[\200-\237]/", $string ) and ! preg_match( "/[\241-\377]/", $string ) ) {
            return $string;
        }

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


/**
 * .. make classes
 *
 * @param string $class_name
 *
 * @return void
 */
function object_serializer( $class_name ) {
    eval( "class {$class_name} extends ArrayObject {}" );
}

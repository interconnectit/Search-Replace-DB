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
 * Version 3.0.0:
 * 		* Major overhaul
 * 		* Multibyte string replacements
 * 		* UI completely redesigned
 * 		* Removed all links from script until 'delete' has been clicked to avoid
 * 		  security risk from our access logs
 * 		* Search replace functionality moved to it's own separate class
 * 		* Replacements done table by table to avoid timeouts
 * 		* Convert tables to InnoDB
 * 		* Convert tables to utf8_unicode_ci
 * 		* Use PDO if available
 * 		* Preview/view changes
 * 		* Optionally use preg_replace()
 * 		* Scripts bootstraps WordPress/Drupal to avoid issues with unknown
 * 		  serialised objects/classes
 * 		* Added marketing stuff to deleted screen (sorry but we're running a
 * 		  business!)
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

require_once( 'srdb.class.php' );

class icit_srdb_ui extends icit_srdb {

	/**
	 * @var string Root path of the CMS
	 */
	public $path;

	public $is_wordpress = false;
	public $is_drupal = false;

	public function __construct() {

		// php 5.4 date timezone requirement, shouldn't affect anything
		date_default_timezone_set( 'Europe/London' );

		// discover environment
		if ( $this->is_wordpress() ) {

			// populate db details
			$name 		= DB_NAME;
			$user 		= DB_USER;
			$pass 		= DB_PASSWORD;
			$host 		= DB_HOST;
			$charset 	= DB_CHARSET;
			$collate 	= DB_COLLATE;

		} elseif( $this->is_drupal() ) {

			$database = Database::getConnection();
			$database_opts = $database->getConnectionOptions();

			// populate db details
			$name 		= $database_opts[ 'database' ];
			$user 		= $database_opts[ 'username' ];
			$pass 		= $database_opts[ 'password' ];
			$host 		= $database_opts[ 'host' ];
			$charset 	= 'utf8';
			$collate 	= '';

		}

		// always override with post data
		if ( isset( $_POST[ 'name' ] ) ) {
			$name = $_POST[ 'name' ];	// your database
			$user = $_POST[ 'user' ];	// your db userid
			$pass = $_POST[ 'pass' ];	// your db password
			$host = $_POST[ 'host' ]; // normally localhost, but not necessarily.
			$charset = 'utf8'; // isset( $_POST[ 'char' ] ) ? stripcslashes( $_POST[ 'char' ] ) : '';	// your db charset
			$collate = '';
		}

		// Search replace details
		$search = isset( $_POST[ 'search' ] ) ? $_POST[ 'search' ] : '';
		$replace = isset( $_POST[ 'replace' ] ) ? $_POST[ 'replace' ] : '';

		// regex options
		$regex 	 = isset( $_POST[ 'regex' ] );
		$regex_i = isset( $_POST[ 'regex_i' ] );
		$regex_m = isset( $_POST[ 'regex_m' ] );
		$regex_s = isset( $_POST[ 'regex_s' ] );
		$regex_x = isset( $_POST[ 'regex_x' ] );

		// Tables to scanned
		$tables = isset( $_POST[ 'tables' ] ) && is_array( $_POST[ 'tables' ] ) ? $_POST[ 'tables' ] : array( );
		if ( isset( $_POST[ 'use_tables' ] ) && $_POST[ 'use_tables' ] == 'all' )
			$tables = array();

		// exclude / include columns
		$exclude_cols = isset( $_POST[ 'exclude_cols' ] ) ? $_POST[ 'exclude_cols' ] : array();
		$include_cols = isset( $_POST[ 'include_cols' ] ) ? $_POST[ 'include_cols' ] : array();

		foreach( array( 'exclude_cols', 'include_cols' ) as $maybe_string_arg ) {
			if ( is_string( $$maybe_string_arg ) )
				$$maybe_string_arg = array_filter( array_map( 'trim', explode( ',', $$maybe_string_arg ) ) );
		}

		// update class vars
		$vars = array(
			'name', 'user', 'pass', 'host',
			'charset', 'collate', 'tables',
			'search', 'replace',
			'exclude_cols', 'include_cols',
			'regex', 'regex_i', 'regex_m', 'regex_s', 'regex_x'
		);

		foreach( $vars as $var ) {
			if ( isset( $$var ) )
				$this->set( $var, $$var );
		}

		// are doing something?
		$show = '';
		if ( isset( $_POST[ 'submit' ] ) ) {
			if ( is_array( $_POST[ 'submit' ] ) )
				$show = array_shift( array_keys( $_POST[ 'submit' ] ) );
			if ( is_string( $_POST[ 'submit' ] ) )
				$show = preg_replace( '/submit\[([a-z0-9]+)\]/', '$1', $_POST[ 'submit' ] );
		}

		// is it an AJAX call
		$ajax = isset( $_POST[ 'ajax' ] );

		// body callback
		$html = 'ui';

		switch( $show ) {

			// remove search replace
			case 'delete':

				// determine if it's the folder of compiled version
				if ( basename( __FILE__ ) == 'index.php' )
					$path = str_replace( basename( __FILE__ ), '', __FILE__ );
				else
					$path = __FILE__;

				if ( $this->delete_script( $path ) ) {
					if ( is_file( __FILE__ ) )
						$this->add_error( 'Could not delete the search replace script. You will have to delete it manually', 'delete' );
					else
						$this->add_error( 'Search/Replace has been successfully removed from your server', 'delete' );
				}

				$html = 'deleted';

				break;

			case 'liverun':

				// bsy-web, 20130621: Check live run was explicitly clicked and only set false then
				$this->set( 'dry_run', false );

			case 'dryrun':

				// build regex string
				// non UI implements can just pass in complete regex string
				if ( $this->regex ) {
					$mods = '';
					if ( $this->regex_i ) $mods .= 'i';
					if ( $this->regex_s ) $mods .= 's';
					if ( $this->regex_m ) $mods .= 'm';
					if ( $this->regex_x ) $mods .= 'x';
					$this->search = '/' . $this->search . '/u' . $mods;
				}

				// call search replace class
				$parent = parent::__construct( array(
					'name' => $this->get( 'name' ),
					'user' => $this->get( 'user' ),
					'pass' => $this->get( 'pass' ),
					'host' => $this->get( 'host' ),
					'search' => $this->get( 'search' ),
					'replace' => $this->get( 'replace' ),
					'tables' => $this->get( 'tables' ),
					'dry_run' => $this->get( 'dry_run' ),
					'regex' => $this->get( 'regex' ),
					'exclude_cols' => $this->get( 'exclude_cols' ),
					'include_cols' => $this->get( 'include_cols' )
				) );

				break;

			case 'innodb':

				// call search replace class to alter engine
				$parent = parent::__construct( array(
					'name' => $this->get( 'name' ),
					'user' => $this->get( 'user' ),
					'pass' => $this->get( 'pass' ),
					'host' => $this->get( 'host' ),
					'tables' => $this->get( 'tables' ),
					'alter_engine' => 'InnoDB',
				) );

				break;

			case 'utf8':

				// call search replace class to alter engine
				$parent = parent::__construct( array(
					'name' => $this->get( 'name' ),
					'user' => $this->get( 'user' ),
					'pass' => $this->get( 'pass' ),
					'host' => $this->get( 'host' ),
					'tables' => $this->get( 'tables' ),
					'alter_collation' => 'utf8_unicode_ci',
				) );

				break;

			case 'update':
			default:

				// get tables or error messages
				$this->db_setup();

				break;
		}

		$info = array(
			'table_select' => $this->table_select( false ),
			'engines' => $this->get( 'engines' )
		);

		// output
		header( 'HTTP/1.1 200 OK' );

		if ( ! $ajax )
			$this->html( $html );
		else {

			// return json version of results
			header( 'Content-Type: application/json' );

			echo json_encode( array(
				'errors' => $this->get( 'errors' ),
				'report' => $this->get( 'report' ),
				'info' 	 => $info
			) );

			exit;

		}

	}


	public function exceptions( $exception ) {
		$this->add_error( '<p class="exception">' . $exception->getMessage() . '</p>' );
	}

	public function errors( $no, $message, $file, $line ) {
		$this->add_error( '<p class="error">' . "<strong>{$no}:</strong> {$message} in {$file} on line {$line}" . '</p>', 'results' );
	}


	/**
	 * http://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
	 *
	 * @param string $path directory/file path
	 *
	 * @return void
	 */
	public function delete_script( $path ) {
		return is_file( $path ) ?
				@unlink( $path ) :
				array_map( array( $this, __FUNCTION__ ), glob( $path . '/*' ) ) == @rmdir( $path );
	}


	/**
	 * Attempts to detect a WordPress installation and bootstraps the environment with it
	 *
	 * @return bool    Whether it is a WP install and we have database credentials
	 */
	public function is_wordpress() {

		$path_mod = '';
		$depth = 0;
		$max_depth = 4;
		$bootstrap_file = 'wp-blog-header.php';

		while( ! file_exists( dirname( __FILE__ ) . "{$path_mod}/{$bootstrap_file}" ) ) {
			$path_mod .= '/..';
			if ( $depth++ >= $max_depth )
				break;
		}

		if ( file_exists( dirname( __FILE__ ) . "{$path_mod}/{$bootstrap_file}" ) ) {

			// store WP path
			$this->path = dirname( __FILE__ ) . $path_mod;

			// just in case we're white screening
			try {
				// need to make as many of the globals available as possible or things can break
				// (globals suck)
				global $wp, $wpdb, $wp_query, $wp_the_query, $wp_version,
					$wp_db_version, $tinymce_version, $manifest_version,
					$required_php_version, $required_mysql_version,
					$post, $posts, $wp_locale, $authordata, $more, $numpages,
					$currentday, $currentmonth, $page, $pages, $multipage,
					$wp_rewrite, $wp_filesystem, $blog_id, $request,
					$wp_styles, $wp_taxonomies, $wp_post_types, $wp_filter,
					$wp_object_cache, $query_string, $single, $post_type,
					$is_iphone, $is_chrome, $is_safari, $is_NS4, $is_opera,
					$is_macIE, $is_winIE, $is_gecko, $is_lynx, $is_IE,
					$is_apache, $is_iis7, $is_IIS;

				// bootstrap WordPress
				require( dirname( __FILE__ ) . "{$path_mod}/{$bootstrap_file}" );

				$this->set( 'path', ABSPATH );

				$this->set( 'is_wordpress', true );

				return true;

			} catch( Exception $error ) {

				// try and get database values using regex approach
				$db_details = $this->define_find( $this->path . '/wp-config.php' );

				if ( $db_details ) {

					define( 'DB_NAME', $db_details[ 'name' ] );
					define( 'DB_USER', $db_details[ 'user' ] );
					define( 'DB_PASSWORD', $db_details[ 'pass' ] );
					define( 'DB_HOST', $db_details[ 'host' ] );
					define( 'DB_CHARSET', $db_details[ 'char' ] );
					define( 'DB_COLLATE', $db_details[ 'coll' ] );

					// additional error message
					$this->add_error( 'WordPress detected but could not bootstrap environment. There might be a PHP error, possibly caused by changes to the database', 'db' );

				}

				if ( $db_details )
					return true;

			}

		}

		return false;
	}


	public function is_drupal() {

		$path_mod = '';
		$depth = 0;
		$max_depth = 4;
		$bootstrap_file = 'includes/bootstrap.inc';

		while( ! file_exists( dirname( __FILE__ ) . "{$path_mod}/{$bootstrap_file}" ) ) {
			$path_mod .= '/..';
			if ( $depth++ >= $max_depth )
				break;
		}

		if ( file_exists( dirname( __FILE__ ) . "{$path_mod}/{$bootstrap_file}" ) ) {

			try {
				// require the bootstrap include
				require_once( dirname( __FILE__ ) . "{$path_mod}/{$bootstrap_file}" );

				// define drupal root
				if ( ! defined( 'DRUPAL_ROOT' ) )
					define( 'DRUPAL_ROOT', dirname( __FILE__ ) . $path_mod );

				// load drupal
				drupal_bootstrap( DRUPAL_BOOTSTRAP_FULL );

				// confirm environment
				$this->set( 'is_drupal', true );

				return true;

			} catch( Exception $error ) {

				$this->add_error( 'Drupal detected but could not bootstrap environment. There might be a PHP error, possibly caused by changes to the database', 'db' );

			}

		}

		return false;
	}


	/**
	 * Search through the file name passed for a set of defines used to set up
	 * WordPress db access.
	 *
	 * @param string $filename The file name we need to scan for the defines.
	 *
	 * @return array    List of db connection details.
	 */
	public function define_find( $filename = 'wp-config.php' ) {

		if ( $filename == 'wp-config.php' ) {
			$filename = dirname( __FILE__ ) . '/' . basename( $filename );

			// look up one directory if config file doesn't exist in current directory
			if ( ! file_exists( $filename ) )
				$filename = dirname( __FILE__ ) . '/../' . basename( $filename );
		}

		if ( file_exists( $filename ) && is_file( $filename ) && is_readable( $filename ) ) {
			$file = @fopen( $filename, 'r' );
			$file_content = fread( $file, filesize( $filename ) );
			@fclose( $file );
		}

		preg_match_all( '/define\s*?\(\s*?([\'"])(DB_NAME|DB_USER|DB_PASSWORD|DB_HOST|DB_CHARSET|DB_COLLATE)\1\s*?,\s*?([\'"])([^\3]*?)\3\s*?\)\s*?;/si', $file_content, $defines );

		if ( ( isset( $defines[ 2 ] ) && ! empty( $defines[ 2 ] ) ) && ( isset( $defines[ 4 ] ) && ! empty( $defines[ 4 ] ) ) ) {
			foreach( $defines[ 2 ] as $key => $define ) {

				switch( $define ) {
					case 'DB_NAME':
						$name = $defines[ 4 ][ $key ];
						break;
					case 'DB_USER':
						$user = $defines[ 4 ][ $key ];
						break;
					case 'DB_PASSWORD':
						$pass = $defines[ 4 ][ $key ];
						break;
					case 'DB_HOST':
						$host = $defines[ 4 ][ $key ];
						break;
					case 'DB_CHARSET':
						$char = $defines[ 4 ][ $key ];
						break;
					case 'DB_COLLATE':
						$coll = $defines[ 4 ][ $key ];
						break;
				}
			}
		}

		return array(
			'host' => $host,
			'name' => $name,
			'user' => $user,
			'pass' => $pass,
			'char' => $char,
			'coll' => $coll
		);
	}


	/**
	* Simple html escaping
	*
	* @param string $string Thing that needs escaping
	* @param bool $echo   Do we echo or return?
	*
	* @return string    Escaped string.
	*/
	public function esc_html_attr( $string = '', $echo = false ) {
		$output = htmlentities( $string, ENT_QUOTES, 'UTF-8' );
		if ( $echo )
			echo $output;
		else
			return $output;
	}

	public function checked( $value, $value2, $echo = true ) {
		$output = $value == $value2 ? ' checked="checked"' : '';
		if ( $echo )
			echo $output;
		return $output;
	}

	public function selected( $value, $value2, $echo = true ) {
		$output = $value == $value2 ? ' selected="selected"' : '';
		if ( $echo )
			echo $output;
		return $output;
	}


	public function get_errors( $type ) {
		if ( ! isset( $this->errors[ $type ] ) || ! count( $this->errors[ $type ] ) )
			return;

		echo '<div class="errors">';
		foreach( $this->errors[ $type ] as $error ) {
			if ( $error instanceof Exception )
				echo '<p class="exception">' . $error->getMessage() . '</p>';
			elseif ( is_string( $error ) )
				echo $error;
		}
		echo '</div>';
	}


	public function get_report( $table = null ) {

		$report = $this->get( 'report' );

		if ( empty( $report ) )
			return;

		$dry_run = $this->get( 'dry_run' );
		$search = $this->get( 'search' );
		$replace = $this->get( 'replace' );

		// Calc the time taken.
		$time = array_sum( explode( ' ', $report[ 'end' ] ) ) - array_sum( explode( ' ', $report[ 'start' ] ) );

		$srch_rplc_input_phrase = $dry_run ?
			'searching for <strong>"' . $search . '"</strong> (to be replaced by <strong>"' . $replace . '"</strong>)' :
			'replacing <strong>"' . $search . '"</strong> with <strong>"' . $replace . '"</strong>';

		echo '
		<div class="report">';

		echo '
			<h2>Report</h2>';

		echo '
			<p>';
		printf(
			'In the process of %s we scanned <strong>%d</strong> tables with a total of
			<strong>%d</strong> rows, <strong>%d</strong> cells %s changed.
			<strong>%d</strong> db updates were actually performed.
			It all took <strong>%f</strong> seconds.',
			$srch_rplc_input_phrase,
			$report[ 'tables' ],
			$report[ 'rows' ],
			$report[ 'change' ],
			$dry_run ? 'would have been' : 'were',
			$report[ 'updates' ],
			$time
		);
		echo '
			</p>';

		echo '
			<table class="table-reports">
				<thead>
					<tr>
						<th>Table</th>
						<th>Rows</th>
						<th>Cells changed</th>
						<th>Updates</th>
						<th>Seconds</th>
					</tr>
				</thead>
				<tbody>';
		foreach( $report[ 'table_reports' ] as $table => $t_report ) {

			$t_time = array_sum( explode( ' ', $t_report[ 'end' ] ) ) - array_sum( explode( ' ', $t_report[ 'start' ] ) );

			echo '
					<tr>';
			printf( '
						<th>%s:</th>
						<td>%d</td>
						<td>%d</td>
						<td>%d</td>
						<td>%f</td>',
				$table,
				$t_report[ 'rows' ],
				$t_report[ 'change' ],
				$t_report[ 'updates' ],
				$t_time
			);
			echo '
					</tr>';

		}
		echo '
				</tbody>
			</table>';

		echo '
		</div>';

	}


	public function table_select( $echo = true ) {

		$table_select = '';

		if ( ! empty( $this->all_tables ) ) {
			$table_select .= '<select name="tables[]" multiple="multiple">';
			foreach( $this->all_tables as $table ) {
				$size = $table[ 'Data_length' ] / 1000;
				$size_unit = 'kb';
				if ( $size > 1000 ) {
					$size = $size / 1000;
					$size_unit = 'Mb';
				}
				if ( $size > 1000 ) {
					$size = $size / 1000;
					$size_unit = 'Gb';
				}
				$size = number_format( $size, 2 ) . $size_unit;
				$rows = $table[ 'Rows' ] > 1 ? 'rows' : 'row';

				$table_select .= '<option value="' . $this->esc_html_attr( $table[ 0 ], false ) . '" ' . $this->selected( true, in_array( $table[ 0 ], $this->tables ), false ) . '>' . "{$table[0]}: {$table['Engine']}, rows: {$table['Rows']}, size: {$size}, collation: {$table['Collation']}" . '</option>';
			}
			$table_select .= '</select>';
		}

		if ( $echo )
			echo $table_select;
		return $table_select;
	}


	public function ui() {

		// Warn if we're running in safe mode as we'll probably time out.
		if ( ini_get( 'safe_mode' ) ) {
			?>
			<div class="special-errors">
				<h4>Warning</h4>
				<?php echo printf( '<p>Safe mode is on so you may run into problems if it takes longer than %s seconds to process your request.</p>', ini_get( 'max_execution_time' ) ); ?>
			</div>
			<?php
		}

		?>
		<form action="" method="post">

			<!-- 1. search/replace -->
			<fieldset class="row row-search">

				<h1>search<span>/</span>replace</h1>

				<?php $this->get_errors( 'search' ); ?>

				<div class="fields fields-large">
					<label for="search"><span class="label-text">replace</span> <span class="hide-if-regex-off regex-left">/</span><input id="search" type="text" placeholder="search for&hellip;" value="<?php $this->esc_html_attr( $this->search, true ); ?>" name="search" /><span class="hide-if-regex-off regex-right">/</span></label>
					<label for="replace"><span class="label-text">with</span> <input id="replace" type="text" placeholder="replace with&hellip;" value="<?php $this->esc_html_attr( $this->replace, true ); ?>" name="replace" /></label>
					<label for="regex" class="field-advanced"><input id="regex" type="checkbox" name="regex" value="1" <?php $this->checked( true, $this->regex ); ?> /> use regex</label>
				</div>

				<div class="fields field-advanced hide-if-regex-off">
					<label for="regex_i" class="field field-advanced"><input type="checkbox" name="regex_i" id="regex_i" value="1" <?php $this->checked( true, $this->regex_i ); ?> /> <abbr title="case insensitive">i</abbr></abbr></label>
					<label for="regex_m" class="field field-advanced"><input type="checkbox" name="regex_m" id="regex_m" value="1" <?php $this->checked( true, $this->regex_m ); ?> /> <abbr title="multiline">m</abbr></label>
					<label for="regex_s" class="field field-advanced"><input type="checkbox" name="regex_s" id="regex_s" value="1" <?php $this->checked( true, $this->regex_s ); ?> /> <abbr title="dot also matches newlines">s</abbr></label>
					<label for="regex_x" class="field field-advanced"><input type="checkbox" name="regex_x" id="regex_x" value="1" <?php $this->checked( true, $this->regex_x ); ?> /> <abbr title="extended mode">x</abbr></label>
				</div>

			</fieldset>

			<!-- 2. db details -->
			<fieldset class="row row-db">

				<h1>db details</h1>

				<?php $this->get_errors( 'db' ); ?>

				<div class="fields fields-small">

					<div class="field field-short">
						<label for="name">name</label>
						<input id="name" name="name" type="text" value="<?php $this->esc_html_attr( $this->name, true ); ?>" />
					</div>

					<div class="field field-short">
						<label for="user">user</label>
						<input id="user" name="user" type="text" value="<?php $this->esc_html_attr( $this->user, true ); ?>" />
					</div>

					<div class="field field-short">
						<label for="pass">pass</label>
						<input id="pass" name="pass" type="text" value="<?php $this->esc_html_attr( $this->pass, true ); ?>" />
					</div>

					<div class="field field-short">
						<label for="host">host</label>
						<input id="host" name="host" type="text" value="<?php $this->esc_html_attr( $this->host, true ); ?>" />
					</div>

				</div>

			</fieldset>

			<!-- 3. tables -->
			<fieldset class="row row-tables">

				<h1>tables</h1>

				<?php $this->get_errors( 'tables' ); ?>

				<div class="fields">

					<div class="field radio">
						<label for="all_tables">
							<input id="all_tables" name="use_tables" value="all" type="radio" <?php if ( ! $this->db_valid() ) echo 'disabled="disabled"'; ?> <?php $this->checked( true, empty( $this->tables ) ); ?> />
							all tables
						</label>
					</div>

					<div class="field radio">
						<label for="subset_tables">
							<input id="subset_tables" name="use_tables" value="subset" type="radio" <?php if ( ! $this->db_valid() ) echo 'disabled="disabled"'; ?> <?php $this->checked( false, empty( $this->tables ) ); ?> />
							select tables
						</label>
					</div>

					<div class="field table-select hide-if-js"><?php $this->table_select(); ?></div>

				</div>

				<div class="fields field-advanced">

					<div class="field field-advanced field-long">
						<label for="exclude_cols">columns to exclude (optional, comma separated)</label>
						<input id="exclude_cols" type="text" name="exclude_cols" value="<?php $this->esc_html_attr( implode( ',', $this->get( 'exclude_cols' ) ) ) ?>" placeholder="eg. guid" />
					</div>
					<div class="field field-advanced field-long">
						<label for="include_cols">columns to include only (optional, comma separated)</label>
						<input id="include_cols" type="text" name="include_cols" value="<?php $this->esc_html_attr( implode( ',', $this->get( 'include_cols' ) ) ) ?>" placeholder="eg. post_content, post_excerpt" />
					</div>

				</div>

			</fieldset>

			<!-- 4. results -->
			<fieldset class="row row-results">

				<h1>actions</h1>

				<?php $this->get_errors( 'results' ); ?>

				<div class="fields">

					<span class="submit-group">
						<input type="submit" name="submit[update]" value="update details" />

						<input type="submit" name="submit[dryrun]" value="dry run" <?php if ( ! $this->db_valid() ) echo 'disabled="disabled"'; ?> class="db-required" />

						<input type="submit" name="submit[liverun]" value="live run" <?php if ( ! $this->db_valid() ) echo 'disabled="disabled"'; ?> class="db-required" />

						<span class="separator">/</span>
					</span>

					<span class="submit-group">
						<?php if ( in_array( 'InnoDB', $this->get( 'engines' ) ) ) { ?>
						<input type="submit" name="submit[innodb]" value="convert to innodb" <?php if ( ! $this->db_valid() ) echo 'disabled="disabled"'; ?> class="db-required secondary field-advanced" />
						<?php } ?>

						<input type="submit" name="submit[utf8]" value="convert to utf8 unicode" <?php if ( ! $this->db_valid() ) echo 'disabled="disabled"'; ?> class="db-required secondary field-advanced" />

					</span>

				</div>

				<?php $this->get_report(); ?>

			</fieldset>


			<!-- 5. branding -->
			<section class="row row-delete">

				<h1>delete</h1>

				<div class="fields">
					<p>
						<input type="submit" name="submit[delete]" value="delete me" />
						Once you&rsquo;re done click the <strong>delete me</strong> button to secure your server
					</p>
				</div>

			</section>

		</form>

		<section class="help">

			<h1 class="branding">interconnect/it</h1>

			<h2>Safe Search and Replace on Database with Serialized Data v3.0.0</h2>

			<p>This developer/sysadmin tool carries out search/replace functions on MySQL DBs and can handle serialised PHP Arrays and Objects.</p>

			<p><strong class="red">WARNINGS!</strong>
			Ensure data is backed up.
			We take no responsibility for any damage caused by this script or its misuse.
			DB Connection Settings are auto-filled when WordPress or Drupal is detected but can be confused by commented out settings so CHECK!
			There is NO UNDO!
			Be careful running this script on a production server.</p>

			<h3>Don't Forget to Remove Me!</h3>

			<p>Delete this utility from your
			server after use by clicking the 'delete me' button. It represents a major security threat to your database if
			maliciously used.</p>

			<p>If you have feedback or want to contribute to this script click the delete button to find out how.</p>

			<p><em>We don't put links on the search replace UI itself to avoid seeing URLs for the script in our access logs.</em></p>

			<h3>Again, use Of This Script Is Entirely At Your Own Risk</h3>

			<p>The easiest and safest way to use this script is to copy your site's files and DB to a new location.
			You then, if required, fix up your .htaccess and wp-config.php appropriately.  Once
			done, run this script, select your tables (in most cases all of them) and then
			enter the search replace strings.  You can press back in your browser to do
			this several times, as may be required in some cases.</p>

		</section>

		<?php
	}

	public function deleted() {

		// obligatory marketing!
		// seriously though it's good stuff
		?>

		<!-- 1. branding -->
		<section class="row row-branding">

			<h1><a href="http://interconnectit.com/" target="_blank">interconnect<span>/</span><strong>it</strong></a></h1>

			<?php $this->get_errors( 'delete' ); ?>

			<div class="content">
				<p>Thanks for using our search/replace tool! We&rsquo;d really appreciate it if you took a
					minute to join our mailing list and check out some of our other products.</p>
			</div>

		</section>

		<!-- 2. subscribe -->
		<section class="row row-subscribe">

			<h1>newsletter</h1>

			<form action="http://interconnectit.us2.list-manage.com/subscribe/post" method="POST" class="fields fields-small">
				<input type="hidden" name="u" value="08ec797202866aded7b2619b2">
				<input type="hidden" name="id" value="538abe0a97">

				<div id="mergeTable" class="mergeTable">

					<div class="mergeRow dojoDndItem mergeRow-email field field-short" id="mergeRow-0">
						<label for="MERGE0"><strong>email address</strong> <span class="asterisk">*</span></label>
						<input type="email" autocapitalize="off" autocorrect="off" name="MERGE0" id="MERGE0" size="25" value="">
					</div>

					<div class="mergeRow dojoDndItem mergeRow-text field field-short" id="mergeRow-1">
						<label for="MERGE1">first name</label>
						<input type="text" name="MERGE1" id="MERGE1" size="25" value="">
					</div>

					<div class="mergeRow dojoDndItem mergeRow-text field field-short" id="mergeRow-2">
						<label for="MERGE2">last name</label>
						<input type="text" name="MERGE2" id="MERGE2" size="25" value="">
					</div>

					<div class="submit_container field field-short">
						<br />
						<input type="submit" name="submit" value="subscribe">
					</div>

				</div>
			</form>

		</section>

		<!-- 3. contribute -->
		<section class="row row-contribute">

			<h1>contribute</h1>

			<div class="content">

				<p>Got suggestions? Found a bug? Want to contribute code? <a href="https://github.com/interconnectit/search-replace-db">Join us on Github!</a></p>

			</div>

		</section>

		<section class="row row-blog">

			<h1>blogs</h1>

			<div class="content">
				<p><a href="http://interconnectit.com/blog/" target="_blank">We couldn't load our blog feed for some reason so here's a link instead!</a></p>
			</div>

		</section>

		<!-- 5. products -->
		<section class="row row-products">

			<h1>products</h1>

			<div class="content">
				<p><a href="http://interconnectit.com/products/" target="_blank">We couldn't load our product feed for some reason so here's a link instead!</a></p>
			</div>

		</section>



		<?php

	}

	public function html( $body ) {

		// html classes
		$classes = array( 'no-js' );
		$classes[] = $this->regex ? 'regex-on' : 'regex-off';

		?><!DOCTYPE html>
<html class="<?php echo implode( ' ', $classes ); ?>">
	<head>
		<script>var h = document.getElementsByTagName('html')[0];h.className = h.className.replace('no-js', 'js');</script>

		<title>interconnect/it : search replace db</title>

		<?php $this->css(); ?>
		<?php $this->js(); ?>

	</head>
	<body>

		<?php $this->$body(); ?>


	</body>
</html>
		<?php
	}



	public function css() {
		?>
		<style type="text/css">
* { margin: 0; padding: 0; }

::-webkit-input-placeholder { /* WebKit browsers */
    color:    #999;
}
:-moz-placeholder { /* Mozilla Firefox 4 to 18 */
    color:    #999;
}
::-moz-placeholder { /* Mozilla Firefox 19+ */
    color:    #999;
}
:-ms-input-placeholder { /* Internet Explorer 10+ */
    color:    #999;
}

.js .hide-if-js {
	display: none;
}
.no-js .hide-if-nojs {
	display: none;
}

.regex-off .hide-if-regex-off {
	display: none;
}
.regex-on .hide-if-regex-on {
	display: none;
}

html {
	background: #fff;
	font-size: 1px;
	border-top: 20px solid #de1301;
}

body {
	font-family: 'Gill Sans MT', 'Gill Sans', Calibri, sans-serif;
	font-size: 16rem;
}

h2,
h3 {
	text-transform: uppercase;
	font-weight: normal;
	margin: 20rem 0 10rem;
}

label {
	cursor: pointer;
}

/*.row {
	background-color: rgba( 210, 0, 0, 1 );
	padding: 20px 40px;
	border: 0;
	overflow: hidden;
}
.row + .row {
	background-color: rgba( 210, 0, 0, .8 );
}
.row + .row + .row {
	background-color: rgba( 210, 0, 0, .6 );
}
.row + .row + .row + .row {
	background-color: rgba( 210, 0, 0, .4 );
}
.row + .row + .row + .row + .row {
	background-color: rgba( 210, 0, 0, .2 );
}*/

.row {
	background-color: rgba( 210, 210, 210, 1 );
	padding: 20px 40px;
	border: 0;
	overflow: hidden;
}
.row + .row {
	background-color: rgba( 210, 210, 210, .8 );
}
.row + .row + .row {
	background-color: rgba( 210, 210, 210, .6 );
}
.row + .row + .row + .row {
	background-color: rgba( 210, 210, 210, .4 );
}
.row + .row + .row + .row + .row {
	background-color: rgba( 210, 210, 210, .2 );
}

.row h1 {
	display: block;
	font-size: 40rem;
	font-weight: normal;
	margin: 15px 0 20px;
	float: left;
}
.row h1,
.branding {
	width: 260px;
	background:
		url(data:image/png;base64,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)
		no-repeat
		0 0;
	height: 40px;
	overflow: hidden;
	text-indent: -9999px;
}
h1 span {
	color: #de1301;
}

.row-db h1 {
	background-position: 0 -40px;
}
.row-tables h1 {
	background-position: 0 -80px;
}
.row-results h1 {
	background-position: 0 -120px;
}
.row-delete h1 {
	background-position: 0 -160px;
}
.row-branding h1,
.branding {
	background-position: 0 -200px;
}
.row-subscribe h1 {
	background-position: 0 -240px;
}
.row-contribute h1 {
	background-position: 0 -280px;
}
.row-blog h1 {
	background-position: 0 -320px;
}
.row-products h1 {
	background-position: 0 -360px;
}

legend, fieldset {
	/*color: #fff;*/
}

.fields, .content, .errors {
	clear: both;
	margin-top: 15px;
	margin-bottom: 15px;
	overflow: hidden;
}

.content {
	margin-top: 20px;
	margin-bottom: 20px;
}

.errors, .special-errors {
	color: #fff;
	background: #671301;
	padding: 10px;
	font-weight: bold;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	-ms-box-sizing: border-box;
	-o-box-sizing: border-box;
	box-sizing: border-box;
	border: 2px solid rgba(0,0,0,.3);
}

@media only screen and (min-width: 1000px) {

	legend, h1 {
		min-width: 250px;
		font-size: 40rem;
		margin-bottom: 10px;
	}

	.fields, .content, .errors {
		margin-left: 300px;
		clear: right;
	}

}

.fields {
	margin-right: -20px;
	margin-bottom: 5px;
}

.fields-small {
	margin-top: 0;
}

.fields-large {
	font-size: 20rem;
	margin-right: 0;
	margin-bottom: 15px;
}
.fields-large label {
	white-space: nowrap;
	margin: 10px 0;
	display: block;
}
.fields-large input[type="text"] {
	width: 100%;
}

.label-text {
	display: block;
}

@media only screen and (min-width: 1110px) {
	.label-text {
		display: inline;
	}
	.fields-large label {
		margin: 0;
		text-align: left;
		display: inline-block;
	}
	.fields-large input[type="text"] {
		width: 15em;
	}
	.regex-on .fields-large .regex-left + input[type="text"] {
		width: 12.7em;
	}
}

.field {
	float: left;
	padding-right: 20px;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	-ms-box-sizing: border-box;
	-o-box-sizing: border-box;
	box-sizing: border-box;
}
.field label {
	display: block;
}

.field-long {
	width: 100%;
}
.field-short {
	width: 100%;
}
.field-long input[type="text"],
.field-short input[type="text"],
.field-long input[type="email"],
.field-short input[type="email"] {
	width: 100%;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	-ms-box-sizing: border-box;
	-o-box-sizing: border-box;
	box-sizing: border-box;
	margin-bottom: 10px;
}
@media only screen and (min-width: 400px) {
	.field-short {
		width: 50%;
	}
}
@media only screen and (min-width: 700px) {
	.field-short {
		width: 25%;
	}
}

.description {
	font-size: 18rem;
	font-style: italic;
	color: #eee;
	margin-top: 10px;
}

input[type="text"],
input[type="email"],
.regex-left,
.regex-right {
	background: rgba(255,255,255,.7);
	border: 2px solid rgba(0,0,0,.15);
	padding: 10px 10px 10px;
	font-family: Monaco, Consolas, monospace;
	font-weight: bold;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	-ms-box-sizing: border-box;
	-o-box-sizing: border-box;
	box-sizing: border-box;
}
.regex-on .regex-left + input[type="text"] {
	padding-left: 0;
	padding-right: 0;
	border-left: 0;
	border-right: 0;
	width: 80%;
}

.regex-left {
	color: #000;
	padding-right: 0;
	border-right: 0;
	width: 1em;
}
.regex-right {
	color: #000;
	padding-left: 0;
	border-left: 0;
	width: 1em;
}


[type="submit"] {
	padding: 5px 10px 8px;
	color: #fff;
	background: #de1301 left center;
	cursor: pointer;
	border: 2px solid rgba(0,0,0,.15);
	margin-right: 20px;
	margin-bottom: 10px;
	display: inline-block;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	-ms-box-sizing: border-box;
	-o-box-sizing: border-box;
	box-sizing: border-box;
}

.separator {
	margin-right: 20px;
}

[type="submit"][disabled],
[type="submit"][disabled]:hover,
[type="submit"][disabled]:active,
[type="submit"][disabled]:focus {
	background: #999;
	color: #ccc;
	cursor: default;
	outline: none;
}

[type="submit"]:focus {
	outline: 2px solid #ab1301;
}
[type="submit"]:active,
[type="submit"].active,
[type="submit"]:active:hover,
[type="submit"].active:hover {
	outline: 2px solid #ab1301;
	background:
		#900
		url(data:image/gif;base64,R0lGODlhEAAQAPQAAJkAAP///5sGBufGxsl6evv4+O7Y2KgoKLtWVvXo6M+IiNWYmKMaGsFmZq84OOG2ttuoqAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH+GkNyZWF0ZWQgd2l0aCBhamF4bG9hZC5pbmZvACH5BAAKAAAAIf8LTkVUU0NBUEUyLjADAQAAACwAAAAAEAAQAAAFUCAgjmRpnqUwFGwhKoRgqq2YFMaRGjWA8AbZiIBbjQQ8AmmFUJEQhQGJhaKOrCksgEla+KIkYvC6SJKQOISoNSYdeIk1ayA8ExTyeR3F749CACH5BAAKAAEALAAAAAAQABAAAAVoICCKR9KMaCoaxeCoqEAkRX3AwMHWxQIIjJSAZWgUEgzBwCBAEQpMwIDwY1FHgwJCtOW2UDWYIDyqNVVkUbYr6CK+o2eUMKgWrqKhj0FrEM8jQQALPFA3MAc8CQSAMA5ZBjgqDQmHIyEAIfkEAAoAAgAsAAAAABAAEAAABWAgII4j85Ao2hRIKgrEUBQJLaSHMe8zgQo6Q8sxS7RIhILhBkgumCTZsXkACBC+0cwF2GoLLoFXREDcDlkAojBICRaFLDCOQtQKjmsQSubtDFU/NXcDBHwkaw1cKQ8MiyEAIfkEAAoAAwAsAAAAABAAEAAABVIgII5kaZ6AIJQCMRTFQKiDQx4GrBfGa4uCnAEhQuRgPwCBtwK+kCNFgjh6QlFYgGO7baJ2CxIioSDpwqNggWCGDVVGphly3BkOpXDrKfNm/4AhACH5BAAKAAQALAAAAAAQABAAAAVgICCOZGmeqEAMRTEQwskYbV0Yx7kYSIzQhtgoBxCKBDQCIOcoLBimRiFhSABYU5gIgW01pLUBYkRItAYAqrlhYiwKjiWAcDMWY8QjsCf4DewiBzQ2N1AmKlgvgCiMjSQhACH5BAAKAAUALAAAAAAQABAAAAVfICCOZGmeqEgUxUAIpkA0AMKyxkEiSZEIsJqhYAg+boUFSTAkiBiNHks3sg1ILAfBiS10gyqCg0UaFBCkwy3RYKiIYMAC+RAxiQgYsJdAjw5DN2gILzEEZgVcKYuMJiEAOwAAAAAAAAAAAA==)
		no-repeat
		8px
		center;
	padding-left: 30px;
}

.submit-group {
	display: block;
	margin: 0 0;
}

.field input[type="submit"] {
	margin-top: 2px;
}

@media only screen and (min-width: 500px) {
	.submit-group {
		white-space: nowrap;
		display: inline-block;
	}
}

input, select, textarea, button {
	font-family: inherit;
	font-size: inherit;
}

.checkboxes {
	float: none;
}

select[multiple] {
	height: 16em;
	font-family: Monaco, Consolas, monospace;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	-ms-box-sizing: border-box;
	-o-box-sizing: border-box;
	box-sizing: border-box;
	width: 100%;
	margin: 20px 0 0;
}
select[multiple] option {
	padding: 5px;
}

.checkboxes ul {
	list-style: none;
	max-height: 260px;
	-webkit-columns: 16em 3;
	-webkit-column-gap: 2em;
	overflow: auto;
	padding-bottom: 1em;
}
.checkboxes li {
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
}
input[type="checkbox"],
input[type="radio"] {
	vertical-align: middle;
}


.report {
	color: #000;
	background: #fff;
	margin-top: 20px;
	margin-bottom: 20px;
	padding: 10px;
}

.report ul {
	list-style: none;
	margin: 10px 0;
	display: table;
}
.report li {
	margin: 0;
	display: table-row;
	padding: 10px 0;
}
.report li>strong {
	display: table-cell;
	padding-right: 20px;
}
.report li>span {
	display: table-cell;
	padding-right: 20px;
	white-space: nowrap;
}

.report tbody tr:nth-child(2n-1) {
	background: rgba(0,0,0,.1);
}

.report table {
	width: 100%;
	border-collapse: collapse;
}

.report th,
.report td {
	text-align: left;
	padding: 5px;
}


.changes-overlay {
	background: #fff;
	position: fixed;
	left: 0; top: 0;
	right: 0; bottom: 0;
	overflow: auto;
}

.changes-overlay .overlay-header {
	overflow: hidden;
}

.changes-overlay .close {
	float: right;
	margin: 40px 20px;
	color: #c00;
	text-decoration: none;
	font-weight: bold;
	font-size: 24rem;
}

.changes-overlay h1 {
	margin: 20px;
	float: none;
	font-weight: normal;
}

.changes-overlay .changes {
	margin: 0 0;
	overflow: auto;
	position: absolute;
	top: 100px; right: 0;
	left: 0; bottom: 0;
	clear: both;
	width: 100%;
}

.highlight {
	background: #ffa;
}

.diff-wrap {
	margin: 20px;
	background: #f3f3f3;
	overflow: hidden;
}

.diff-wrap h3 {
	font-size: 16rem;
	font-weight: bold;
	margin: 10px 10px 10px;
}

.diff {
	overflow: auto;
	margin: 5px;
}

.diff pre {
	float: left;
	width: 50%;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	-ms-box-sizing: border-box;
	-o-box-sizing: border-box;
	box-sizing: border-box;
	padding: 5px;
	background: #fff;
	white-space: normal;
	word-break: break-all;
	font-size: 13rem;
}

.diff .from {
	border-right: 5px solid #f3f3f3;
}

.diff .to {
	border-left: 5px solid #f3f3f3;
}

a {
	color: #d00;
}

h1 a {
	text-decoration: none;
	color: inherit;
}

.help {
	margin: 40px;
	color: #999;
	max-width: 640px;
}

.help a {
	color: inherit;
}

.help p {
	margin: 10px 0;
}

.red {
	color: #c00;
}


.row-branding .content {
	font-size: 24rem;
}

.row-blogs {

}

.blog {
	margin-bottom: 15px;
}

.blog a {
	display: block;
	text-decoration: none;
}

.blog a:hover h2 {
	text-decoration: underline;
}

.blog h2 {
	margin: 0;
	line-height: 1.2;
}

.blog div {
	display: inline-block;
	color: #333;
	font-size: 14rem;
}
.blog .categories {
	font-style: italic;
	margin-left: 20px;
}

.row-products .content {
	overflow: hidden;
	margin-right: -20px;
}

.product {
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	-ms-box-sizing: border-box;
	-o-box-sizing: border-box;
	box-sizing: border-box;
	padding-right: 20px;
	margin-bottom: 20px;
}
@media only screen and (min-width: 600px) {
	.product {
		float: left;
		width: 100%;
		width: 50%;
	}
}
@media only screen and (min-width: 1200px) {
	.product {
		width: 33.33333%;
	}
}

.product-thumb {
	height: 100px;
	overflow: hidden;
}

.product img {
	max-width: 100%;
	height: auto;
}

.product a {
	display: block;
	text-decoration: none;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	-ms-box-sizing: border-box;
	-o-box-sizing: border-box;
	box-sizing: border-box;
	padding: 20px 20px 40px 20px;
	border: 2px solid rgba(0,0,0,.1);
	background: #fff;
	min-height: 380px;
}
.product a:hover {
	border: 2px solid rgba(0,0,0,.3);
}

.product-description {
	color: #000;
	line-height: 1.2;
}

.product-description p {
	margin-bottom: 10px;
}

.product-description li {
	list-style: none;
	font-style: italic;
	margin: 5px 0;
}

		</style>
		<?php
	}

	public function js() {
		?>
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
		<script>

// patch console free browsers
window.console = window.console || { log: function(){} };

;(function($){

	var srdb;

	srdb = function() {

		var t = this,
			dom = $( 'html' );

		$.extend( t, {

			errors: {},
			report: {},
			info: {},
			prev_data: {},
			tables: 0,
			rows: 0,
			changes: 0,
			updates: 0,
			time: 0.0,
			button: false,

			// constructor
			init: function() {

				// search replace ui
				if ( $( '.row-db' ).length ) {

					// show/hide tables
					dom.on( 'click', '[name="use_tables"]', t.toggle_tables );
					dom.find( '[name="use_tables"][checked]' ).click();

					// toggle regex mode
					dom.on( 'click', '[name="regex"]', t.toggle_regex );
					dom.find( '[name="regex"][checked]' ).click();

					// ajax form
					dom.on( 'submit', 'form', t.submit_proxy );
					dom.on( 'click', '[type="submit"]', t.submit );

				// deleted ui
				} else {

					// mailchimp
					dom.on( 'submit', 'form[action*="list-manage.com"]', t.mailchimp );

					// fetch blog feed
					t.fetch_blogs();

					// fetch product feed
					t.fetch_products();

				}

			},

			report_tpl: '\
				<p class="main-report">\
				In the process of <span data-report="search_replace"></span> we scanned\
				<strong data-report="tables"></strong> tables with a total of\
				<strong data-report="rows"></strong> rows,\
				<strong data-report="changes"></strong> cells\
				<span data-report="dry_run"></span> changed.\
				<strong data-report="updates"></strong> db updates were performed.\
				It all took <strong data-report="time"></strong> seconds.\
				</p>',
			table_report_tpl: '\
				<th data-report="table"></th>\
				<td data-report="rows"></td>\
				<td data-report="changes"></td>\
				<td data-report="updates"></td>\
				<td data-report="time"></td>',
			table_report_head_tpl: '',

			strings_dry: {
				search_replace: 'searching for <strong>&ldquo;<span data-report="search"></span>&rdquo;</strong>\
								(to be replaced by <strong>&ldquo;<span data-report="replace"></span>&rdquo;</strong>)',
				updates: 'would have been'
			},
			strings_live: {
				search_replace: 'replacing <strong data-report="search"></strong> with\
								<strong data-report="replace"></strong>',
				updates: 'were'
			},

			toggle_tables: function() {
				if ( this.id == 'all_tables' ) {
					dom.find( '.table-select' ).slideUp( 400 );
				} else {
					dom.find( '.table-select' ).slideDown( 400 );
				}
			},

			toggle_regex: function() {
				if ( $( this ).is( ':checked' ) )
					dom.removeClass( 'regex-off' ).addClass( 'regex-on' );
				else
					dom.removeClass( 'regex-on' ).addClass( 'regex-off' );
			},

			reset: function() {
				t.errors = {};
				t.report = {};
				t.tables = 0;
				t.rows = 0;
				t.changes = 0;
				t.updates = 0;
				t.time = 0.0;
			},

			map_form_data: function( $form ) {
				var data_temp = $form.serializeArray(),
					data = {};
				$.map( data_temp, function( field, i ) {
					if ( data[ field.name ] ) {
						if ( ! $.isArray( data[ field.name ] ) )
							data[ field.name ] = [ data[ field.name ] ];
						data[ field.name ].push( field.value );
					}
					else {
						if ( field.value === '1' )
							field.value = true;
						data[ field.name ] = field.value;
					}
				} );
				return data;
			},

			submit_proxy: function( e ) {
				if ( t.button !== 'submit[delete]' )
					return false;
				return true;
			},

			submit: function( e ) {

				// workaround for submission not coming from a button click
				var $button = $( this ),
					$form = $( this ).parents( 'form' ),
					submit = $button.attr( 'name' ),
					$feedback = $( '.errors, .report' ),
					feedback_length = $feedback.length;

				// track button clicked
				t.button = submit;

				// add spinner
				$button.addClass( 'active' );

				if ( submit == 'submit[delete]' ) {
					$( '[type="submit"]' ).not( $button ).attr( 'disabled', 'disabled' );
					return true;
				}

				if ( submit == 'submit[liverun]' && ! window.confirm( 'Are you absolutely ready to run search/replace? Make sure you have backed up your database!' ) )
					return false;

				if ( ( submit == 'submit[innodb]' || submit == 'submit[utf8]' ) && ! window.confirm( 'Are you absolutely ready to modify the tables? Make sure you have backed up your database!' ) )
					return false;

				// disable buttons & add spinner
				$( '[type="submit"]' ).attr( 'disabled', 'disabled' );

				// stop normal submission
				e.preventDefault();

				// reset reports
				t.reset();

				// get form data as an object
				data = t.map_form_data( $form );

				// use all tables if none selected
				if ( dom.find( '#all_tables' ).is( ':checked' ) || ! data[ 'tables[]' ] || ! data[ 'tables[]' ].length )
					data[ 'tables[]' ] = $.map( $( 'select[name^="tables"] option' ), function( el, i ) { return $( el ).attr( 'value' ); } );

				// check we don't just have one table selected as we get a string not array
				if ( ! $.isArray( data[ 'tables[]' ] ) )
					data[ 'tables[]' ] = [ data[ 'tables[]' ] ];

				// add in ajax and submit params
				data = $.extend( {
					ajax: true,
					submit: submit
				}, data );

				// clear previous errors
				if ( feedback_length ) {
					$feedback.each( function( i ) {
						$( this ).fadeOut( 200, function() {
							$( this ).remove();

							// start recursive table post
							if ( i+1 == feedback_length )
								t.recursive_fetch_json( data, 0 );
						} );
					} );
				} else {
					// start recursive table post
					t.recursive_fetch_json( data, 0 );
				}

				return false;
			},

			complete: function() {
				// remove spinner
				$( '[type="submit"]' )
					.removeClass( 'active' )
					.not( '.db-required' )
					.removeAttr( 'disabled' );
				if ( typeof t.errors.db != 'undefined' && ! t.errors.db.length )
					$( '[type="submit"].db-required' ).removeAttr( 'disabled' );
			},

			recursive_fetch_json: function( data, i ) {

				// break from loop
				if ( data[ 'tables[]' ].length && typeof data[ 'tables[]' ][ i ] == 'undefined' ) {
					t.complete();
					return false;
				}

				// clone data
				var post_data = $.extend( true, {}, data ),
					dry_run = data.submit != 'submit[liverun]',
					strings = dry_run ? t.strings_dry : t.strings_live,
					result = true,
					start = Date.now() / 1000,
					end = start;

				// remap values so we just do one table at a time
				post_data[ 'tables[]' ] = [ data[ 'tables[]' ][ i ] ];
				post_data.use_tables = 'subset';

				return $.post( window.location.href, post_data, function( response ) {

					if ( response ) {

						var errors = response.errors,
							report = response.report,
							info   = response.info;

						// append errors
						$.each( errors, function( type, error_list ) {

							if ( ! error_list.length ) {
								if ( type == 'db' ) {
									$( '[name="use_tables"]' ).removeAttr( 'disabled' );
									// update the table dropdown if we're changing db
									if ( $( '.table-select' ).html() == '' || ( t.prev_data.name && t.prev_data.name !== data.name ) )
										$( '.table-select' ).html( info.table_select );
									// add/remove innodb button if innodb is available or not
									if ( $.inArray( 'InnoDB', info.engines ) >= 0 && ! $( '[name="submit\[innodb\]"]' ).length )
										$( '[name="submit\[utf8\]"]' ).before( '<input type="submit" name="submit[innodb]" value="convert to innodb" class="db-required secondary field-advanced" />' );
								}
								return;
							}

							var $row = $( '.row-' + type ),
								$errors = $row.find( '.errors' );

							if ( ! $errors.length ) {
								$errors = $( '<div class="errors"></div>' ).hide().insertAfter( $( 'legend,h1', $row ) );
								$errors.fadeIn( 200 );
							}

							$.each( error_list, function( i, error ) {
								if ( ! t.errors[ type ] || $.inArray( error, t.errors[ type ] ) < 0 )
									$( '<p>' + error + '</p>' ).hide().appendTo( $errors ).fadeIn( 200 );
							} );

							if ( type == 'db' ) {
								$( '[name="use_tables"]' ).eq(0).click().end().attr( 'disabled', 'disabled' );
								$( '.table-select' ).html( '' );
								$( '[name="submit\[innodb\]"]' ).remove();
							}

						} );

						// scroll back to top most errors block
						//if ( t.errors !== errors && $( '.errors' ).length && $( '.errors' ).eq( 0 ).offset().top < $( 'body' ).scrollTop() )
						//	$( 'html,body' ).animate( { scrollTop: $( '.errors' ).eq(0).offset().top }, 300 );

						// track errors
						$.extend( true, t.errors, errors );

						// track info
						$.extend( true, t.info, info );

						// append reports
						if ( report.tables ) {

							var $row = $( '.row-results' ),
								$report = $row.find( '.report' ),
								$table_reports = $row.find( '.table-reports' );

							if ( ! $report.length )
								$report = $( '<div class="report"></div>' ).appendTo( $row );

							end = Date.now() / 1000;

							t.tables += report.tables;
							t.rows += report.rows;
							t.changes += report.change;
							t.updates += report.updates;
							t.time += t.get_time( start, end );

							if ( ! $report.find( '.main-report' ).length ) {
								$( t.report_tpl )
									.find( '[data-report="search_replace"]' ).html( strings.search_replace ).end()
									.find( '[data-report="search"]' ).html( data.search ).end()
									.find( '[data-report="replace"]' ).html( data.replace ).end()
									.find( '[data-report="dry_run"]' ).html( strings.updates ).end()
									.prependTo( $report );
							}

							$( '.main-report' )
								.find( '[data-report="tables"]' ).html( t.tables ).end()
								.find( '[data-report="rows"]' ).html( t.rows ).end()
								.find( '[data-report="changes"]' ).html( t.changes ).end()
								.find( '[data-report="updates"]' ).html( t.updates ).end()
								.find( '[data-report="time"]' ).html( t.time.toFixed( 7 ) ).end();

							if ( ! $table_reports.length )
								$table_reports = $( '\
									<table class="table-reports">\
										<thead>\
											<tr>\
												<th>Table</th>\
												<th>Rows</th>\
												<th>Cells changed</th>\
												<th>Updates</th>\
												<th>Seconds</th>\
											</tr>\
										</thead>\
										<tbody></tbody>\
									</table>' ).appendTo( $report );

							$.each( report.table_reports, function( table, table_report ) {

								var $view_changes = '',
									changes_length = table_report.changes.length;

								if ( changes_length ) {
									$view_changes = $( '<a href="#" title="View the first ' + changes_length + ' modifications">view changes</a>' )
										.data( 'report', table_report )
										.data( 'table', table )
										.click( t.changes_overlay );
								}

								$( '<tr class="' + table + '">' + t.table_report_tpl + '</tr>' )
									.hide()
									.find( '[data-report="table"]' ).html( table ).end()
									.find( '[data-report="rows"]' ).html( table_report.rows ).end()
									.find( '[data-report="changes"]' ).html( table_report.change + ' ' ).append( $view_changes ).end()
									.find( '[data-report="updates"]' ).html( table_report.updates ).end()
									.find( '[data-report="time"]' ).html( t.get_time( start, end ).toFixed( 7 ) ).end()
									.prependTo( $table_reports.find( 'tbody' ) )
									.fadeIn( 150 );

							} );

							$.extend( true, t.report, report );

							// fetch next table
							t.recursive_fetch_json( data, ++i );

						} else if ( report.engine ) {

							var $row = $( '.row-results' ),
								$report = $row.find( '.report' ),
								$table_reports = $row.find( '.table-reports' );

							if ( ! $report.length )
								$report = $( '<div class="report"></div>' ).appendTo( $row );

							if ( ! $table_reports.length )
								$table_reports = $( '\
									<table class="table-reports">\
										<thead>\
											<tr>\
												<th>Table</th>\
												<th>Engine</th>\
											</tr>\
										</thead>\
										<tbody></tbody>\
									</table>' ).appendTo( $report );

							$.each( report.converted, function( table, converted ) {

								$( '<tr class="' + table + '"><td>' + table + '</td><td>' + report.engine + '</td></tr>' )
									.hide()
									.prependTo( $table_reports.find( 'tbody' ) )
									.fadeIn( 150 );

								$( '.table-select option[value="' + table + '"]' ).html( function(){
									return $( this ).html().replace( new RegExp( table + ': [^,]+' ), table + ': ' + report.engine );
								} );

							} );

							// fetch next table
							t.recursive_fetch_json( data, ++i );

						} else if ( report.collation ) {

							var $row = $( '.row-results' ),
								$report = $row.find( '.report' ),
								$table_reports = $row.find( '.table-reports' );

							if ( ! $report.length )
								$report = $( '<div class="report"></div>' ).appendTo( $row );

							if ( ! $table_reports.length )
								$table_reports = $( '\
									<table class="table-reports">\
										<thead>\
											<tr>\
												<th>Table</th>\
												<th>Charset</th>\
												<th>Collation</th>\
											</tr>\
										</thead>\
										<tbody></tbody>\
									</table>' ).appendTo( $report );

							$.each( report.converted, function( table, converted ) {

								$( '\
											<tr class="' + table + '">\
												<td>' + table + '</td>\
												<td>' + report.collation.replace( /^([^_]+).*$/, '$1' ) + '</td>\
												<td>' + report.collation + '</td>\
											</tr>' )
									.hide()
									.prependTo( $table_reports.find( 'tbody' ) )
									.fadeIn( 150 );

								$( '.table-select option[value="' + table + '"]' ).html( function(){
									return $( this ).html().replace( new RegExp( 'collation: .*?$' ), 'collation: ' + report.collation );
								} );

							} );

							// fetch next table
							t.recursive_fetch_json( data, ++i );

						} else {

							console.log( 'no report' );
							t.complete();

						}

					} else {

						console.log( 'no response' );
						t.complete();

					}

					// remember previous request
					t.prev_data = $.extend( {}, data );

					return true;

				}, 'json' );

			},

			get_time: function( start, end ) {
				start 	= start || 0.0;
				end 	= end 	|| 0.0;
				start 	= parseFloat( start );
				end 	= parseFloat( end );
				var diff = end - start;
				return parseFloat( diff < 0.0 ? 0.0 : diff );
			},

			changes_overlay: function( e ) {
				e.preventDefault();

				var $overlay = $( '.changes-overlay' ),
					table = $( this ).data( 'table' ),
					report = $( this ).data( 'report' )
					changes = report.changes,
					search = $( '[name="search"]' ).val(),
					replace = $( '[name="replace"]' ).val(),
					regex = $( '[name="regex"]' ).is( ':checked' ),
					regex_i = $( '[name="regex_i"]' ).is( ':checked' ),
					regex_m = $( '[name="regex_m"]' ).is( ':checked' ),
					regex_search = new RegExp( search, 'g' + ( regex_i ? 'i' : '' ) + ( regex_m ? 'm' : '' ) );

				if ( ! $overlay.length ) {
					$overlay = $( '<div class="changes-overlay"><div class="overlay-header"><a class="close" href="#close">&times; Close</a><h1></h1></div><div class="changes"></div></div>' )
						.hide()
						.find( '.close' )
							.click( function( e ) {
								e.preventDefault();
								$overlay.fadeOut( 300 );
								$( 'body' ).css( { overflow: 'auto' } );
							} )
							.end()
						.appendTo( $( 'body' ) );
				}

				$( 'body' ).css( { overflow: 'hidden' } );

				$overlay
					.find( 'h1' ).html( table ).end()
					.find( '.changes' ).html( '' ).end()
					.fadeIn( 300 )
					.find( '.changes' ).html( function() {
							var $changes = $( this );
							$.each( changes, function( i, item ) {
								if ( i >= 20 )
									return false;
								var match_search,
									match_replace,
									text,
									$change = $( '\
										<div class="diff-wrap">\
											<h3>row ' + item.row + ', column `' + item.column + '`</h3>\
											<div class="diff">\
												<pre class="from"></pre>\
												<pre class="to"></pre>\
											</div>\
										</div>' )
									.find( '.from' ).text( item.from ).end()
									.find( '.to' ).text( item.to ).end()
									.appendTo( $changes );
								if ( regex ) {
									text = $change.find( '.from' ).html();
									match_search = text.match( regex_search );
									$.each( match_search, function( i, match ) {
										match_replace = match.replace( regex_search, replace );
										$change.html( $change.html().replace( new RegExp( match, 'g' ), '<span class="highlight">' + match + '</span>' ) );
										$change.html( $change.html().replace( new RegExp( match_replace, 'g' ), '<span class="highlight">' + match_replace + '</span>' ) );
									} );
								} else {
									$change.html( $change.html().replace( new RegExp( search, 'g' ), '<span class="highlight">' + search + '</span>' ) );
									$change.html( $change.html().replace( new RegExp( replace, 'g' ), '<span class="highlight">' + replace + '</span>' ) );
								}
								return true;
							} );
						} ).end();

			},

			fetch_products: function() {

				// fetch products feed from interconnectit.com
				var $products,
					tpl = '\
						<div class="product">\
							<a href="{{custom_fields.link}}" title="Link opens in new tab" target="_blank">\
								<div class="product-thumb"><img src="{{attachments[0].url}}" alt="{{title_plain}}" /></div>\
								<h2>{{title}}</h2>\
								<div class="product-description">{{content}}</div>\
							</a>\
						</div>';

				// get products as jsonp
				$.ajax( {
					type: 'GET',
					url: 'http://products.network.interconnectit.com/api/core/get_posts/',
					data: { order: 'ASC', orderby: 'menu_order title' },
					dataType: 'jsonp',
					jsonpCallback: 'show_products',
					contentType: 'application/json',
					success: function( products ) {
						$products = $( '.row-products .content' ).html( '' );
						$.each( products.posts, function( i, product ) {

							// run template replacement
							$products.append( tpl.replace( /{{([a-z\.\[\]0-9_]+)}}/g, function( match, p1, offset, search ) {
								return typeof eval( 'product.' + p1 ) != 'undefined' ? eval( 'product.' + p1 ) : '';
							} ) );

						} );
					},
					error: function(e) {

					}
				} );

			},

			fetch_blogs: function() {

				// fetch products feed from interconnectit.com
				var $blogs,
					tpl = '\
						<div class="blog">\
							<a href="{{url}}" title="Link opens in new tab" target="_blank">\
								<h2>{{title}}</h2>\
								<div class="date">{{date}}</div>\
								<div class="categories">Filed under: {{categories}}</div>\
							</a>\
						</div>';

				// get products as jsonp
				$.ajax( {
					type: 'GET',
					url: 'http://interconnectit.com/api/core/get_posts/',
					data: { count: 3, category__not_in: [ 216 ] },
					dataType: 'jsonp',
					jsonpCallback: 'show_blogs',
					contentType: 'application/json',
					success: function( blogs ) {
						$blogs = $( '.row-blog .content' ).html( '' );
						$.each( blogs.posts, function( i, blog ) {

							// run template replacement
							$blogs.append( tpl.replace( /{{([a-z\.\[\]0-9_]+)}}/g, function( match, p1, offset, search ) {
								var value = typeof eval( 'blog.' + p1 ) != 'undefined' ? eval( 'blog.' + p1 ) : '';
								if ( p1 == 'date' )
									value = new Date( value ).toDateString();
								if ( p1 == 'categories' )
									value = $.map( value, function( category, i ){ return category.title; } ).join( ', ' );
								return value;
							} ) );

						} );
					},
					error: function(e) {

					}
				} );

			},

			mailchimp: function( e ) {
				e.preventDefault();

				var $this = $( this ),
					$form = $this.is( 'form' ) ? $this : $this.parents( 'form' ),
					$button = $form.find( 'input[type="submit"]' ).addClass( 'active' ),
					action = $form.attr( 'action' ).replace( /subscribe\/post$/, 'subscribe/post-json' );

				// remove errors
				$( '.row-subscribe .errors' ).remove();

				// get response from mailchimp
				$.ajax( {
					type: 'GET',
					url: action,
					data: $form.serialize() + '&c=?',
					dataType: 'json',
					success: function( response ) {

						if ( response && response.result == 'success' ) {
							$form.find( '>*' ).fadeOut( 150, function() {
								$form.html( '' );
								$( '<div class="content"><p class="thanks">Success! We didn&rsquo;t think it was possible but now we like you even more!</p></div>' )
									.hide()
									.insertAfter( $form )
									.fadeIn( 300 );
								$form.remove();
							} );
						}

						if ( response && response.result != 'success' ) {

							$( '<div class="errors"><p>Computer says no&hellip; Can you check you&rsquo;ve filled in the email address field correctly?</p></div>' )
								.hide()
								.insertAfter( '.row-subscribe h1' )
								.fadeIn( 200 );

						}
					},
					complete: function() {
						$button.removeClass( 'active' );
					}
				} );

			}

		} );

		// constructor
		t.init();

		return t;
	}

	// load on ready
	$( document ).ready( srdb );

})(jQuery);

		</script>
		<?php
	}

}

// initialise
new icit_srdb_ui();

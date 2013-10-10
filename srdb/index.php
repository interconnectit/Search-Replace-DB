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
 * 		* UI completely redesigned
 * 		* Removed all links from script until 'delete' has been clicked to avoid
 * 		  security risk from our access logs
 * 		* Search replace functionality moved to it's own separate class
 * 		* Replacements done table by table to avoid timeouts
 * 		* Convert tables to InnoDB
 * 		* Convert tables to utf8_unicode_ci
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

		// discover environment
		if ( $this->is_wordpress() ) {

			// populate db details
			$name 		= DB_NAME;
			$user 		= DB_USER;
			$pass 		= DB_PASSWORD;
			$host 		= DB_HOST;
			$charset 	= DB_CHARSET;
			$collate 	= DB_COLLATE;

		} elseif( ! isset( $_POST ) && $this->is_drupal() ) {

			// populate db details
			//$name 	= DB_NAME;
			//$user 	= DB_USER;
			//$pass 	= DB_PASSWORD;
			//$host 	= DB_HOST;
			//$charset 	= DB_CHARSET;
			//$collate 	= DB_COLLATE;

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

		error_log( $show );

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
				}

				$html = 'deleted';

				break;

			case 'liverun':

				// increase time out limit
				@set_time_limit( 60 * 10 );

				// try to push the allowed memory up, while we're at it
				@ini_set( 'memory_limit', '1024M' );

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
					$this->add_error( 'Could not bootstrap WordPress environment. There might be a PHP error, possibly caused by changes to the database', 'db' );

				}

				if ( $db_details )
					return true;

			}

		}

		return false;
	}


	public function is_drupal( $version = 7 ) {

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

			// require the bootstrap include
			require_once( dirname( __FILE__ ) . "{$path_mod}/{$bootstrap_file}" );

			// load drupal
			drupal_bootstrap( DRUPAL_BOOTSTRAP_FULL );

			// confirm environment
			$this->set( 'is_drupal', true );

			$database = Database::getConnectionOptions();

			error_log( var_export( $database, true ) );

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


	// ajax



	//



	/**
	 * Create a submit button with a JS confirm popup if there is need.
	 *
	 * @param string $text    Button string.
	 * @param string $warning Submit warning pop up text.
	 *
	 * @return null
	 */
	public function submit_button( $text = 'Submit', $warning = '' ) {
		$warning = str_replace( "'", "\'", $warning ); ?>
		<input type="submit" class="button" value="<?php echo htmlentities( $text, ENT_QUOTES, 'UTF-8' ); ?>" <?php echo ! empty( $warning ) ? 'onclick="if (confirm(\'' . htmlentities( $warning, ENT_QUOTES, 'UTF-8' ) . '\')){return true;}return false;"' : ''; ?>/> <?php
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
				if ( $table[ 'Comment' ] == 'VIEW' ) {
					$table_select .= '<option value="' . $this->esc_html_attr( $table[ 0 ], false ) . '" ' . $this->selected( true, in_array( $table[ 0 ], $this->tables ), false ) . '>' . $table[0] . ': VIEW</option>';
				} else {
					$table_select .= '<option value="' . $this->esc_html_attr( $table[ 0 ], false ) . '" ' . $this->selected( true, in_array( $table[ 0 ], $this->tables ), false ) . '>' . "{$table[0]}: {$table['Engine']}, rows: {$table['Rows']}, size: {$size}, collation: {$table['Collation']}" . '</option>';
				}
			}
			$table_select .= '</select>';
		}

		if ( $echo )
			echo $table_select;
		return $table_select;
	}


	public function css() {
		?>
		<link rel="stylesheet" type="text/css" href="style.css" media="screen" />
		<?php
	}

	public function js() {
		?>
		<script src="jquery.js"></script>
		<script src="srdb.js"></script>
		<?php
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

			<h2>Safe Search and Replace on Database with Serialized Data v2.1.2</h2>

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

}

// initialise
new icit_srdb_ui();

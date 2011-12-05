<?php
/**
 *
 * Safe Search and Replace on Database with Serialized Data v2.0.1
 *
 * This script is to solve the problem of doing database search and replace when
 * developers have only gone and used the non-relational concept of serializing
 * PHP arrays into single database columns.  It will search for all matching
 * data on the database and change it, even if it's within a serialized PHP
 * array.
 *
 * The big problem with serialised arrays is that if you do a normal DB style
 * search and replace the lengths get mucked up.  This search deals with the
 * problem by unserializing and reserializing the entire contents of the
 * database you're working on.  It then carries out a search and replace on the
 * data it finds, and dumps it back to the database.  So far it appears to work
 * very well.  It was coded for our WordPress work where we often have to move
 * large databases across servers, but I designed it to work with any database.
 * Biggest worry for you is that you may not want to do a search and replace on
 * every damn table - well, if you want, simply add some exclusions in the table
 * loop and you'll be fine.  If you don't know how, you possibly shouldn't be
 * using this script anyway.
 *
 * To use, simply configure the settings below and off you go.  I wouldn't
 * expect the script to take more than a few seconds on most machines.
 *
 * BIG WARNING!  Take a backup first, and carefully test the results of this
 * code. If you don't, and you vape your data then you only have yourself to
 * blame. Seriously.  And if you're English is bad and you don't fully
 * understand the instructions then STOP. Right there. Yes. Before you do any
 * damage.
 *
 * USE OF THIS SCRIPT IS ENTIRELY AT YOUR OWN RISK. I/We accept no liability
 * from its use.
 *
 * First Written 2009-05-25 by David Coveney of Interconnect IT Ltd (UK)
 * http://www.davidcoveney.com or http://www.interconnectit.com
 * and released under the WTFPL
 * ie, do what ever you want with the code, and we take no responsibility for it
 * OK? If you don't wish to take responsibility, hire us at Interconnect IT Ltd
 * on +44 (0)151 331 5140 and we will do the work for you, but at a cost,
 * minimum 1hr
 *
 * To view the WTFPL go to http://sam.zoy.org/wtfpl/ (WARNING: it's a little
 * rude, if you're sensitive);
 *
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


/**
 * Form action attribute.
 *
 * @return null
 */
function icit_srdb_form_action( ) {
	global $step;
	echo basename( __FILE__ ) . '?step=' . intval( $step + 1 );
}


/**
 * Used to check the $_post tables array and remove any that don't exist.
 *
 * @param array $table The list of tables from the $_post var to be checked.
 *
 * @return array	Same array as passed in but with any tables that don'e exist removed.
 */
function check_table_array( $table = '' ){
	global $all_tables;
	return in_array( $table, $all_tables );
}


/**
 * Simply create a submit button with a JS confirm popup if there is need.
 *
 * @param string $text    Button string.
 * @param string $warning Submit warning pop up text.
 *
 * @return null
 */
function icit_srdb_submit( $text = 'Submit', $warning = '' ){
	$warning = str_replace( "'", "\'", $warning ); ?>
	<input type="submit" class="button" value="<?php echo htmlentities( $text, ENT_QUOTES, 'UTF-8' ); ?>" <?php echo ! empty( $warning ) ? 'onclick="if (confirm(\'' . htmlentities( $warning, ENT_QUOTES, 'UTF-8' ) . '\')){return true;}return false;"' : ''; ?>/> <?php
}


/**
 * Simple html esc
 *
 * @param string $string Thing that needs escaping
 * @param bool $echo   Do we echo or return?
 *
 * @return string    Escaped string.
 */
function esc_html_attr( $string = '', $echo = false ){
	$output = htmlentities( $string, ENT_QUOTES, 'UTF-8' );
	if ( $echo )
		echo $output;
	else
		return $output;
}


/**
 * Walk and array replacing one element for another. ( NOT USED ANY MORE )
 *
 * @param string $find    The string we want to replace.
 * @param string $replace What we'll be replacing it with.
 * @param array $data    Used to pass any subordinate arrays back to the
 * function for searching.
 *
 * @return array    The original array with the replacements made.
 */
function recursive_array_replace( $find, $replace, $data ) {
    if ( is_array( $data ) ) {
        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                recursive_array_replace( $find, $replace, $data[ $key ] );
            } else {
                // have to check if it's string to ensure no switching to string for booleans/numbers/nulls - don't need any nasty conversions
                if ( is_string( $value ) )
					$data[ $key ] = str_replace( $find, $replace, $value );
            }
        }
    } else {
        if ( is_string( $data ) )
			$data = str_replace( $find, $replace, $data );
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
function recursive_unserialize_replace( $from = '', $to = '', $data = '', $serialised = false ) {

	// some unseriliased data cannot be re-serialised eg. SimpleXMLElements
	try {

		if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {
			$data = recursive_unserialize_replace( $from, $to, $unserialized, true );
		}

		elseif ( is_array( $data ) ) {
			$_tmp = array( );
			foreach ( $data as $key => $value ) {
				$_tmp[ $key ] = recursive_unserialize_replace( $from, $to, $value, false );
			}

			$data = $_tmp;
			unset( $_tmp );
		}

		else {
			if ( is_string( $data ) )
				$data = str_replace( $from, $to, $data );
		}

		if ( $serialised )
			return serialize( $data );

	} catch( Exception $error ) {

	}

	return $data;
}


/**
 * Is the string we're dealing with a serialised string? ( NOT USED ANY MORE )
 *
 * @param string $data The string we want to check
 *
 * @return bool    true if serialised.
 */
function is_serialized_string( $data ) {
	// if it isn't a string, it isn't a serialized string
	if ( !is_string( $data ) )
		return false;
	$data = trim( $data );
	if ( preg_match( '/^s:[0-9]+:.*;$/s', $data ) ) // this should fetch all serialized strings
		return true;
	return false;
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
function icit_srdb_replacer( $connection, $search = '', $replace = '', $tables = array( ) ) {
	global $guid, $exclude_cols;

	$report = array( 'tables' => 0,
					 'rows' => 0,
					 'change' => 0,
					 'updates' => 0,
					 'start' => microtime( ),
					 'end' => microtime( ),
					 'errors' => array( ),
					 );

	if ( is_array( $tables ) && ! empty( $tables ) ) {
		foreach( $tables as $table ) {
			$report[ 'tables' ]++;

			$columns = array( );

			// Get a list of columns in this table
		    $fields = mysql_query( 'DESCRIBE ' . $table, $connection );
			while( $column = mysql_fetch_array( $fields ) )
				$columns[ $column[ 'Field' ] ] = $column[ 'Key' ] == 'PRI' ? true : false;

			// Count the number of rows we have in the table if large we'll split into blocks, This is a mod from Simon Wheatley
			$row_count = mysql_query( 'SELECT COUNT(*) FROM ' . $table, $connection );
			$rows_result = mysql_fetch_array( $row_count );
			$row_count = $rows_result[ 0 ];
			if ( $row_count == 0 )
				continue;

			$page_size = 50000;
			$pages = ceil( $row_count / $page_size );

			for( $page = 0; $page < $pages; $page++ ) {

				$current_row = 0;
				$start = $page * $page_size;
				$end = $start + $page_size;
				// Grab the content of the table
				$data = mysql_query( sprintf( 'SELECT * FROM %s LIMIT %d, %d', $table, $start, $end ), $connection );

				if ( ! $data )
					$report[ 'errors' ][] = mysql_error( );

				while ( $row = mysql_fetch_array( $data ) ) {

					$report[ 'rows' ]++; // Increment the row counter
					$current_row++;

					$update_sql = array( );
					$where_sql = array( );
					$upd = false;

					foreach( $columns as $column => $primary_key ) {
						if ( $guid == 1 && in_array( $column, $exclude_cols ) )
							continue;

						$edited_data = $data_to_fix = $row[ $column ];

						// Run a search replace on the data that'll respect the serialisation.
						$edited_data = recursive_unserialize_replace( $search, $replace, $data_to_fix );

						// Something was changed
						if ( $edited_data != $data_to_fix ) {
							$report[ 'change' ]++;
							$update_sql[] = $column . ' = "' . mysql_real_escape_string( $edited_data ) . '"';
							$upd = true;
						}

						if ( $primary_key )
							$where_sql[] = $column . ' = "' . mysql_real_escape_string( $data_to_fix ) . '"';
					}

					if ( $upd && ! empty( $where_sql ) ) {
						$sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $update_sql ) . ' WHERE ' . implode( ' AND ', array_filter( $where_sql ) );
						$result = mysql_query( $sql, $connection );
						if ( ! $result )
							$report[ 'errors' ][] = mysql_error( );
						else
							$report[ 'updates' ]++;

					} elseif ( $upd ) {
						$report[ 'errors' ][] = sprintf( '"%s" has no primary key, manual change needed on row %s.', $table, $current_row );
					}

				}
			}
		}

	}
	$report[ 'end' ] = microtime( );

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
function eng_list( $input_arr = array( ), $sep = ', ', $before = '"', $after = '"' ) {
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
 * Search through the file name passed for a set of defines used to set up
 * WordPress db access.
 *
 * @param string $filename The file name we need to scan for the defines.
 *
 * @return array    List of db connection details.
 */
function icit_srdb_define_find( $filename = 'wp-config.php' ) {

	$filename = dirname( __FILE__ ) . '/' . basename( $filename );

	if ( file_exists( $filename ) && is_file( $filename ) && is_readable( $filename ) ) {
		$file = @fopen( $filename, 'r' );
		$file_content = fread( $file, filesize( $filename ) );
		@fclose( $file );
	}

	preg_match_all( '/define\s*?\(\s*?([\'"])(DB_NAME|DB_USER|DB_PASSWORD|DB_HOST|DB_CHARSET)\1\s*?,\s*?([\'"])([^\3]*?)\3\s*?\)\s*?;/si', $file_content, $defines );

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
			}
		}
	}

	return array( $host, $name, $user, $pass, $char );
}

/*
 Check and clean all vars, change the step we're at depending on the quality of
 the vars.
*/
$errors = array( );
$step = isset( $_REQUEST[ 'step' ] ) ? intval( $_REQUEST[ 'step' ] ) : 0; // Set the step to the request, we'll change it as we need to.

// Check that we need to scan wp-config.
$loadwp = isset( $_POST[ 'loadwp' ] ) ? true : false;

// DB details
$host = isset( $_POST[ 'host' ] ) ? stripcslashes( $_POST[ 'host' ] ) : 'localhost';	// normally localhost, but not necessarily.
$data = isset( $_POST[ 'data' ] ) ? stripcslashes( $_POST[ 'data' ] ) : '';	// your database
$user = isset( $_POST[ 'user' ] ) ? stripcslashes( $_POST[ 'user' ] ) : '';	// your db userid
$pass = isset( $_POST[ 'pass' ] ) ? stripcslashes( $_POST[ 'pass' ] ) : '';	// your db password
$char = isset( $_POST[ 'char' ] ) ? stripcslashes( $_POST[ 'char' ] ) : '';	// your db charset
// Search replace details
$srch = isset( $_POST[ 'srch' ] ) ? stripcslashes( $_POST[ 'srch' ] ) : '';
$rplc = isset( $_POST[ 'rplc' ] ) ? stripcslashes( $_POST[ 'rplc' ] ) : '';
// Tables to scanned
$tables = isset( $_POST[ 'tables' ] ) && is_array( $_POST[ 'tables' ] ) ? array_map( 'stripcslashes', $_POST[ 'tables' ] ) : array( );
// Do we want to skip changing the guid column
$guid = isset( $_POST[ 'guid' ] ) && $_POST[ 'guid' ] == 1 ? 1 : 0;
$exclude_cols = array( 'guid' ); // Add columns to be excluded from changes to this array. If the GUID checkbox is ticked they'll be skipped.

// If we're at the start we'll check to see if wp is about so we can get the details from the wp-config.
if ( $step == 0 || $step == 1 )
	$step = file_exists( dirname( __FILE__ ) . '/wp-config.php' ) ? 1 : 2;

// Scan wp-config for the defines. We can't just include it as it will try and load the whole of wordpress.
if ( $loadwp && file_exists( dirname( __FILE__ ) . '/wp-config.php' ) )
	list( $host, $data, $user, $pass, $char ) = icit_srdb_define_find( 'wp-config.php' );

// Check the db connection else go back to step two.
if ( $step >= 3 ) {
	$connection = @mysql_connect( $host, $user, $pass );
	if ( ! $connection ) {
		$errors[] = mysql_error( );
		$step = 2;
	}
	
	if ( ! empty( $char ) ) {
		if ( function_exists( 'mysql_set_charset' ) )
			mysql_set_charset( $char, $connection );
		else
			mysql_query( 'SET NAMES ' . $char, $connection );  // Shouldn't really use this, but there for backwards compatibility	
	}
	
	// Do we have any tables and if so build the all tables array
	$all_tables = array( );
	@mysql_select_db( $data, $connection );
	$all_tables_mysql = @mysql_query( 'SHOW TABLES', $connection );

	if ( ! $all_tables_mysql ) {
		$errors[] = mysql_error( );
		$step = 2;
	} else {
		while ( $table = mysql_fetch_array( $all_tables_mysql ) ) {
			$all_tables[] = $table[ 0 ];
		}
	}
}

// Check and clean the tables array
$tables = array_filter( $tables, 'check_table_array' );
if ( $step >= 4 && empty( $tables ) ) {
	$errors[] = 'You didn\'t select any tables.';
	$step = 3;
}

// Make sure we're searching for something.
if ( $step >= 5 ) {
	if ( empty( $srch ) ) {
		$errors[] = 'Missing search string.';
		$step = 4;
	}

	if ( empty( $rplc ) ) {
		$errors[] = 'Replace string is blank.';
		$step = 4;
	}

	if ( ! ( empty( $rplc ) && empty( $srch ) ) && $rplc == $srch ) {
		$errors[] = 'Search and replace are the same, please check your values.';
		$step = 4;
	}
}


/*
 Send the HTML to the screen.
*/
@header('Content-Type: text/html; charset=UTF-8');?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:dc="http://purl.org/dc/terms/" dir="ltr" lang="en-US">
<head profile="http://gmpg.org/xfn/11">
	<title>Search and replace DB.</title>
	<style type="text/css">
	body {
		background-color: #E5E5E5;
		color: #353231;
	        font: 14px/18px "Gill Sans MT","Gill Sans",Calibri,sans-serif;
	}

	p {
	    line-height: 18px;
	    margin: 18px 0;
	    max-width: 520px;
	}

	p.byline {
	    margin: 0 0 18px 0;
	    padding-bottom: 9px;
            border-bottom: 1px dashed #999999;
	    max-width: 100%;
	}

	h1,h2,h3 {
	    font-weight: normal;
	    line-height: 36px;
	    font-size: 24px;
	    margin: 9px 0;
	    text-shadow: 1px 1px 0 rgba(255, 255, 255, 0.8);
	}

	h2 {
	    font-weight: normal;
	    line-height: 24px;
	    font-size: 21px;
	    margin: 9px 0;
	    text-shadow: 1px 1px 0 rgba(255, 255, 255, 0.8);
	}

	h3 {
	    font-weight: normal;
	    line-height: 18px;
	    margin: 9px 0;
	    text-shadow: 1px 1px 0 rgba(255, 255, 255, 0.8);
	}

	a {
	    -moz-transition: color 0.2s linear 0s;
	    color: #DE1301;
	    text-decoration: none;
	    font-weight: normal;
	}

	a:visited {
	   -moz-transition: color 0.2s linear 0s;
	    color: #AE1301;
	}

	a:hover, a:visited:hover {
	    -moz-transition: color 0.2s linear 0s;
	    color: #FE1301;
	    text-decoration: underline;
}

	#container {
		display:block;
		width: 768px;
		padding: 10px;
		margin: 0px auto;
		border:solid 10px 0px 0px 0px #ccc;
		border-top: 18px solid #DE1301;
		background-color: #F5F5F5;
	}

	fieldset {
		border: 0 none;
	}

	.error {
		border: solid 1px #c00;
		padding: 5px;
		background-color: #FFEBE8;
		text-align: center;
		margin-bottom: 10px;
	}

	label {
		display:block;
		line-height: 18px;
		cursor: pointer;
	}

	select.multi,
	input.text {
		margin-bottom: 1em;
		display:block;
		width: 90%;
	}

	select.multi {
		height: 144px;
	}


	input.button {
	}

	div.help {
		border-top: 1px dashed #999999;
		margin-top: 9px;
	}

	</style>
</head>
<body>
	<div id="container"><?php
		if ( ! empty( $errors ) && is_array( $errors ) ) {
			echo '<div class="error">';
			foreach( $errors as $error )
				echo '<p>' . $error . '</p>';
			echo '</div>';
		}?>


	<h1>Safe Search Replace</h1>
	<p class="byline">by interconnect/<strong>it</strong></p>
	<?php
/*
 The bit that does all the work.
*/
switch ( $step ) {
	case 1:
		// Prompt for the loading of WordPress or not.	?>
		<h2>Load DB connection values from WordPress?</h2>
		<form action="<?php icit_srdb_form_action( ); ?>" method="post">
			<fieldset>
				<p><label for="loadwp"><input type="checkbox" checked="checked" value="1" name="loadwp" id="loadwp" /> Pre-populate the DB values form with the ones used in wp-config?  It is possible to edit them later.</label></p> <?php
				icit_srdb_submit( 'Submit' ); ?>
			</fieldset>
		</form>	<?php
		break;


	case 2:
		// Ask for db username password. ?>
		<h2>Database details</h2>
		<form action="<?php icit_srdb_form_action( ); ?>" method="post">
			<fieldset>
				<p>
					<label for="host">Server Name:</label>
					<input class="text" type="text" name="host" id="host" value="<?php esc_html_attr( $host, true ) ?>" />
				</p>

				<p>
					<label for="data">Database Name:</label>
					<input class="text" type="text" name="data" id="data" value="<?php esc_html_attr( $data, true ) ?>" />
				</p>

				<p>
					<label for="user">Username:</label>
					<input class="text" type="text" name="user" id="user" value="<?php esc_html_attr( $user, true ) ?>" />
				</p>

				<p>
					<label for="pass">Password:</label>
					<input class="text" type="password" name="pass" id="pass" value="<?php esc_html_attr( $pass, true ) ?>" />
				</p>
				
				<p>
					<label for="pass">Charset:</label>
					<input class="text" type="text" name="char" id="char" value="<?php esc_html_attr( $char, true ) ?>" />
				</p>
				<?php icit_srdb_submit( 'Submit DB details' ); ?>
			</fieldset>
		</form>	<?php
		break;


	case 3:
		// Ask which tables to deal with ?>
		<h2>Which tables do you want to scan?</h2>
		<form action="<?php icit_srdb_form_action( ); ?>" method="post">

			<fieldset>

				<input type="hidden" name="host" value="<?php esc_html_attr( $host, true ) ?>" />
				<input type="hidden" name="data" value="<?php esc_html_attr( $data, true ) ?>" />
				<input type="hidden" name="user" value="<?php esc_html_attr( $user, true ) ?>" />
				<input type="hidden" name="pass" value="<?php esc_html_attr( $pass, true ) ?>" />
				<input type="hidden" name="char" value="<?php esc_html_attr( $char, true ) ?>" />
				<p>
					<label for="tables">Tables:</label>
					<select id="tables" name="tables[]" multiple="multiple" class="multi"><?php
					foreach( $all_tables as $table ) {
						echo '<option selected="selected" value="' . esc_html_attr( $table ) . '">' . $table . '</option>';
					} ?>
					</select>
					<em>Multiple tables can be selected with ( CTRL/CMD + click ).</em>
				</p>

				<p>
					<label for="guid">
					<input type="checkbox" name="guid" id="guid" value="1" <?php echo $guid == 1 ? 'checked="checked"' : '' ?>/> Leave GUID column unchanged? </label>
					<em>If the values in the GUID column from the WordPress posts table change RSS readers and other tools will be under the impression that the posts are new and may show them in feeds again. <br />
					All columns titled <?php echo eng_list( $exclude_cols ) ?> will be skipped if this it ticked.</em>
				</p>

				<?php icit_srdb_submit( 'Continue', 'Do be sure that you have selected the correct tables - especially important on multi-sites installs.' );	?>
			</fieldset>
		</form>	<?php
		break;


	case 4:
		// Ask for the search replace strings. ?>
		<h2>What to replace?</h2>
		<form action="<?php icit_srdb_form_action( ); ?>" method="post">
			<fieldset>
				<input type="hidden" name="host" id="host" value="<?php esc_html_attr( $host, true ) ?>" />
				<input type="hidden" name="data" id="data" value="<?php esc_html_attr( $data, true ) ?>" />
				<input type="hidden" name="user" id="user" value="<?php esc_html_attr( $user, true ) ?>" />
				<input type="hidden" name="pass" id="pass" value="<?php esc_html_attr( $pass, true ) ?>" />
				<input type="hidden" name="char" id="char" value="<?php esc_html_attr( $char, true ) ?>" />
				<input type="hidden" name="guid" id="guid" value="<?php esc_html_attr( $guid, true ) ?>" /> <?php

				foreach( $tables as $i => $tab ) {
					printf( '<input type="hidden" name="tables[%s]" value="%s" />', esc_html_attr( $i, false ), esc_html_attr( $tab, false ) );
				} ?>

				<p>
					<label for="srch">Search for (case sensitive string):</label>
					<input class="text" type="text" name="srch" id="srch" value="<?php esc_html_attr( $srch, true ) ?>" />
				</p>

				<p>
					<label for="rplc">Replace with:</label>
					<input class="text" type="text" name="rplc" id="rplc" value="<?php esc_html_attr( $rplc, true ) ?>" />
				</p>

				<?php icit_srdb_submit( 'Submit Search string', 'Are you REALLY sure you want to go ahead and do this?' ); ?>
			</fieldset>
		</form>	<?php
		break;


	case 5:

		@ set_time_limit( 60 * 10 );
		// Try to push the allowed memory up, while we're at it
		@ ini_set( 'memory_limit', '1024M' );

		// Process the tables
		if ( isset( $connection ) )
			$report = icit_srdb_replacer( $connection, $srch, $rplc, $tables );

		// Output any errors encountered during the db work.
		if ( ! empty( $report[ 'errors' ] ) && is_array( $report[ 'errors' ] ) ) {
			echo '<div class="error">';
			foreach( $report[ 'errors' ] as $error )
				echo '<p>' . $error . '</p>';
			echo '</div>';
		}

		// Calc the time taken.
		$time = array_sum( explode( ' ', $report[ 'end' ] ) ) - array_sum( explode( ' ', $report[ 'start' ] ) ); ?>

		<h2>Completed</h2>
		<p><?php printf( 'In the process of replacing <strong>"%s"</strong> with <strong>"%s"</strong> we scanned <strong>%d</strong> tables with a total of <strong>%d</strong> rows, <strong>%d</strong> cells were changed and <strong>%d</strong> db update performed and it all took <strong>%f</strong> seconds.', $srch, $rplc, $report[ 'tables' ], $report[ 'rows' ], $report[ 'change' ], $report[ 'updates' ], $time ); ?></p> <?php
		break;


	default: ?>
		<h2>No idea how we got here.</h2>
		<p>Something strange has happened.</p> <?php
		break;
}

if ( isset( $connection ) && $connection )
	mysql_close( $connection );


// Warn if we're running in safe mode as we'll probably time out.
if ( ini_get( 'safe_mode' ) ) {
	echo '<h4>Warning</h4>';
	printf( '<p style="color:red;">Safe mode is on so you may run into problems if it takes longer than %s seconds to process your request.</p>', ini_get( 'max_execution_time' ) );
}
/*
 Close out the html and exit.
*/ ?>
		<div class="help">
			<h4><a href="http://interconnectit.com/">interconnect/it</a> <a href="http://interconnectit.com/124/search-and-replace-for-wordpress-databases/">Safe Search and Replace on Database with Serialized Data v2.0.0</a></h4>
			<p>This developer/sysadmin tool helps solve the problem of doing a search and replace on a
			WordPress site when doing a migration to a domain name with a different length.</p>

			<p><style="color:red">WARNING!</strong> Take a backup first, and carefully test the results of this code.
			If you don't, and you vape your data then you only have yourself to blame.
			Seriously.  And if you're English is bad and you don't fully understand the
			instructions then STOP.  Right there.  Yes.  Before you do any damage.

			<h2>Don't Forget to Remove Me!</h3>

			<p style="color:red">Delete this utility from your
			server after use.  It represents a major security threat to your database if
			maliciously used.</p>

			<h2>Use Of This Script Is Entirely At Your Own Risk</h2>

			<p> We accept no liability from the use of this tool.</p>

			<p>If you're not comfortable with this kind of stuff, get an expert, like us, to do
			this work for you.  You do this ENTIRELY AT YOUR OWN RISK!  We accept no responsibility
			if you mess up your data.  There is NO UNDO here!</p>

			<p>The easiest way to use it is to copy your site's files and DB to the new location.
			You then, if required, fix up your .htaccess and wp-config.php appropriately.  Once
			done, run this script, select your tables (in most cases all of them) and then
			enter the search replace strings.  You can press back in your browser to do
			this several times, as may be required in some cases.</p>

			<p>Of course, you can use the script in many other ways - for example, finding
			all references to a company name and changing it when a rebrand comes along.  Or
			perhaps you changed your name.  Whatever you want to search and replace the code will help.</p>

			<p><a href="http://interconnectit.com/124/search-and-replace-for-wordpress-databases/">Got feedback on this script? Come tell us!</a>

		</div>
	</div>
</body>
</html>

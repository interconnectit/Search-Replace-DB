<?php

/**
 *
 * Safe Search and Replace on Database with Serialized Data v4.1.3
 * Copyright © 2020  Interconnect IT Limited
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
 * This file is part of Search-Replace-DB.
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

require_once( realpath( dirname( __FILE__ ) ) . '/srdb.class.php' );

class icit_srdb_ui extends icit_srdb {

    /**
     * @var string Root path of the CMS
     */
    public $path;

    public $name;
    public $user;
    public $pass;
    public $host;
    public $port;
    public $charset;
    public $collate;
    public $tables;
    public $search;
    public $replace;
    public $exclude_cols;
    public $include_cols;
    public $regex;
    public $regex_i;
    public $regex_m;
    public $regex_s;
    public $regex_x;

    public function __construct() {

        // php 5.4 date timezone requirement, shouldn't affect anything
        date_default_timezone_set( 'Europe/London' );

        // prevent fatals from hiding the UI
        register_shutdown_function( array( $this, 'fatal_handler' ) );

        $this->response();

    }

    public function response(
        $name = '',
        $user = '',
        $pass = '',
        $host = '127.0.0.1',
        $port = 3306,
        $charset = 'utf8',
        $collate = ''
    ) {

        if ( version_compare( PHP_VERSION, '7.3' ) < 0 ) {
            $this->add_error( "This script has been tested using PHP7.3 +, whereas your version is: " . PHP_VERSION . ". Although this script may work with older versions you do so at your own risk. Please update php and try again",
                "compatibility" );
        }


        if ( ! extension_loaded( "mbstring" ) ) {
            $this->add_error( "This script requires mbstring. Please install mbstring and try again", "compatibility" );

        }

        // always override with post data
        if ( isset( $_POST['name'] ) ) {
            $this->name = $_POST['name']; // your database
            $this->user = $_POST['user']; // your db userid
            $this->pass = $_POST['pass']; // your db password
            $this->host = $_POST['host']; // normally localhost, but not necessarily.

            $port_input = $_POST['port'];

            // Make sure that the string version of absint(port) is identical to the string input.
            // This prevents expressions, decimals, spaces, etc.
            $port_as_string = (string) $port_input ? (string) $port_input : "0";
            if ( (string) abs( (int) $port_input ) !== $port_as_string ) {
                // Mangled port number: non numeric.
                $this->add_error( 'Port number must be a positive integer. If you are unsure, try the default port 3306.',
                    'db' );

                // Force a bad run by supplying nonsense.
                $this->port = "nonsense";
            } else {
                $this->port = abs( (int) $port_input );
            }

            $this->charset = 'utf8';
            $this->collate = '';
        }

        // Search replace details
        $this->search  = isset( $_POST['search'] ) ? $_POST['search'] : '';
        $this->replace = isset( $_POST['replace'] ) ? $_POST['replace'] : '';

        // regex options
        $this->regex   = isset( $_POST['regex'] );
        $this->regex_i = isset( $_POST['regex_i'] );
        $this->regex_m = isset( $_POST['regex_m'] );
        $this->regex_s = isset( $_POST['regex_s'] );
        $this->regex_x = isset( $_POST['regex_x'] );

        // Tables to scanned
        $this->tables = isset( $_POST['tables'] ) && is_array( $_POST['tables'] ) ? $_POST['tables'] : array();
        if ( isset( $_POST['use_tables'] ) && $_POST['use_tables'] == 'all' ) {
            $this->tables = array();
        }

        // exclude / include columns
        $this->exclude_cols = isset( $_POST['exclude_cols'] ) ? $_POST['exclude_cols'] : array();

        if ( $this->exclude_cols && is_string( $this->exclude_cols ) ) {
            $this->exclude_cols = array_filter( array_map( 'trim', explode( ',', $this->exclude_cols ) ) );
        }

        $this->include_cols = isset( $_POST['include_cols'] ) ? $_POST['include_cols'] : array();

        if ( $this->include_cols && is_string( $this->include_cols ) ) {
            $this->include_cols = array_filter( array_map( 'trim', explode( ',', $this->include_cols ) ) );
        }

        // are doing something?
        $show = '';
        if ( isset( $_POST['submit'] ) ) {
            if ( is_array( $_POST['submit'] ) ) {
                $show = key( $_POST['submit'] );
            }
            if ( is_string( $_POST['submit'] ) ) {
                $show = preg_replace( '/submit\[([a-z0-9]+)\]/', '$1', $_POST['submit'] );
            }
        }

        // is it an AJAX call
        $ajax = isset( $_POST['ajax'] );

        // body callback
        $html = 'ui';

        switch ( $show ) {

            // remove search replace
            case 'delete':
            {
                // determine if it's the folder of compiled version
                $path = basename( __FILE__ ) == 'index.php' ? str_replace( basename( __FILE__ ), '',
                    __FILE__ ) : __FILE__;

                if ( $this->delete_script( $path ) ) {
                    $this->add_error( 'Could not fully delete Search/Replace. You will have to delete it manually',
                        'delete' );
                } else {
                    if ( ! ( is_file( __FILE__ ) && file_exists( __FILE__ ) ) ) {
                        $this->add_error( 'Search/Replace has been successfully removed from your server', 'delete' );
                    }
                }

                $html = 'deleted';

                break;
            }
            case 'liverun':
            {
                // bsy-web, 20130621: Check live run was explicitly clicked and only set false then
                $this->dry_run = false;
            }
            case 'dryrun':
            {

                // build regex string
                // non UI implements can just pass in complete regex string
                if ( $this->regex ) {
                    $mods = '';
                    if ( $this->regex_i ) {
                        $mods .= 'i';
                    }
                    if ( $this->regex_s ) {
                        $mods .= 's';
                    }
                    if ( $this->regex_m ) {
                        $mods .= 'm';
                    }
                    if ( $this->regex_x ) {
                        $mods .= 'x';
                    }

                    if(is_array($this->search)){
                        foreach ($this->search as $searchKey => $searchString){
                            $this->search[$searchKey] = '/' . $searchString . '/' . $mods;
                        }
                    }else{
                        $this->search = '/' . $this->search . '/' . $mods;
                    }
                }

                // call search replace class
                $parent = parent::__construct( array(
                    'name'         => $this->name,
                    'user'         => $this->user,
                    'pass'         => $this->pass,
                    'host'         => $this->host,
                    'port'         => $this->port,
                    'search'       => $this->search,
                    'replace'      => $this->replace,
                    'tables'       => $this->tables,
                    'dry_run'      => $this->dry_run,
                    'regex'        => $this->regex,
                    'exclude_cols' => $this->exclude_cols,
                    'include_cols' => $this->include_cols
                ) );

                break;
            }

            case 'innodb':
            {


                // call search replace class to alter engine
                $parent = parent::__construct( array(
                    'name'         => $this->name,
                    'user'         => $this->user,
                    'pass'         => $this->pass,
                    'host'         => $this->host,
                    'port'         => $this->port,
                    'tables'       => $this->tables,
                    'alter_engine' => 'InnoDB',
                ) );

                break;
            }

            case 'utf8':
            {
                // call search replace class to alter engine
                $parent = parent::__construct( array(
                    'name'            => $this->name,
                    'user'            => $this->user,
                    'pass'            => $this->pass,
                    'host'            => $this->host,
                    'port'            => $this->port,
                    'tables'          => $this->tables,
                    'alter_collation' => 'utf8_unicode_ci',
                ) );

                break;
            }

            case 'utf8mb4':
            {
                // call search replace class to alter engine
                $parent = parent::__construct( array(
                    'name'            => $this->name,
                    'user'            => $this->user,
                    'pass'            => $this->pass,
                    'host'            => $this->host,
                    'port'            => $this->port,
                    'tables'          => $this->tables,
                    'alter_collation' => 'utf8mb4_unicode_ci',
                ) );

                break;
            }

            case 'update':
            default:
            {

                // get tables or error messages
                $this->db_setup();

                if ( $this->db_valid() ) {
                    // get engines
                    $this->engines = $this->get_engines();

                    // get tables
                    $this->all_tables = $this->get_tables();
                }

                break;
            }
        }

        $info = array(
            'table_select' => $this->table_select( false ),
            'engines'      => $this->engines
        );

        // set header again before output in case WP does it's thing
        header( 'HTTP/1.1 200 OK' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' ); // HTTP 1.1.
        header( 'Pragma: no-cache' ); // HTTP 1.0.
        header( 'Expires: 0' ); // Proxies.
        if ( ! $ajax ) {
            $this->html( $html );
        } else {

            // return json version of results
            header( 'Content-Type: application/json' );

            echo json_encode( array(
                'errors' => $this->errors,
                'report' => $this->report,
                'info'   => $info
            ) );

            exit;
        }
    }

    public function exceptions( $exception ) {
        $this->add_error( '<p class="exception">' . $exception->getMessage() . '</p>' );
    }

    public function error_handler( $no, $message, $file, $line ) {
        $this->add_error( '<p class="error">' . "<strong>{$no}:</strong> {$message} in {$file} on line {$line}" . '</p>',
            'results' );
        $this->add_error( 'This is usually caused by a plugin storing classes as a serialised string which other
        PHP classes can\'t then access. It is not possible to unserialise this data because the PHP can\'t access this class.
        P.S. It\'s most commonly a Yoast plugin that causes this error. Your changes will still have taken place within the rest of the database.',
            'results' );
    }

    public function fatal_handler() {
        $error = error_get_last();

        if ( $error !== null ) {
            $errno   = $error["type"];
            $errfile = $error["file"];
            $errline = $error["line"];
            $errstr  = $error["message"];

            if ( $errno == 1 ) {
                header( 'HTTP/1.1 200 OK' );
                $this->add_error( '<p class="error">Could not bootstrap environment.<br /> ' . "Fatal error in {$errfile}, line {$errline}. {$errstr}" . '</p>',
                    'environment' );
                $this->response();
            }
        }
    }

    /**
     * Checks an array of fully qualified filenames to see if they are all
     * SRDB filenames.
     *
     * @param array $array_of_paths
     *
     * @return boolean true if all paths are most likely SRDB files.
     */
    public function safe_to_delete_all_filenames( $array_of_paths ) {
        foreach ( $array_of_paths as $path ) {
            if ( ! $this->safe_to_delete_filename( $path ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Delete files and directories
     *
     * @param string $path directory/file path
     *
     */
    public function delete_script( $path ) {
        $path      = rtrim( $path, DIRECTORY_SEPARATOR );
        $file_list = array(
            "README.md",
            "srdb.cli.php",
            "composer.lock",
            "CHANGELOG.md",
            "phpunit.xml",
            "index.php",
            "LICENSE.txt",
            "composer.json",
            "package.json",
            "tests" . DIRECTORY_SEPARATOR . "DataSetGenerator.php",
            "tests" . DIRECTORY_SEPARATOR . "DataSet.xml",
            "tests" . DIRECTORY_SEPARATOR . "SrdbTest.php",
            "srdb.class.php",
        );

        foreach ( $file_list as $file ) {
            if ( is_file( $path . DIRECTORY_SEPARATOR . $file ) ) {
                if ( @unlink( $path . DIRECTORY_SEPARATOR . $file ) === false ) {
                    return - 1;
                }
            }
        }

        if ( is_dir( $path . DIRECTORY_SEPARATOR . 'tests' ) ) {
            if ( @rmdir( $path . DIRECTORY_SEPARATOR . 'tests' ) === false ) {
                return - 1;
            }
        }

        return 0;
    }

    /**
     * Simple html escaping
     *
     * @param string $string Thing that needs escaping
     * @param bool $echo Do we echo or return?
     *
     * @return string    Escaped string.
     */
    public function esc_html_attr( $string = '', $echo = false ) {
        $output = htmlentities( $string, ENT_QUOTES, 'UTF-8' );
        if ( $echo ) {
            echo $output;
        } else {
            return $output;
        }
    }

    public function checked( $value, $value2, $echo = true ) {
        $output = $value == $value2 ? ' checked="checked"' : '';
        if ( $echo ) {
            echo $output;
        }

        return $output;
    }

    public function selected( $value, $value2, $echo = true ) {
        $output = $value == $value2 ? ' selected="selected"' : '';
        if ( $echo ) {
            echo $output;
        }

        return $output;
    }


    public function get_errors( $type ) {
        if ( ! isset( $this->errors[ $type ] ) || ! count( $this->errors[ $type ] ) ) {
            return;
        }

        echo '<div class="errors">';
        $dup_errors = array();
        foreach ( $this->errors[ $type ] as $error ) {
            if ( ! in_array( $error, $dup_errors ) ) {
                if ( $error instanceof Exception ) {
                    echo '<p class="exception">' . $error->getMessage() . '</p>';
                } elseif ( is_string( $error ) ) {
                    echo $error;
                }
            }
            array_push( $dup_errors, $error );
        }
        echo '</div>';
    }


    public function get_report( $table = null ) {

        $report = $this->report;
        if ( empty( $report ) ) {
            return;
        }
        $report  = $report[0];
        $dry_run = $this->dry_run;

        $search  = $this->search;
        $replace = $this->replace;
        // Calc the time taken.
        $time = array_sum( explode( ' ', $report['end'] ) ) - array_sum( explode( ' ', $report['start'] ) );
        if ( $time < 0 ) {
            $time = $time * - 1;
        }
        $srch_rplc_input_phrase = $dry_run ?
            'searching for <strong>"' . $this->esc_html_attr( $search ) . '"</strong> (to be replaced by <strong>"' . $this->esc_html_attr( $replace ) . '"</strong>)' :
            'replacing <strong>"' . $this->esc_html_attr( $search ) . '"</strong> with <strong>"' . $this->esc_html_attr( $replace ) . '"</strong>';

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
            $report['tables'],
            $report['rows'],
            $report['change'],
            $dry_run ? 'would have been' : 'were',
            $report['updates'],
            $time
        );
        echo '
    </p>';

        echo '
    <table class="table-reports-0">
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
        foreach ( $report['table_reports'] as $table => $t_report ) {

            $t_time = array_sum( explode( ' ', $t_report['end'] ) ) - array_sum( explode( ' ', $t_report['start'] ) );

            echo '
        <tr>';
            printf( '
            <th>%s:</th>
            <td>%d</td>
            <td>%d</td>
            <td>%d</td>
            <td>%f</td>',
                $table,
                $t_report['rows'],
                $t_report['change'],
                $t_report['updates'],
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
            foreach ( $this->all_tables as $table ) {
                $size      = $table['Data_length'] / 1000;
                $size_unit = 'kb';
                if ( $size > 1000 ) {
                    $size      = $size / 1000;
                    $size_unit = 'Mb';
                }
                if ( $size > 1000 ) {
                    $size      = $size / 1000;
                    $size_unit = 'Gb';
                }
                $size = number_format( $size, 2 ) . $size_unit;
                $rows = $table['Rows'] > 1 ? 'rows' : 'row';

                $table_select .= sprintf( '<option value="%s" %s>%s</option>',
                    $this->esc_html_attr( $table[0], false ),
                    $this->selected( true, in_array( $table[0], $this->tables ), false ),
                    "{$table[0]}: {$table['Engine']}, rows: {$table['Rows']}, size: {$size}, collation: {$table['Collation']}, character_set: {$table['Character_set']}"
                );
            }
            $table_select .= '</select>';
        }

        if ( $echo ) {
            echo $table_select;
        }

        return $table_select;
    }

    public function isSecure() {
        return
            ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off';

    }

    public function ui() {
        if ( ! $this->isSecure() ) {
            ?>
            <div class="special-errors">
                <h4>Warning</h4>
                <?php echo printf( '<p>The network connection you are using is transmitting your password unencrypted. <br/> Consider using an https:// connection, or change your database password after using the script </p>' ); ?>
            </div>
            <?php
        }

        // Warn if we're running in safe mode as we'll probably time out.
        if ( ini_get( 'safe_mode' ) ) {
            ?>
            <div class="special-errors">
                <h4>Warning</h4>
                <?php echo printf( '<p>Safe mode is on so you may run into problems if it takes longer than %s seconds to process your request.</p>',
                    ini_get( 'max_execution_time' ) ); ?>
            </div>
            <?php
        }
        ?>

        <?php
        // templates/ui.php
        ?>
        <form action="" method="post">

            <!-- 1. search/replace -->
            <fieldset class="row row-search">

                <h1>Search<span>Replace</span></h1>

                <?php $this->get_errors( 'search' ); ?>

                <div class="fields fields-large">
                    <div class="sr-boxes">
                        <div class="sr-box-0">
                            <label for="search-0"><span class="label-text">replace</span> <span
                                    class="hide-if-regex-off regex-left">/</span><input id="search-0" type="text"
                                                                                        placeholder="search for&hellip;"
                                                                                        value="<?php $this->esc_html_attr( $this->search,
                                                                                            true ); ?>"
                                                                                        name="search-0"/><span
                                    class="hide-if-regex-off regex-right">/</span></label>
                            <label for="replace-0"><span class="label-text">with</span> <input id="replace-0"
                                                                                               type="text"
                                                                                               placeholder="replace with&hellip;"
                                                                                               value="<?php $this->esc_html_attr( $this->replace,
                                                                                                   true ); ?>"
                                                                                               name="replace-0"/></label>
                        </div>
                    </div>

                    <div>
                        <button class="multi-search add-search" type="modify-ui" onclick="add_search()">+ add more
                            search terms
                        </button>
                        <button class="multi-search remove-search hide-if-multisearch-off" type="modify-ui"
                                onclick="remove_search()">- remove search terms
                        </button>
                    </div>

                    <label class="label-text" for="regex" class="field-advanced"><input id="regex" type="checkbox"
                                                                                        name="regex"
                                                                                        value="1" <?php $this->checked( true,
                            $this->regex ); ?> />
                        use regex</label>

                    <div class="fields field-advanced hide-if-regex-off">
                        <label for="regex_i" class="field field-advanced"><input type="checkbox" name="regex_i"
                                                                                 id="regex_i"
                                                                                 value="1" <?php $this->checked( true,
                                $this->regex_i ); ?> />
                            <abbr title="case insensitive">Case insensitive</abbr></abbr></label>
                        <label for="regex_m" class="field field-advanced"><input type="checkbox" name="regex_m"
                                                                                 id="regex_m"
                                                                                 value="1" <?php $this->checked( true,
                                $this->regex_m ); ?> />
                            <abbr title="multiline">Multiline</abbr></label>
                        <label for="regex_x" class="field field-advanced"><input type="checkbox" name="regex_x"
                                                                                 id="regex_x"
                                                                                 value="1" <?php $this->checked( true,
                                $this->regex_x ); ?> />
                            <abbr title="extended mode">Extended mode</abbr></label>
                        <label for="regex_s" class="field field-advanced"><input type="checkbox" name="regex_s"
                                                                                 id="regex_s"
                                                                                 value="1" <?php $this->checked( true,
                                $this->regex_s ); ?> />
                            <abbr title="dot also matches newlines">Dot also matches newlines</abbr></label>
                    </div>


            </fieldset>

            <!-- 2. db details -->
            <fieldset class="row row-db">

                <h1>Database Details</h1>

                <?php $this->get_errors( 'environment' ); ?>
                <?php $this->get_errors( 'recoverable_db' ); ?>
                <?php $this->get_errors( 'compatibility' ); ?>
                <?php $this->get_errors( 'connection' ); ?>

                <div class="fields fields-small">

                    <div class="field field-short">
                        <label for="name">database name</label>
                        <input id="name" name="name" type="text"
                               value="<?php $this->esc_html_attr( $this->name, true ); ?>"/>
                    </div>

                    <div class="field field-short">
                        <label for="user">username</label>
                        <input id="user" name="user" type="text"
                               value="<?php $this->esc_html_attr( $this->user, true ); ?>"/>
                    </div>

                    <div class="field field-short">
                        <label for="pass">pass</label>
                        <input id="pass" name="pass" type="password"
                               value="<?php $this->esc_html_attr( $this->pass, true ); ?>"/>
                    </div>

                    <div class="field field-short">
                        <label for="host">host</label>
                        <input id="host" name="host" type="text"
                               value="<?php $this->esc_html_attr( $this->host, true ); ?>"/>
                    </div>

                    <div class="field field-short">
                        <label for="port">port</label>
                        <input id="port" name="port" type="text"
                               value="<?php $this->esc_html_attr( $this->port, true ); ?>"/>
                    </div>

                    <span class="submit-group">
                        <input type="submit" name="submit[update]" value="Test connection"/>
            </span>

                    <div class="successful-connection" style="display:none">
                        Success. You are connected.
                    </div>


                </div>

            </fieldset>

            <!-- 3. tables -->
            <fieldset class="row row-tables">

                <h1>Which Tables?</h1>

                <?php $this->get_errors( 'tables' ); ?>

                <div class="fields">

                    <div class="field radio">
                        <label for="all_tables">
                            <input id="all_tables" name="use_tables" value="all"
                                   type="radio" <?php if ( ! $this->db_valid() ) {
                                echo 'disabled="disabled"';
                            } ?> <?php $this->checked( true, empty( $this->tables ) ); ?> />
                            all tables
                        </label>
                    </div>

                    <div class="field radio">
                        <label for="subset_tables">
                            <input id="subset_tables" name="use_tables" value="subset"
                                   type="radio" <?php if ( ! $this->db_valid() ) {
                                echo 'disabled="disabled"';
                            } ?> <?php $this->checked( false, empty( $this->tables ) ); ?> />
                            select tables
                        </label>
                    </div>

                    <div class="field table-select hide-if-js"><?php $this->table_select(); ?></div>

                </div>

                <div class="fields field-advanced">

                    <div class="field field-advanced field-medium">
                        <label for="exclude_cols">columns to exclude (optional, comma separated)</label>
                        <input id="exclude_cols" type="text" name="exclude_cols"
                               value="<?php $this->esc_html_attr( implode( ',', $this->exclude_cols ) ) ?>"
                               placeholder="eg. guid"/>
                    </div>
                    <div class="field field-advanced field-medium">
                        <label for="include_cols">columns to include only (optional, comma separated)</label>
                        <input id="include_cols" type="text" name="include_cols"
                               value="<?php $this->esc_html_attr( implode( ',', $this->include_cols ) ) ?>"
                               placeholder="eg. post_content, post_excerpt"/>
                    </div>

                </div>
                <div class="fields">
            <span class="submit-group">
                <?php if ( in_array( 'InnoDB', $this->engines ) ) { ?>
                    <input type="submit" name="submit[innodb]"
                           value="convert to innodb" <?php if ( ! $this->db_valid() ) {
                        echo 'disabled="disabled"';
                    } ?>
                           class="db-required secondary field-advanced"/>
                <?php } ?>

                <input type="submit" name="submit[utf8]"
                       value="convert to utf8 unicode" <?php if ( ! $this->db_valid() ) {
                    echo 'disabled="disabled"';
                } ?>
                       class="db-required secondary field-advanced"/>

                <input type="submit" name="submit[utf8mb4]"
                       value="convert to utf8mb4 unicode" <?php if ( ! $this->db_valid() ) {
                    echo 'disabled="disabled"';
                } ?>
                       class="db-required secondary field-advanced"/>

            </span>
                </div>

            </fieldset>

            <!-- 4. results -->
            <fieldset class="row row-results">

                <h1>Let's go</h1>

                <?php $this->get_errors( 'results' ); ?>

                <div class="fields">
            <span class="submit-group">
                <div class="dryrun">
                    <input type="submit" name="submit[dryrun]"
                           value="Do a safe test run" <?php if ( ! $this->db_valid() ) {
                        echo 'disabled="disabled"';
                    } ?>
                       class="db-required"/>
                </div>
                <hr>
                <div class="liverun">
                    <div><p class="label-text">You cannot undo this once started - are you sure?</p></div>
                    <input type="submit" name="submit[liverun]"
                           value="Search and Replace" <?php if ( ! $this->db_valid() ) {
                        echo 'disabled="disabled"';
                    } ?>
                       class="db-required run-script"/>
                </div>
            </span>
                </div>

                <?php $this->get_report(); ?>

            </fieldset>


            <!-- 5. branding -->
            <section class="row row-delete">

                <h1>DELETE</h1>

                <div class="fields">
                    <p>
                        <input type="submit" name="submit[delete]" value="delete me"/>
                        Once you&rsquo;re done click the <strong>delete me</strong> button to secure your server
                    </p>
                </div>

            </section>
            <div id="deleteModal" class="modal">

                <!-- Modal content -->
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <p>Some text in the Modal..</p>
                </div>

            </div>

        </form>

        <section class="help">

            <h1 class="branding">interconnect/it</h1>

            <h2>Safe Search and Replace on Database with Serialized Data v4.1.3</h2>

            <p>This developer/sysadmin tool carries out search/replace functions on MySQL DBs and can handle serialised
                PHP Arrays and Objects.</p>

            <p><strong class="red">WARNINGS!</strong>
                Ensure data is backed up.
                There is NO UNDO!
                Be careful running this script on a production server.</p>

            <h3>Don't Forget to Remove Me!</h3>

            <p>Delete this utility from your
                server after use by clicking the 'delete me' button. It represents a major security threat to your
                database if
                maliciously used.</p>

            <p>If you have feedback or want to contribute to this script click the delete button to find out how.</p>

            <p><em>We don't put links on the search replace UI itself to avoid seeing URLs for the script in our access
                    logs.</em></p>

            <h3>Again, use Of This Script Is Entirely At Your Own Risk</h3>

            <p>The easiest and safest way to use this script is to copy your site's files and DB to a new location.
                You then, if required, fix up your .htaccess and wp-config.php appropriately. Once
                done, run this script, select your tables (in most cases all of them) and then
                enter the search replace strings. You can press back in your browser to do
                this several times, as may be required in some cases.</p>

        </section>
        <?php

    }

    public function deleted() {
        // templates/delete.php
        ?>
        <!-- 1. branding -->
        <section class="row row-branding">

            <h1><a href="http://interconnectit.com/" target="_blank">interconnect<span>/</span><strong>it</strong></a>
            </h1>

            <?php $this->get_errors( 'delete' ); ?>

            <div class="content">
                <p>Thanks for using our search/replace tool! We&rsquo;d really appreciate it if you took a
                    minute to join our mailing list and check out some of our other products.</p>
            </div>

        </section>

        <!-- 2. subscribe -->
        <section class="row row-subscribe">

            <h1>Newsletter</h1>

            <form action="http://interconnectit.us2.list-manage.com/subscribe/post" method="POST"
                  class="fields fields-small">
                <input type="hidden" name="u" value="08ec797202866aded7b2619b2">
                <input type="hidden" name="id" value="aa9c21f993">

                <div id="mergeTable" class="mergeTable">

                    <div class="mergeRow dojoDndItem mergeRow-email field field-short" id="mergeRow-0">
                        <label for="MERGE0"><strong>email address</strong> <span class="asterisk">*</span></label>
                        <input type="email" autocapitalize="off" autocorrect="off" name="MERGE0" id="MERGE0" size="25"
                               value="">
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
                        <br/>
                        <input type="submit" name="submit" value="subscribe">
                    </div>

                </div>
            </form>

        </section>

        <!-- 3. contribute -->
        <section class="row row-contribute">

            <h1>Contribute</h1>

            <div class="content">

                <p>Got suggestions? Found a bug? Want to contribute code? <a
                        href="https://github.com/interconnectit/search-replace-db">Join us on Github!</a></p>

            </div>

        </section>

        <section class="row row-blog">

            <h1>Blogs</h1>

            <div class="content">
                <p><a href="http://interconnectit.com/blog/" target="_blank">We couldn't load our blog feed for some
                        reason so here's a link instead!</a></p>
            </div>

        </section>

        <!-- 5. products -->
        <section class="row row-products">

            <h1>Products</h1>

            <div class="content">
                <p><a href="http://interconnectit.com/products/" target="_blank">We couldn't load our product feed for
                        some reason so here's a link instead!</a></p>
            </div>

        </section>
        <?php
    }

    public function html( $body ) {
        $classes   = array( 'no-js' );
        $classes[] = $this->regex ? 'regex-on' : 'regex-off';
        $classes[] = 'multisearch-off';
        // templates/html.php
        ?>
        <!DOCTYPE html>
        <html class="<?php echo implode( ' ', $classes ); ?>">
        <head>
            <title>interconnect/it : search replace db</title>
            <meta charset="utf-8">
            <link rel="icon"
            "data:image/x-icon;base64,AAABAAIAEBAAAAAAIABoBAAAJgAAACAgAAAAACAAqBAAAI4EAAAoAAAAEAAAACAAAAABACAAAAAAAEAEAAAAAAAAAAAAAAAAAAAAAAAA////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8BARPeXQET3kX///8B////Af///wEzMzMjMzMzfzMzMyP///8B////ATMzM0kzMzOJMzMziTMzMy////8B////AQET3nsBE96l////Af///wH///8BMzMzRTMzM/8zMzNF////ATMzMzczMzP/MzMzzTMzM6EzMzOR////Af///wEBE94nARPe+////wH///8B////ATMzM0UzMzP/MzMzRf///wEzMzN/MzMz/zMzMwX///8B////Af///wH///8B////AQET3ukBE95B////Af///wEzMzNFMzMz/zMzM0X///8BMzMzfzMzM/////8B////Af///wH///8B////Af///wEBE96VARPeg////wH///8BMzMzRTMzM/8zMzNF////ATMzM38zMzP/////Af///wH///8B////Af///wH///8BARPeXwET3t3///8B////ATMzM0UzMzP/MzMzRf///wEzMzN/MzMz/////wH///8B////Af///wH///8B////AQET3gUBE97/ARPeFf///wEzMzNFMzMz/zMzM0UzMzNRMzMzvzMzM/8zMzN/MzMzfzMzMyP///8B////Af///wH///8BARPevwET3mv///8BMzMzKzMzM8MzMzMrMzMzBTMzM8MzMzP/MzMzwzMzM8MzMzMr////Af///wH///8B////AQET3nsBE96l////Af///wH///8B////Af///wEzMzMFMzMzw////wH///8B////Af///wH///8B////Af///wEBE94nARPe+////wEzMzMzMzMz2TMzMxv///8B////ATMzMwX///8B////Af///wH///8B////Af///wH///8B////AQET3ukBE95BMzMzKzMzM68zMzMV////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wEBE95VARPeQf///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////AQAA//8AAP//AAD//wAA//8AAP//AAD//wAA//8AAP//AAD//wAA//8AAP//AAD//wAA//8AAP//AAD//wAA//8oAAAAIAAAAEAAAAABACAAAAAAAIAQAAAAAAAAAAAAAAAAAAAAAAAA////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wEBE94jARPeEf///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8BMzMzIzMzMyP///8B////Af///wH///8B////Af///wH///8BARPeVQET3v8BE97/////Af///wH///8B////Af///wH///8B////Af///wEzMzOJMzMz/zMzM/8zMzOJ////Af///wH///8B////Af///wEzMzMjMzMz/zMzM/8zMzP/MzMz/zMzM/8zMzO7////Af///wH///8B////Af///wH///8BARPe/wET3v8BE94R////Af///wH///8B////Af///wH///8B////ATMzM4kzMzP/MzMz/zMzM4n///8B////Af///wH///8BMzMzIzMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzMj////Af///wH///8B////Af///wEBE97vARPe/wET3on///8B////Af///wH///8B////Af///wH///8BMzMziTMzM/8zMzP/MzMzif///wH///8B////Af///wEzMzO7MzMz/zMzM/8zMzPvMzMzRTMzMyMzMzNnMzMz/zMzMyP///8B////Af///wH///8B////AQET3okBE97/ARPe7////wH///8B////Af///wH///8B////Af///wEzMzOJMzMz/zMzM/8zMzOJ////Af///wH///8B////ATMzM/8zMzP/MzMz/zMzMxH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8BARPeEQET3v8BE97/////Af///wH///8B////Af///wH///8B////ATMzM4kzMzP/MzMz/zMzM4n///8B////Af///wH///8BMzMz/zMzM/8zMzP/////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8BARPe/wET3v8BE95V////Af///wH///8B////Af///wH///8BMzMziTMzM/8zMzP/MzMzif///wH///8B////Af///wEzMzP/MzMz/zMzM/////8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wEBE96rARPe/wET3qv///8B////Af///wH///8B////Af///wEzMzOJMzMz/zMzM/8zMzOJ////Af///wH///8B////ATMzM/8zMzP/MzMz/////wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////AQET3lUBE97/ARPe/////wH///8B////Af///wH///8B////ATMzM4kzMzP/MzMz/zMzM4n///8B////Af///wH///8BMzMz/zMzM/8zMzP/////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////AQET3v8BE97/ARPeEf///wH///8B////Af///wH///8BMzMziTMzM/8zMzP/MzMzif///wH///8B////Af///wEzMzP/MzMz/zMzM/////8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8BARPe7wET3v8BE96J////Af///wH///8B////Af///wEzMzOJMzMz/zMzM/8zMzOJ////Af///wH///8B////ATMzM/8zMzP/MzMz/////wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wEBE96JARPe/wET3u////8B////Af///wH///8B////ATMzM4kzMzP/MzMz/zMzM4n///8B////Af///wH///8BMzMz/zMzM/8zMzP/////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////AQET3hEBE97/ARPe/////wH///8B////Af///wH///8BMzMziTMzM/8zMzP/MzMzif///wH///8B////Af///wEzMzP/MzMz/zMzM/////8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////AQET3v8BE97/ARPeVf///wH///8B////Af///wEzMzOJMzMz/zMzM/8zMzOJ////ATMzM0UzMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzOJ////Af///wH///8B////Af///wH///8B////Af///wH///8BARPeqwET3v8BE96r////Af///wH///8B////ATMzM4kzMzP/MzMz/zMzM4n///8B////ATMzMxEzMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM4n///8B////Af///wH///8B////Af///wH///8B////Af///wEBE95VARPe/wET3v////8B////Af///wH///8BMzMzIzMzM4kzMzOJMzMzI////wH///8B////ATMzMxEzMzP/MzMz/zMzM/8zMzOJMzMziTMzM4kzMzOJMzMzI////wH///8B////Af///wH///8B////Af///wH///8B////Af///wEBE97/ARPe/wET3hH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////ATMzMxEzMzP/MzMz/////wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////AQET3u8BE97/ARPeif///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////ATMzMxEzMzP/////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8BARPeiQET3v8BE97v////Af///wH///8BMzMzETMzM7szMzOr////Af///wH///8B////Af///wH///8B////ATMzMxH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wEBE94RARPe/wET3v////8B////Af///wEzMzO7MzMz/zMzM/8zMzNn////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wEBE97/ARPe/wET3lX///8B////ATMzM6szMzP/MzMz/zMzM1X///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////AQET3qsBE97/ARPeq////wH///8B////ATMzM2czMzNV////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8BARPeVQET3v8BE97/////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////Af///wH///8B////AQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA">
            <style type="text/css">
                * {
                    margin: 0;
                    padding: 0;
                }

                /* The Modal (background) */
                .modal {
                    display: none; /* Hidden by default */
                    position: fixed; /* Stay in place */
                    z-index: 1; /* Sit on top */
                    left: 0;
                    top: 0;
                    width: 100%; /* Full width */
                    height: 100%; /* Full height */
                    overflow: auto; /* Enable scroll if needed */
                    background-color: rgb(0, 0, 0); /* Fallback color */
                    background-color: rgba(0, 0, 0, 0.4); /* Black w/ opacity */
                }

                /* Modal Content/Box */
                .modal-content {
                    background-color: #fefefe;
                    margin: 15% auto; /* 15% from the top and centered */
                    padding: 20px;
                    border: 1px solid #888;
                    width: 80%; /* Could be more or less, depending on screen size */
                }

                /* The Close Button */
                .close {
                    color: #aaa;
                    float: right;
                    font-size: 28px;
                    font-weight: bold;
                }

                .close:hover,
                .close:focus {
                    color: black;
                    text-decoration: none;
                    cursor: pointer;
                }

                ::-webkit-input-placeholder { /* WebKit browsers */
                    color: #999;
                }

                :-moz-placeholder { /* Mozilla Firefox 4 to 18 */
                    color: #999;
                }

                ::-moz-placeholder { /* Mozilla Firefox 19+ */
                    color: #999;
                }

                :-ms-input-placeholder { /* Internet Explorer 10+ */
                    color: #999;
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

                .multisearch-off .hide-if-multisearch-off {
                    display: none;
                }

                html {
                    background: #fff;
                    font-size: 10px;
                    border-top: 20px solid #de1301;
                }

                body {
                    font-family: 'Gill Sans MT', 'Gill Sans', Calibri, sans-serif;
                    font-size: 1.6rem;
                }

                h2,
                h3 {
                    text-transform: uppercase;
                    font-weight: normal;
                    margin: 2.0rem 0 1.0rem;
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
                    background-color: rgba(210, 210, 210, 1);
                    padding: 20px 40px;
                    border: 0;
                    overflow: hidden;
                }

                .row + .row {
                    background-color: rgba(210, 210, 210, .8);
                }

                .row + .row + .row {
                    background-color: rgba(210, 210, 210, .6);
                }

                .row + .row + .row + .row {
                    background-color: rgba(210, 210, 210, .4);
                }

                .row + .row + .row + .row + .row {
                    background-color: rgba(210, 210, 210, .2);
                }

                .row h1 {
                    display: block;
                    font-weight: lighter;
                    margin: 15px 0 20px;
                    float: left;
                }

                .row h1,
                .branding {
                    width: 260px;
                    no-repeat: 0;
                    overflow: hidden;
                }

                h1 span {
                    font-weight: bold;
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
                    border: 2px solid rgba(0, 0, 0, .3);
                }

                @media only screen and (min-width: 1000px) {

                    legend, h1 {
                        min-width: 250px;
                        font-size: 4.0rem;
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
                    font-size: 2.0rem;
                    margin-right: 0;
                    margin-bottom: 15px;
                }

                .fields-large label {
                    white-space: nowrap;
                    margin: 10px 0;
                    display: block;
                }

                .fields-large input[type="text"],
                .fields-large input[type="password"] {
                    width: 100%;
                }

                .label-text {
                    display: block;
                    font-weight: bold;
                }

                .run-script {
                    font-weight: bold;
                    font-size: 120%;
                }

                .dryrun, .liverun {
                    padding-bottom: 20px;
                }

                .liverun {
                    padding-top: 20px;
                }

                .successful-connection {
                    background-color: green;
                    font-weight: bold;
                    display: inline-block;
                    padding: 5px 10px 8px;
                    border: 2px solid green;
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
                    font-weight: bold;
                }

                .table-select {
                    clear: both;
                }

                .field-long,
                .field-medium,
                .field-short {
                    width: 100%;
                }

                .field-long input[type="text"],
                .field-medium input[type="text"],
                .field-short input[type="text"],
                .field-long input[type="password"],
                .field-medium input[type="password"],
                .field-short input[type="password"],
                .field-long input[type="email"],
                .field-medium input[type="email"],
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
                    .field-medium {
                        width: 50%;
                    }

                    .field-short {
                        width: 20%;
                    }
                }

                .description {
                    font-size: 1.8rem;
                    font-style: italic;
                    color: #eee;
                    margin-top: 10px;
                }

                input[type="text"],
                input[type="password"],
                input[type="email"],
                .regex-left,
                .regex-right {
                    background: rgba(255, 255, 255, .7);
                    border: 2px solid rgba(0, 0, 0, .15);
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

                [type="modify-ui"],
                [type="submit"] {
                    padding: 5px 10px 8px;
                    color: #fff;
                    background: #de1301 left center;
                    cursor: pointer;
                    border: 2px solid rgba(0, 0, 0, .15);
                    margin-right: 20px;
                    margin-bottom: 10px;
                    display: inline-block;
                    -webkit-box-sizing: border-box;
                    -moz-box-sizing: border-box;
                    -ms-box-sizing: border-box;
                    -o-box-sizing: border-box;
                    box-sizing: border-box;
                    -webkit-transition: background-color 0.2s ease-in, color 0.2s ease-in, padding-left 0.05s ease-in;
                    -moz-transition: background-color 0.2s ease-in, color 0.2s ease-in, padding-left 0.05s ease-in;
                    -ms-transition: background-color 0.2s ease-in, color 0.2s ease-in, padding-left 0.05s ease-in;
                    transition: background-color 0.2s ease-in, color 0.2s ease-in, padding-left 0.05s ease-in;
                }

                .separator {
                    margin-right: 20px;
                }

                [type="modify-ui"]:focus,
                [type="modify-ui"]:active,
                [type="submit"]:focus,
                [type="submit"]:active {
                    outline: 2px solid #ab1301;
                }

                [type="submit"][disabled],
                [type="submit"][disabled]:hover,
                [type="submit"][disabled]:active,
                [type="submit"][disabled]:focus,
                [type="submit"][disabled]:active:hover {
                    background: #999;
                    color: #ccc;
                    cursor: default;
                    outline: none;
                    padding-left: 10px;
                }

                [type="modify-ui"].active,
                [type="modify-ui"].active:hover,
                [type="modify-ui"].active:active,
                [type="modify-ui"][disabled].active,
                [type="modify-ui"][disabled].active:hover,
                [type="modify-ui"][disabled].active:active,
                [type="modify-ui"][disabled].active:active:hover {
                    outline: none;
                    color: #fff;
                    padding-left: 30px;
                    background: #900;
                }

                [type="submit"].active,
                [type="submit"].active:hover,
                [type="submit"].active:active,
                [type="submit"][disabled].active,
                [type="submit"][disabled].active:hover,
                [type="submit"][disabled].active:active,
                [type="submit"][disabled].active:active:hover {
                    outline: none;
                    color: #fff;
                    background: #900 url('data:image/png;base64,R0lGODlhEAAQAPQAAJkAAP///5sGBufGxsl6evv4+O7Y2KgoKLtWVvXo6M+IiNWYmKMaGsFmZq84OOG2ttuoqAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH+GkNyZWF0ZWQgd2l0aCBhamF4bG9hZC5pbmZvACH5BAAKAAAAIf8LTkVUU0NBUEUyLjADAQAAACwAAAAAEAAQAAAFUCAgjmRpnqUwFGwhKoRgqq2YFMaRGjWA8AbZiIBbjQQ8AmmFUJEQhQGJhaKOrCksgEla+KIkYvC6SJKQOISoNSYdeIk1ayA8ExTyeR3F749CACH5BAAKAAEALAAAAAAQABAAAAVoICCKR9KMaCoaxeCoqEAkRX3AwMHWxQIIjJSAZWgUEgzBwCBAEQpMwIDwY1FHgwJCtOW2UDWYIDyqNVVkUbYr6CK+o2eUMKgWrqKhj0FrEM8jQQALPFA3MAc8CQSAMA5ZBjgqDQmHIyEAIfkEAAoAAgAsAAAAABAAEAAABWAgII4j85Ao2hRIKgrEUBQJLaSHMe8zgQo6Q8sxS7RIhILhBkgumCTZsXkACBC+0cwF2GoLLoFXREDcDlkAojBICRaFLDCOQtQKjmsQSubtDFU/NXcDBHwkaw1cKQ8MiyEAIfkEAAoAAwAsAAAAABAAEAAABVIgII5kaZ6AIJQCMRTFQKiDQx4GrBfGa4uCnAEhQuRgPwCBtwK+kCNFgjh6QlFYgGO7baJ2CxIioSDpwqNggWCGDVVGphly3BkOpXDrKfNm/4AhACH5BAAKAAQALAAAAAAQABAAAAVgICCOZGmeqEAMRTEQwskYbV0Yx7kYSIzQhtgoBxCKBDQCIOcoLBimRiFhSABYU5gIgW01pLUBYkRItAYAqrlhYiwKjiWAcDMWY8QjsCf4DewiBzQ2N1AmKlgvgCiMjSQhACH5BAAKAAUALAAAAAAQABAAAAVfICCOZGmeqEgUxUAIpkA0AMKyxkEiSZEIsJqhYAg+boUFSTAkiBiNHks3sg1ILAfBiS10gyqCg0UaFBCkwy3RYKiIYMAC+RAxiQgYsJdAjw5DN2gILzEEZgVcKYuMJiEAOwAAAAAAAAAAAA==') no-repeat 8px center;
                    padding-left: 30px;
                }

                .submit-group {
                    display: block;
                    margin: 0 0;
                }

                .multi-search {
                    background: none;
                    border: none;
                    margin: 0;
                    padding: 0;
                    color: #de1301;
                    padding-top: 20px;
                    padding-bottom: 35px;
                    font-size: small;
                    font-weight: bold;
                }

                .multi-search:focus {
                    outline: none;
                }

                [type="modify-ui"],
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

                .report li > strong {
                    display: table-cell;
                    padding-right: 20px;
                }

                .report li > span {
                    display: table-cell;
                    padding-right: 20px;
                    white-space: nowrap;
                }

                .report tbody tr:nth-child(2n-1) {
                    background: rgba(0, 0, 0, .1);
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
                    left: 0;
                    top: 0;
                    right: 0;
                    bottom: 0;
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
                    font-size: 2.4rem;
                }

                .changes-overlay h1 {
                    margin: 20px;
                    float: none;
                    font-weight: normal;
                }

                .changes-overlay h1 small {
                    vertical-align: baseline;
                    font-size: 1.4rem;
                    color: #999;
                }

                .changes-overlay .changes {
                    margin: 0 0;
                    overflow: auto;
                    position: absolute;
                    top: 100px;
                    right: 0;
                    left: 0;
                    bottom: 0;
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
                    font-size: 1.6rem;
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
                    font-size: 1.3rem;
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
                    font-size: 2.4rem;
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
                    font-size: 1.4rem;
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
                    border: 2px solid rgba(0, 0, 0, .1);
                    background: #fff;
                    min-height: 380px;
                }

                .product a:hover {
                    border: 2px solid rgba(0, 0, 0, .3);
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
            <script>
                var h = document.getElementsByTagName('html')[0];
                h.className = h.className.replace('no-js', 'js');
            </script>
        </head>
        <body>
        <?php $this->$body(); ?>
        <script>

            /*! jQuery v1.10.2 | (c) 2005, 2013 jQuery Foundation, Inc. | jquery.org/license
     //# sourceMappingURL=jquery-1.10.2.min.map
     */
            (function (e, t) {
                var n, r, i = typeof t, o = e.location, a = e.document, s = a.documentElement, l = e.jQuery, u = e.$,
                    c = {}, p = [], f = "1.10.2", d = p.concat, h = p.push, g = p.slice, m = p.indexOf, y = c.toString,
                    v = c.hasOwnProperty, b = f.trim, x = function (e, t) {
                        return new x.fn.init(e, t, r)
                    }, w = /[+-]?(?:\d*\.|)\d+(?:[eE][+-]?\d+|)/.source, T = /\S+/g,
                    C = /^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, N = /^(?:\s*(<[\w\W]+>)[^>]*|#([\w-]*))$/,
                    k = /^<(\w+)\s*\/?>(?:<\/\1>|)$/, E = /^[\],:{}\s]*$/, S = /(?:^|:|,)(?:\s*\[)+/g,
                    A = /\\(?:["\\\/bfnrt]|u[\da-fA-F]{4})/g,
                    j = /"[^"\\\r\n]*"|true|false|null|-?(?:\d+\.|)\d+(?:[eE][+-]?\d+|)/g, D = /^-ms-/,
                    L = /-([\da-z])/gi, H = function (e, t) {
                        return t.toUpperCase()
                    }, q = function (e) {
                        (a.addEventListener || "load" === e.type || "complete" === a.readyState) && (_(), x.ready())
                    }, _ = function () {
                        a.addEventListener ? (a.removeEventListener("DOMContentLoaded", q, !1), e.removeEventListener("load", q, !1)) : (a.detachEvent("onreadystatechange", q), e.detachEvent("onload", q))
                    };
                x.fn = x.prototype = {
                    jquery: f, constructor: x, init: function (e, n, r) {
                        var i, o;
                        if (!e) return this;
                        if ("string" == typeof e) {
                            if (i = "<" === e.charAt(0) && ">" === e.charAt(e.length - 1) && e.length >= 3 ? [null, e, null] : N.exec(e), !i || !i[1] && n) return !n || n.jquery ? (n || r).find(e) : this.constructor(n).find(e);
                            if (i[1]) {
                                if (n = n instanceof x ? n[0] : n, x.merge(this, x.parseHTML(i[1], n && n.nodeType ? n.ownerDocument || n : a, !0)), k.test(i[1]) && x.isPlainObject(n)) for (i in n) x.isFunction(this[i]) ? this[i](n[i]) : this.attr(i, n[i]);
                                return this
                            }
                            if (o = a.getElementById(i[2]), o && o.parentNode) {
                                if (o.id !== i[2]) return r.find(e);
                                this.length = 1, this[0] = o
                            }
                            return this.context = a, this.selector = e, this
                        }
                        return e.nodeType ? (this.context = this[0] = e, this.length = 1, this) : x.isFunction(e) ? r.ready(e) : (e.selector !== t && (this.selector = e.selector, this.context = e.context), x.makeArray(e, this))
                    }, selector: "", length: 0, toArray: function () {
                        return g.call(this)
                    }, get: function (e) {
                        return null == e ? this.toArray() : 0 > e ? this[this.length + e] : this[e]
                    }, pushStack: function (e) {
                        var t = x.merge(this.constructor(), e);
                        return t.prevObject = this, t.context = this.context, t
                    }, each: function (e, t) {
                        return x.each(this, e, t)
                    }, ready: function (e) {
                        return x.ready.promise().done(e), this
                    }, slice: function () {
                        return this.pushStack(g.apply(this, arguments))
                    }, first: function () {
                        return this.eq(0)
                    }, last: function () {
                        return this.eq(-1)
                    }, eq: function (e) {
                        var t = this.length, n = +e + (0 > e ? t : 0);
                        return this.pushStack(n >= 0 && t > n ? [this[n]] : [])
                    }, map: function (e) {
                        return this.pushStack(x.map(this, function (t, n) {
                            return e.call(t, n, t)
                        }))
                    }, end: function () {
                        return this.prevObject || this.constructor(null)
                    }, push: h, sort: [].sort, splice: [].splice
                }, x.fn.init.prototype = x.fn, x.extend = x.fn.extend = function () {
                    var e, n, r, i, o, a, s = arguments[0] || {}, l = 1, u = arguments.length, c = !1;
                    for ("boolean" == typeof s && (c = s, s = arguments[1] || {}, l = 2), "object" == typeof s || x.isFunction(s) || (s = {}), u === l && (s = this, --l); u > l; l++) if (null != (o = arguments[l])) for (i in o) e = s[i], r = o[i], s !== r && (c && r && (x.isPlainObject(r) || (n = x.isArray(r))) ? (n ? (n = !1, a = e && x.isArray(e) ? e : []) : a = e && x.isPlainObject(e) ? e : {}, s[i] = x.extend(c, a, r)) : r !== t && (s[i] = r));
                    return s
                }, x.extend({
                    expando: "jQuery" + (f + Math.random()).replace(/\D/g, ""), noConflict: function (t) {
                        return e.$ === x && (e.$ = u), t && e.jQuery === x && (e.jQuery = l), x
                    }, isReady: !1, readyWait: 1, holdReady: function (e) {
                        e ? x.readyWait++ : x.ready(!0)
                    }, ready: function (e) {
                        if (e === !0 ? !--x.readyWait : !x.isReady) {
                            if (!a.body) return setTimeout(x.ready);
                            x.isReady = !0, e !== !0 && --x.readyWait > 0 || (n.resolveWith(a, [x]), x.fn.trigger && x(a).trigger("ready").off("ready"))
                        }
                    }, isFunction: function (e) {
                        return "function" === x.type(e)
                    }, isArray: Array.isArray || function (e) {
                        return "array" === x.type(e)
                    }, isWindow: function (e) {
                        return null != e && e == e.window
                    }, isNumeric: function (e) {
                        return !isNaN(parseFloat(e)) && isFinite(e)
                    }, type: function (e) {
                        return null == e ? e + "" : "object" == typeof e || "function" == typeof e ? c[y.call(e)] || "object" : typeof e
                    }, isPlainObject: function (e) {
                        var n;
                        if (!e || "object" !== x.type(e) || e.nodeType || x.isWindow(e)) return !1;
                        try {
                            if (e.constructor && !v.call(e, "constructor") && !v.call(e.constructor.prototype, "isPrototypeOf")) return !1
                        } catch (r) {
                            return !1
                        }
                        if (x.support.ownLast) for (n in e) return v.call(e, n);
                        for (n in e) ;
                        return n === t || v.call(e, n)
                    }, isEmptyObject: function (e) {
                        var t;
                        for (t in e) return !1;
                        return !0
                    }, error: function (e) {
                        throw Error(e)
                    }, parseHTML: function (e, t, n) {
                        if (!e || "string" != typeof e) return null;
                        "boolean" == typeof t && (n = t, t = !1), t = t || a;
                        var r = k.exec(e), i = !n && [];
                        return r ? [t.createElement(r[1])] : (r = x.buildFragment([e], t, i), i && x(i).remove(), x.merge([], r.childNodes))
                    }, parseJSON: function (n) {
                        return e.JSON && e.JSON.parse ? e.JSON.parse(n) : null === n ? n : "string" == typeof n && (n = x.trim(n), n && E.test(n.replace(A, "@").replace(j, "]").replace(S, ""))) ? Function("return " + n)() : (x.error("Invalid JSON: " + n), t)
                    }, parseXML: function (n) {
                        var r, i;
                        if (!n || "string" != typeof n) return null;
                        try {
                            e.DOMParser ? (i = new DOMParser, r = i.parseFromString(n, "text/xml")) : (r = new ActiveXObject("Microsoft.XMLDOM"), r.async = "false", r.loadXML(n))
                        } catch (o) {
                            r = t
                        }
                        return r && r.documentElement && !r.getElementsByTagName("parsererror").length || x.error("Invalid XML: " + n), r
                    }, noop: function () {
                    }, globalEval: function (t) {
                        t && x.trim(t) && (e.execScript || function (t) {
                            e.eval.call(e, t)
                        })(t)
                    }, camelCase: function (e) {
                        return e.replace(D, "ms-").replace(L, H)
                    }, nodeName: function (e, t) {
                        return e.nodeName && e.nodeName.toLowerCase() === t.toLowerCase()
                    }, each: function (e, t, n) {
                        var r, i = 0, o = e.length, a = M(e);
                        if (n) {
                            if (a) {
                                for (; o > i; i++) if (r = t.apply(e[i], n), r === !1) break
                            } else for (i in e) if (r = t.apply(e[i], n), r === !1) break
                        } else if (a) {
                            for (; o > i; i++) if (r = t.call(e[i], i, e[i]), r === !1) break
                        } else for (i in e) if (r = t.call(e[i], i, e[i]), r === !1) break;
                        return e
                    }, trim: b && !b.call("\ufeff\u00a0") ? function (e) {
                        return null == e ? "" : b.call(e)
                    } : function (e) {
                        return null == e ? "" : (e + "").replace(C, "")
                    }, makeArray: function (e, t) {
                        var n = t || [];
                        return null != e && (M(Object(e)) ? x.merge(n, "string" == typeof e ? [e] : e) : h.call(n, e)), n
                    }, inArray: function (e, t, n) {
                        var r;
                        if (t) {
                            if (m) return m.call(t, e, n);
                            for (r = t.length, n = n ? 0 > n ? Math.max(0, r + n) : n : 0; r > n; n++) if (n in t && t[n] === e) return n
                        }
                        return -1
                    }, merge: function (e, n) {
                        var r = n.length, i = e.length, o = 0;
                        if ("number" == typeof r) for (; r > o; o++) e[i++] = n[o]; else while (n[o] !== t) e[i++] = n[o++];
                        return e.length = i, e
                    }, grep: function (e, t, n) {
                        var r, i = [], o = 0, a = e.length;
                        for (n = !!n; a > o; o++) r = !!t(e[o], o), n !== r && i.push(e[o]);
                        return i
                    }, map: function (e, t, n) {
                        var r, i = 0, o = e.length, a = M(e), s = [];
                        if (a) for (; o > i; i++) r = t(e[i], i, n), null != r && (s[s.length] = r); else for (i in e) r = t(e[i], i, n), null != r && (s[s.length] = r);
                        return d.apply([], s)
                    }, guid: 1, proxy: function (e, n) {
                        var r, i, o;
                        return "string" == typeof n && (o = e[n], n = e, e = o), x.isFunction(e) ? (r = g.call(arguments, 2), i = function () {
                            return e.apply(n || this, r.concat(g.call(arguments)))
                        }, i.guid = e.guid = e.guid || x.guid++, i) : t
                    }, access: function (e, n, r, i, o, a, s) {
                        var l = 0, u = e.length, c = null == r;
                        if ("object" === x.type(r)) {
                            o = !0;
                            for (l in r) x.access(e, n, l, r[l], !0, a, s)
                        } else if (i !== t && (o = !0, x.isFunction(i) || (s = !0), c && (s ? (n.call(e, i), n = null) : (c = n, n = function (e, t, n) {
                            return c.call(x(e), n)
                        })), n)) for (; u > l; l++) n(e[l], r, s ? i : i.call(e[l], l, n(e[l], r)));
                        return o ? e : c ? n.call(e) : u ? n(e[0], r) : a
                    }, now: function () {
                        return (new Date).getTime()
                    }, swap: function (e, t, n, r) {
                        var i, o, a = {};
                        for (o in t) a[o] = e.style[o], e.style[o] = t[o];
                        i = n.apply(e, r || []);
                        for (o in t) e.style[o] = a[o];
                        return i
                    }
                }), x.ready.promise = function (t) {
                    if (!n) if (n = x.Deferred(), "complete" === a.readyState) setTimeout(x.ready); else if (a.addEventListener) a.addEventListener("DOMContentLoaded", q, !1), e.addEventListener("load", q, !1); else {
                        a.attachEvent("onreadystatechange", q), e.attachEvent("onload", q);
                        var r = !1;
                        try {
                            r = null == e.frameElement && a.documentElement
                        } catch (i) {
                        }
                        r && r.doScroll && function o() {
                            if (!x.isReady) {
                                try {
                                    r.doScroll("left")
                                } catch (e) {
                                    return setTimeout(o, 50)
                                }
                                _(), x.ready()
                            }
                        }()
                    }
                    return n.promise(t)
                }, x.each("Boolean Number String Function Array Date RegExp Object Error".split(" "), function (e, t) {
                    c["[object " + t + "]"] = t.toLowerCase()
                });

                function M(e) {
                    var t = e.length, n = x.type(e);
                    return x.isWindow(e) ? !1 : 1 === e.nodeType && t ? !0 : "array" === n || "function" !== n && (0 === t || "number" == typeof t && t > 0 && t - 1 in e)
                }

                r = x(a), function (e, t) {
                    var n, r, i, o, a, s, l, u, c, p, f, d, h, g, m, y, v, b = "sizzle" + -new Date, w = e.document,
                        T = 0, C = 0, N = st(), k = st(), E = st(), S = !1, A = function (e, t) {
                            return e === t ? (S = !0, 0) : 0
                        }, j = typeof t, D = 1 << 31, L = {}.hasOwnProperty, H = [], q = H.pop, _ = H.push, M = H.push,
                        O = H.slice, F = H.indexOf || function (e) {
                            var t = 0, n = this.length;
                            for (; n > t; t++) if (this[t] === e) return t;
                            return -1
                        },
                        B = "checked|selected|async|autofocus|autoplay|controls|defer|disabled|hidden|ismap|loop|multiple|open|readonly|required|scoped",
                        P = "[\\x20\\t\\r\\n\\f]", R = "(?:\\\\.|[\\w-]|[^\\x00-\\xa0])+", W = R.replace("w", "w#"),
                        $ = "\\[" + P + "*(" + R + ")" + P + "*(?:([*^$|!~]?=)" + P + "*(?:(['\"])((?:\\\\.|[^\\\\])*?)\\3|(" + W + ")|)|)" + P + "*\\]",
                        I = ":(" + R + ")(?:\\(((['\"])((?:\\\\.|[^\\\\])*?)\\3|((?:\\\\.|[^\\\\()[\\]]|" + $.replace(3, 8) + ")*)|.*)\\)|)",
                        z = RegExp("^" + P + "+|((?:^|[^\\\\])(?:\\\\.)*)" + P + "+$", "g"),
                        X = RegExp("^" + P + "*," + P + "*"), U = RegExp("^" + P + "*([>+~]|" + P + ")" + P + "*"),
                        V = RegExp(P + "*[+~]"), Y = RegExp("=" + P + "*([^\\]'\"]*)" + P + "*\\]", "g"), J = RegExp(I),
                        G = RegExp("^" + W + "$"), Q = {
                            ID: RegExp("^#(" + R + ")"),
                            CLASS: RegExp("^\\.(" + R + ")"),
                            TAG: RegExp("^(" + R.replace("w", "w*") + ")"),
                            ATTR: RegExp("^" + $),
                            PSEUDO: RegExp("^" + I),
                            CHILD: RegExp("^:(only|first|last|nth|nth-last)-(child|of-type)(?:\\(" + P + "*(even|odd|(([+-]|)(\\d*)n|)" + P + "*(?:([+-]|)" + P + "*(\\d+)|))" + P + "*\\)|)", "i"),
                            bool: RegExp("^(?:" + B + ")$", "i"),
                            needsContext: RegExp("^" + P + "*[>+~]|:(even|odd|eq|gt|lt|nth|first|last)(?:\\(" + P + "*((?:-\\d)?\\d*)" + P + "*\\)|)(?=[^-]|$)", "i")
                        }, K = /^[^{]+\{\s*\[native \w/, Z = /^(?:#([\w-]+)|(\w+)|\.([\w-]+))$/,
                        et = /^(?:input|select|textarea|button)$/i, tt = /^h\d$/i, nt = /'|\\/g,
                        rt = RegExp("\\\\([\\da-f]{1,6}" + P + "?|(" + P + ")|.)", "ig"), it = function (e, t, n) {
                            var r = "0x" + t - 65536;
                            return r !== r || n ? t : 0 > r ? String.fromCharCode(r + 65536) : String.fromCharCode(55296 | r >> 10, 56320 | 1023 & r)
                        };
                    try {
                        M.apply(H = O.call(w.childNodes), w.childNodes), H[w.childNodes.length].nodeType
                    } catch (ot) {
                        M = {
                            apply: H.length ? function (e, t) {
                                _.apply(e, O.call(t))
                            } : function (e, t) {
                                var n = e.length, r = 0;
                                while (e[n++] = t[r++]) ;
                                e.length = n - 1
                            }
                        }
                    }

                    function at(e, t, n, i) {
                        var o, a, s, l, u, c, d, m, y, x;
                        if ((t ? t.ownerDocument || t : w) !== f && p(t), t = t || f, n = n || [], !e || "string" != typeof e) return n;
                        if (1 !== (l = t.nodeType) && 9 !== l) return [];
                        if (h && !i) {
                            if (o = Z.exec(e)) if (s = o[1]) {
                                if (9 === l) {
                                    if (a = t.getElementById(s), !a || !a.parentNode) return n;
                                    if (a.id === s) return n.push(a), n
                                } else if (t.ownerDocument && (a = t.ownerDocument.getElementById(s)) && v(t, a) && a.id === s) return n.push(a), n
                            } else {
                                if (o[2]) return M.apply(n, t.getElementsByTagName(e)), n;
                                if ((s = o[3]) && r.getElementsByClassName && t.getElementsByClassName) return M.apply(n, t.getElementsByClassName(s)), n
                            }
                            if (r.qsa && (!g || !g.test(e))) {
                                if (m = d = b, y = t, x = 9 === l && e, 1 === l && "object" !== t.nodeName.toLowerCase()) {
                                    c = mt(e), (d = t.getAttribute("id")) ? m = d.replace(nt, "\\$&") : t.setAttribute("id", m), m = "[id='" + m + "'] ", u = c.length;
                                    while (u--) c[u] = m + yt(c[u]);
                                    y = V.test(e) && t.parentNode || t, x = c.join(",")
                                }
                                if (x) try {
                                    return M.apply(n, y.querySelectorAll(x)), n
                                } catch (T) {
                                } finally {
                                    d || t.removeAttribute("id")
                                }
                            }
                        }
                        return kt(e.replace(z, "$1"), t, n, i)
                    }

                    function st() {
                        var e = [];

                        function t(n, r) {
                            return e.push(n += " ") > o.cacheLength && delete t[e.shift()], t[n] = r
                        }

                        return t
                    }

                    function lt(e) {
                        return e[b] = !0, e
                    }

                    function ut(e) {
                        var t = f.createElement("div");
                        try {
                            return !!e(t)
                        } catch (n) {
                            return !1
                        } finally {
                            t.parentNode && t.parentNode.removeChild(t), t = null
                        }
                    }

                    function ct(e, t) {
                        var n = e.split("|"), r = e.length;
                        while (r--) o.attrHandle[n[r]] = t
                    }

                    function pt(e, t) {
                        var n = t && e,
                            r = n && 1 === e.nodeType && 1 === t.nodeType && (~t.sourceIndex || D) - (~e.sourceIndex || D);
                        if (r) return r;
                        if (n) while (n = n.nextSibling) if (n === t) return -1;
                        return e ? 1 : -1
                    }

                    function ft(e) {
                        return function (t) {
                            var n = t.nodeName.toLowerCase();
                            return "input" === n && t.type === e
                        }
                    }

                    function dt(e) {
                        return function (t) {
                            var n = t.nodeName.toLowerCase();
                            return ("input" === n || "button" === n) && t.type === e
                        }
                    }

                    function ht(e) {
                        return lt(function (t) {
                            return t = +t, lt(function (n, r) {
                                var i, o = e([], n.length, t), a = o.length;
                                while (a--) n[i = o[a]] && (n[i] = !(r[i] = n[i]))
                            })
                        })
                    }

                    s = at.isXML = function (e) {
                        var t = e && (e.ownerDocument || e).documentElement;
                        return t ? "HTML" !== t.nodeName : !1
                    }, r = at.support = {}, p = at.setDocument = function (e) {
                        var n = e ? e.ownerDocument || e : w, i = n.defaultView;
                        return n !== f && 9 === n.nodeType && n.documentElement ? (f = n, d = n.documentElement, h = !s(n), i && i.attachEvent && i !== i.top && i.attachEvent("onbeforeunload", function () {
                            p()
                        }), r.attributes = ut(function (e) {
                            return e.className = "i", !e.getAttribute("className")
                        }), r.getElementsByTagName = ut(function (e) {
                            return e.appendChild(n.createComment("")), !e.getElementsByTagName("*").length
                        }), r.getElementsByClassName = ut(function (e) {
                            return e.innerHTML = "<div class='a'></div><div class='a i'></div>", e.firstChild.className = "i", 2 === e.getElementsByClassName("i").length
                        }), r.getById = ut(function (e) {
                            return d.appendChild(e).id = b, !n.getElementsByName || !n.getElementsByName(b).length
                        }), r.getById ? (o.find.ID = function (e, t) {
                            if (typeof t.getElementById !== j && h) {
                                var n = t.getElementById(e);
                                return n && n.parentNode ? [n] : []
                            }
                        }, o.filter.ID = function (e) {
                            var t = e.replace(rt, it);
                            return function (e) {
                                return e.getAttribute("id") === t
                            }
                        }) : (delete o.find.ID, o.filter.ID = function (e) {
                            var t = e.replace(rt, it);
                            return function (e) {
                                var n = typeof e.getAttributeNode !== j && e.getAttributeNode("id");
                                return n && n.value === t
                            }
                        }), o.find.TAG = r.getElementsByTagName ? function (e, n) {
                            return typeof n.getElementsByTagName !== j ? n.getElementsByTagName(e) : t
                        } : function (e, t) {
                            var n, r = [], i = 0, o = t.getElementsByTagName(e);
                            if ("*" === e) {
                                while (n = o[i++]) 1 === n.nodeType && r.push(n);
                                return r
                            }
                            return o
                        }, o.find.CLASS = r.getElementsByClassName && function (e, n) {
                            return typeof n.getElementsByClassName !== j && h ? n.getElementsByClassName(e) : t
                        }, m = [], g = [], (r.qsa = K.test(n.querySelectorAll)) && (ut(function (e) {
                            e.innerHTML = "<select><option selected=''></option></select>", e.querySelectorAll("[selected]").length || g.push("\\[" + P + "*(?:value|" + B + ")"), e.querySelectorAll(":checked").length || g.push(":checked")
                        }), ut(function (e) {
                            var t = n.createElement("input");
                            t.setAttribute("type", "hidden"), e.appendChild(t).setAttribute("t", ""), e.querySelectorAll("[t^='']").length && g.push("[*^$]=" + P + "*(?:''|\"\")"), e.querySelectorAll(":enabled").length || g.push(":enabled", ":disabled"), e.querySelectorAll("*,:x"), g.push(",.*:")
                        })), (r.matchesSelector = K.test(y = d.webkitMatchesSelector || d.mozMatchesSelector || d.oMatchesSelector || d.msMatchesSelector)) && ut(function (e) {
                            r.disconnectedMatch = y.call(e, "div"), y.call(e, "[s!='']:x"), m.push("!=", I)
                        }), g = g.length && RegExp(g.join("|")), m = m.length && RegExp(m.join("|")), v = K.test(d.contains) || d.compareDocumentPosition ? function (e, t) {
                            var n = 9 === e.nodeType ? e.documentElement : e, r = t && t.parentNode;
                            return e === r || !(!r || 1 !== r.nodeType || !(n.contains ? n.contains(r) : e.compareDocumentPosition && 16 & e.compareDocumentPosition(r)))
                        } : function (e, t) {
                            if (t) while (t = t.parentNode) if (t === e) return !0;
                            return !1
                        }, A = d.compareDocumentPosition ? function (e, t) {
                            if (e === t) return S = !0, 0;
                            var i = t.compareDocumentPosition && e.compareDocumentPosition && e.compareDocumentPosition(t);
                            return i ? 1 & i || !r.sortDetached && t.compareDocumentPosition(e) === i ? e === n || v(w, e) ? -1 : t === n || v(w, t) ? 1 : c ? F.call(c, e) - F.call(c, t) : 0 : 4 & i ? -1 : 1 : e.compareDocumentPosition ? -1 : 1
                        } : function (e, t) {
                            var r, i = 0, o = e.parentNode, a = t.parentNode, s = [e], l = [t];
                            if (e === t) return S = !0, 0;
                            if (!o || !a) return e === n ? -1 : t === n ? 1 : o ? -1 : a ? 1 : c ? F.call(c, e) - F.call(c, t) : 0;
                            if (o === a) return pt(e, t);
                            r = e;
                            while (r = r.parentNode) s.unshift(r);
                            r = t;
                            while (r = r.parentNode) l.unshift(r);
                            while (s[i] === l[i]) i++;
                            return i ? pt(s[i], l[i]) : s[i] === w ? -1 : l[i] === w ? 1 : 0
                        }, n) : f
                    }, at.matches = function (e, t) {
                        return at(e, null, null, t)
                    }, at.matchesSelector = function (e, t) {
                        if ((e.ownerDocument || e) !== f && p(e), t = t.replace(Y, "='$1']"), !(!r.matchesSelector || !h || m && m.test(t) || g && g.test(t))) try {
                            var n = y.call(e, t);
                            if (n || r.disconnectedMatch || e.document && 11 !== e.document.nodeType) return n
                        } catch (i) {
                        }
                        return at(t, f, null, [e]).length > 0
                    }, at.contains = function (e, t) {
                        return (e.ownerDocument || e) !== f && p(e), v(e, t)
                    }, at.attr = function (e, n) {
                        (e.ownerDocument || e) !== f && p(e);
                        var i = o.attrHandle[n.toLowerCase()],
                            a = i && L.call(o.attrHandle, n.toLowerCase()) ? i(e, n, !h) : t;
                        return a === t ? r.attributes || !h ? e.getAttribute(n) : (a = e.getAttributeNode(n)) && a.specified ? a.value : null : a
                    }, at.error = function (e) {
                        throw Error("Syntax error, unrecognized expression: " + e)
                    }, at.uniqueSort = function (e) {
                        var t, n = [], i = 0, o = 0;
                        if (S = !r.detectDuplicates, c = !r.sortStable && e.slice(0), e.sort(A), S) {
                            while (t = e[o++]) t === e[o] && (i = n.push(o));
                            while (i--) e.splice(n[i], 1)
                        }
                        return e
                    }, a = at.getText = function (e) {
                        var t, n = "", r = 0, i = e.nodeType;
                        if (i) {
                            if (1 === i || 9 === i || 11 === i) {
                                if ("string" == typeof e.textContent) return e.textContent;
                                for (e = e.firstChild; e; e = e.nextSibling) n += a(e)
                            } else if (3 === i || 4 === i) return e.nodeValue
                        } else for (; t = e[r]; r++) n += a(t);
                        return n
                    }, o = at.selectors = {
                        cacheLength: 50,
                        createPseudo: lt,
                        match: Q,
                        attrHandle: {},
                        find: {},
                        relative: {
                            ">": {dir: "parentNode", first: !0},
                            " ": {dir: "parentNode"},
                            "+": {dir: "previousSibling", first: !0},
                            "~": {dir: "previousSibling"}
                        },
                        preFilter: {
                            ATTR: function (e) {
                                return e[1] = e[1].replace(rt, it), e[3] = (e[4] || e[5] || "").replace(rt, it), "~=" === e[2] && (e[3] = " " + e[3] + " "), e.slice(0, 4)
                            }, CHILD: function (e) {
                                return e[1] = e[1].toLowerCase(), "nth" === e[1].slice(0, 3) ? (e[3] || at.error(e[0]), e[4] = +(e[4] ? e[5] + (e[6] || 1) : 2 * ("even" === e[3] || "odd" === e[3])), e[5] = +(e[7] + e[8] || "odd" === e[3])) : e[3] && at.error(e[0]), e
                            }, PSEUDO: function (e) {
                                var n, r = !e[5] && e[2];
                                return Q.CHILD.test(e[0]) ? null : (e[3] && e[4] !== t ? e[2] = e[4] : r && J.test(r) && (n = mt(r, !0)) && (n = r.indexOf(")", r.length - n) - r.length) && (e[0] = e[0].slice(0, n), e[2] = r.slice(0, n)), e.slice(0, 3))
                            }
                        },
                        filter: {
                            TAG: function (e) {
                                var t = e.replace(rt, it).toLowerCase();
                                return "*" === e ? function () {
                                    return !0
                                } : function (e) {
                                    return e.nodeName && e.nodeName.toLowerCase() === t
                                }
                            }, CLASS: function (e) {
                                var t = N[e + " "];
                                return t || (t = RegExp("(^|" + P + ")" + e + "(" + P + "|$)")) && N(e, function (e) {
                                    return t.test("string" == typeof e.className && e.className || typeof e.getAttribute !== j && e.getAttribute("class") || "")
                                })
                            }, ATTR: function (e, t, n) {
                                return function (r) {
                                    var i = at.attr(r, e);
                                    return null == i ? "!=" === t : t ? (i += "", "=" === t ? i === n : "!=" === t ? i !== n : "^=" === t ? n && 0 === i.indexOf(n) : "*=" === t ? n && i.indexOf(n) > -1 : "$=" === t ? n && i.slice(-n.length) === n : "~=" === t ? (" " + i + " ").indexOf(n) > -1 : "|=" === t ? i === n || i.slice(0, n.length + 1) === n + "-" : !1) : !0
                                }
                            }, CHILD: function (e, t, n, r, i) {
                                var o = "nth" !== e.slice(0, 3), a = "last" !== e.slice(-4), s = "of-type" === t;
                                return 1 === r && 0 === i ? function (e) {
                                    return !!e.parentNode
                                } : function (t, n, l) {
                                    var u, c, p, f, d, h, g = o !== a ? "nextSibling" : "previousSibling",
                                        m = t.parentNode, y = s && t.nodeName.toLowerCase(), v = !l && !s;
                                    if (m) {
                                        if (o) {
                                            while (g) {
                                                p = t;
                                                while (p = p[g]) if (s ? p.nodeName.toLowerCase() === y : 1 === p.nodeType) return !1;
                                                h = g = "only" === e && !h && "nextSibling"
                                            }
                                            return !0
                                        }
                                        if (h = [a ? m.firstChild : m.lastChild], a && v) {
                                            c = m[b] || (m[b] = {}), u = c[e] || [], d = u[0] === T && u[1], f = u[0] === T && u[2], p = d && m.childNodes[d];
                                            while (p = ++d && p && p[g] || (f = d = 0) || h.pop()) if (1 === p.nodeType && ++f && p === t) {
                                                c[e] = [T, d, f];
                                                break
                                            }
                                        } else if (v && (u = (t[b] || (t[b] = {}))[e]) && u[0] === T) f = u[1]; else while (p = ++d && p && p[g] || (f = d = 0) || h.pop()) if ((s ? p.nodeName.toLowerCase() === y : 1 === p.nodeType) && ++f && (v && ((p[b] || (p[b] = {}))[e] = [T, f]), p === t)) break;
                                        return f -= i, f === r || 0 === f % r && f / r >= 0
                                    }
                                }
                            }, PSEUDO: function (e, t) {
                                var n,
                                    r = o.pseudos[e] || o.setFilters[e.toLowerCase()] || at.error("unsupported pseudo: " + e);
                                return r[b] ? r(t) : r.length > 1 ? (n = [e, e, "", t], o.setFilters.hasOwnProperty(e.toLowerCase()) ? lt(function (e, n) {
                                    var i, o = r(e, t), a = o.length;
                                    while (a--) i = F.call(e, o[a]), e[i] = !(n[i] = o[a])
                                }) : function (e) {
                                    return r(e, 0, n)
                                }) : r
                            }
                        },
                        pseudos: {
                            not: lt(function (e) {
                                var t = [], n = [], r = l(e.replace(z, "$1"));
                                return r[b] ? lt(function (e, t, n, i) {
                                    var o, a = r(e, null, i, []), s = e.length;
                                    while (s--) (o = a[s]) && (e[s] = !(t[s] = o))
                                }) : function (e, i, o) {
                                    return t[0] = e, r(t, null, o, n), !n.pop()
                                }
                            }), has: lt(function (e) {
                                return function (t) {
                                    return at(e, t).length > 0
                                }
                            }), contains: lt(function (e) {
                                return function (t) {
                                    return (t.textContent || t.innerText || a(t)).indexOf(e) > -1
                                }
                            }), lang: lt(function (e) {
                                return G.test(e || "") || at.error("unsupported lang: " + e), e = e.replace(rt, it).toLowerCase(), function (t) {
                                    var n;
                                    do if (n = h ? t.lang : t.getAttribute("xml:lang") || t.getAttribute("lang")) return n = n.toLowerCase(), n === e || 0 === n.indexOf(e + "-"); while ((t = t.parentNode) && 1 === t.nodeType);
                                    return !1
                                }
                            }), target: function (t) {
                                var n = e.location && e.location.hash;
                                return n && n.slice(1) === t.id
                            }, root: function (e) {
                                return e === d
                            }, focus: function (e) {
                                return e === f.activeElement && (!f.hasFocus || f.hasFocus()) && !!(e.type || e.href || ~e.tabIndex)
                            }, enabled: function (e) {
                                return e.disabled === !1
                            }, disabled: function (e) {
                                return e.disabled === !0
                            }, checked: function (e) {
                                var t = e.nodeName.toLowerCase();
                                return "input" === t && !!e.checked || "option" === t && !!e.selected
                            }, selected: function (e) {
                                return e.parentNode && e.parentNode.selectedIndex, e.selected === !0
                            }, empty: function (e) {
                                for (e = e.firstChild; e; e = e.nextSibling) if (e.nodeName > "@" || 3 === e.nodeType || 4 === e.nodeType) return !1;
                                return !0
                            }, parent: function (e) {
                                return !o.pseudos.empty(e)
                            }, header: function (e) {
                                return tt.test(e.nodeName)
                            }, input: function (e) {
                                return et.test(e.nodeName)
                            }, button: function (e) {
                                var t = e.nodeName.toLowerCase();
                                return "input" === t && "button" === e.type || "button" === t
                            }, text: function (e) {
                                var t;
                                return "input" === e.nodeName.toLowerCase() && "text" === e.type && (null == (t = e.getAttribute("type")) || t.toLowerCase() === e.type)
                            }, first: ht(function () {
                                return [0]
                            }), last: ht(function (e, t) {
                                return [t - 1]
                            }), eq: ht(function (e, t, n) {
                                return [0 > n ? n + t : n]
                            }), even: ht(function (e, t) {
                                var n = 0;
                                for (; t > n; n += 2) e.push(n);
                                return e
                            }), odd: ht(function (e, t) {
                                var n = 1;
                                for (; t > n; n += 2) e.push(n);
                                return e
                            }), lt: ht(function (e, t, n) {
                                var r = 0 > n ? n + t : n;
                                for (; --r >= 0;) e.push(r);
                                return e
                            }), gt: ht(function (e, t, n) {
                                var r = 0 > n ? n + t : n;
                                for (; t > ++r;) e.push(r);
                                return e
                            })
                        }
                    }, o.pseudos.nth = o.pseudos.eq;
                    for (n in {radio: !0, checkbox: !0, file: !0, password: !0, image: !0}) o.pseudos[n] = ft(n);
                    for (n in {submit: !0, reset: !0}) o.pseudos[n] = dt(n);

                    function gt() {
                    }

                    gt.prototype = o.filters = o.pseudos, o.setFilters = new gt;

                    function mt(e, t) {
                        var n, r, i, a, s, l, u, c = k[e + " "];
                        if (c) return t ? 0 : c.slice(0);
                        s = e, l = [], u = o.preFilter;
                        while (s) {
                            (!n || (r = X.exec(s))) && (r && (s = s.slice(r[0].length) || s), l.push(i = [])), n = !1, (r = U.exec(s)) && (n = r.shift(), i.push({
                                value: n,
                                type: r[0].replace(z, " ")
                            }), s = s.slice(n.length));
                            for (a in o.filter) !(r = Q[a].exec(s)) || u[a] && !(r = u[a](r)) || (n = r.shift(), i.push({
                                value: n,
                                type: a,
                                matches: r
                            }), s = s.slice(n.length));
                            if (!n) break
                        }
                        return t ? s.length : s ? at.error(e) : k(e, l).slice(0)
                    }

                    function yt(e) {
                        var t = 0, n = e.length, r = "";
                        for (; n > t; t++) r += e[t].value;
                        return r
                    }

                    function vt(e, t, n) {
                        var r = t.dir, o = n && "parentNode" === r, a = C++;
                        return t.first ? function (t, n, i) {
                            while (t = t[r]) if (1 === t.nodeType || o) return e(t, n, i)
                        } : function (t, n, s) {
                            var l, u, c, p = T + " " + a;
                            if (s) {
                                while (t = t[r]) if ((1 === t.nodeType || o) && e(t, n, s)) return !0
                            } else while (t = t[r]) if (1 === t.nodeType || o) if (c = t[b] || (t[b] = {}), (u = c[r]) && u[0] === p) {
                                if ((l = u[1]) === !0 || l === i) return l === !0
                            } else if (u = c[r] = [p], u[1] = e(t, n, s) || i, u[1] === !0) return !0
                        }
                    }

                    function bt(e) {
                        return e.length > 1 ? function (t, n, r) {
                            var i = e.length;
                            while (i--) if (!e[i](t, n, r)) return !1;
                            return !0
                        } : e[0]
                    }

                    function xt(e, t, n, r, i) {
                        var o, a = [], s = 0, l = e.length, u = null != t;
                        for (; l > s; s++) (o = e[s]) && (!n || n(o, r, i)) && (a.push(o), u && t.push(s));
                        return a
                    }

                    function wt(e, t, n, r, i, o) {
                        return r && !r[b] && (r = wt(r)), i && !i[b] && (i = wt(i, o)), lt(function (o, a, s, l) {
                            var u, c, p, f = [], d = [], h = a.length, g = o || Nt(t || "*", s.nodeType ? [s] : s, []),
                                m = !e || !o && t ? g : xt(g, f, e, s, l), y = n ? i || (o ? e : h || r) ? [] : a : m;
                            if (n && n(m, y, s, l), r) {
                                u = xt(y, d), r(u, [], s, l), c = u.length;
                                while (c--) (p = u[c]) && (y[d[c]] = !(m[d[c]] = p))
                            }
                            if (o) {
                                if (i || e) {
                                    if (i) {
                                        u = [], c = y.length;
                                        while (c--) (p = y[c]) && u.push(m[c] = p);
                                        i(null, y = [], u, l)
                                    }
                                    c = y.length;
                                    while (c--) (p = y[c]) && (u = i ? F.call(o, p) : f[c]) > -1 && (o[u] = !(a[u] = p))
                                }
                            } else y = xt(y === a ? y.splice(h, y.length) : y), i ? i(null, a, y, l) : M.apply(a, y)
                        })
                    }

                    function Tt(e) {
                        var t, n, r, i = e.length, a = o.relative[e[0].type], s = a || o.relative[" "], l = a ? 1 : 0,
                            c = vt(function (e) {
                                return e === t
                            }, s, !0), p = vt(function (e) {
                                return F.call(t, e) > -1
                            }, s, !0), f = [function (e, n, r) {
                                return !a && (r || n !== u) || ((t = n).nodeType ? c(e, n, r) : p(e, n, r))
                            }];
                        for (; i > l; l++) if (n = o.relative[e[l].type]) f = [vt(bt(f), n)]; else {
                            if (n = o.filter[e[l].type].apply(null, e[l].matches), n[b]) {
                                for (r = ++l; i > r; r++) if (o.relative[e[r].type]) break;
                                return wt(l > 1 && bt(f), l > 1 && yt(e.slice(0, l - 1).concat({value: " " === e[l - 2].type ? "*" : ""})).replace(z, "$1"), n, r > l && Tt(e.slice(l, r)), i > r && Tt(e = e.slice(r)), i > r && yt(e))
                            }
                            f.push(n)
                        }
                        return bt(f)
                    }

                    function Ct(e, t) {
                        var n = 0, r = t.length > 0, a = e.length > 0, s = function (s, l, c, p, d) {
                            var h, g, m, y = [], v = 0, b = "0", x = s && [], w = null != d, C = u,
                                N = s || a && o.find.TAG("*", d && l.parentNode || l),
                                k = T += null == C ? 1 : Math.random() || .1;
                            for (w && (u = l !== f && l, i = n); null != (h = N[b]); b++) {
                                if (a && h) {
                                    g = 0;
                                    while (m = e[g++]) if (m(h, l, c)) {
                                        p.push(h);
                                        break
                                    }
                                    w && (T = k, i = ++n)
                                }
                                r && ((h = !m && h) && v--, s && x.push(h))
                            }
                            if (v += b, r && b !== v) {
                                g = 0;
                                while (m = t[g++]) m(x, y, l, c);
                                if (s) {
                                    if (v > 0) while (b--) x[b] || y[b] || (y[b] = q.call(p));
                                    y = xt(y)
                                }
                                M.apply(p, y), w && !s && y.length > 0 && v + t.length > 1 && at.uniqueSort(p)
                            }
                            return w && (T = k, u = C), x
                        };
                        return r ? lt(s) : s
                    }

                    l = at.compile = function (e, t) {
                        var n, r = [], i = [], o = E[e + " "];
                        if (!o) {
                            t || (t = mt(e)), n = t.length;
                            while (n--) o = Tt(t[n]), o[b] ? r.push(o) : i.push(o);
                            o = E(e, Ct(i, r))
                        }
                        return o
                    };

                    function Nt(e, t, n) {
                        var r = 0, i = t.length;
                        for (; i > r; r++) at(e, t[r], n);
                        return n
                    }

                    function kt(e, t, n, i) {
                        var a, s, u, c, p, f = mt(e);
                        if (!i && 1 === f.length) {
                            if (s = f[0] = f[0].slice(0), s.length > 2 && "ID" === (u = s[0]).type && r.getById && 9 === t.nodeType && h && o.relative[s[1].type]) {
                                if (t = (o.find.ID(u.matches[0].replace(rt, it), t) || [])[0], !t) return n;
                                e = e.slice(s.shift().value.length)
                            }
                            a = Q.needsContext.test(e) ? 0 : s.length;
                            while (a--) {
                                if (u = s[a], o.relative[c = u.type]) break;
                                if ((p = o.find[c]) && (i = p(u.matches[0].replace(rt, it), V.test(s[0].type) && t.parentNode || t))) {
                                    if (s.splice(a, 1), e = i.length && yt(s), !e) return M.apply(n, i), n;
                                    break
                                }
                            }
                        }
                        return l(e, f)(i, t, !h, n, V.test(e)), n
                    }

                    r.sortStable = b.split("").sort(A).join("") === b, r.detectDuplicates = S, p(), r.sortDetached = ut(function (e) {
                        return 1 & e.compareDocumentPosition(f.createElement("div"))
                    }), ut(function (e) {
                        return e.innerHTML = "<a href='#'></a>", "#" === e.firstChild.getAttribute("href")
                    }) || ct("type|href|height|width", function (e, n, r) {
                        return r ? t : e.getAttribute(n, "type" === n.toLowerCase() ? 1 : 2)
                    }), r.attributes && ut(function (e) {
                        return e.innerHTML = "<input/>", e.firstChild.setAttribute("value", ""), "" === e.firstChild.getAttribute("value")
                    }) || ct("value", function (e, n, r) {
                        return r || "input" !== e.nodeName.toLowerCase() ? t : e.defaultValue
                    }), ut(function (e) {
                        return null == e.getAttribute("disabled")
                    }) || ct(B, function (e, n, r) {
                        var i;
                        return r ? t : (i = e.getAttributeNode(n)) && i.specified ? i.value : e[n] === !0 ? n.toLowerCase() : null
                    }), x.find = at, x.expr = at.selectors, x.expr[":"] = x.expr.pseudos, x.unique = at.uniqueSort, x.text = at.getText, x.isXMLDoc = at.isXML, x.contains = at.contains
                }(e);
                var O = {};

                function F(e) {
                    var t = O[e] = {};
                    return x.each(e.match(T) || [], function (e, n) {
                        t[n] = !0
                    }), t
                }

                x.Callbacks = function (e) {
                    e = "string" == typeof e ? O[e] || F(e) : x.extend({}, e);
                    var n, r, i, o, a, s, l = [], u = !e.once && [], c = function (t) {
                        for (r = e.memory && t, i = !0, a = s || 0, s = 0, o = l.length, n = !0; l && o > a; a++) if (l[a].apply(t[0], t[1]) === !1 && e.stopOnFalse) {
                            r = !1;
                            break
                        }
                        n = !1, l && (u ? u.length && c(u.shift()) : r ? l = [] : p.disable())
                    }, p = {
                        add: function () {
                            if (l) {
                                var t = l.length;
                                (function i(t) {
                                    x.each(t, function (t, n) {
                                        var r = x.type(n);
                                        "function" === r ? e.unique && p.has(n) || l.push(n) : n && n.length && "string" !== r && i(n)
                                    })
                                })(arguments), n ? o = l.length : r && (s = t, c(r))
                            }
                            return this
                        }, remove: function () {
                            return l && x.each(arguments, function (e, t) {
                                var r;
                                while ((r = x.inArray(t, l, r)) > -1) l.splice(r, 1), n && (o >= r && o--, a >= r && a--)
                            }), this
                        }, has: function (e) {
                            return e ? x.inArray(e, l) > -1 : !(!l || !l.length)
                        }, empty: function () {
                            return l = [], o = 0, this
                        }, disable: function () {
                            return l = u = r = t, this
                        }, disabled: function () {
                            return !l
                        }, lock: function () {
                            return u = t, r || p.disable(), this
                        }, locked: function () {
                            return !u
                        }, fireWith: function (e, t) {
                            return !l || i && !u || (t = t || [], t = [e, t.slice ? t.slice() : t], n ? u.push(t) : c(t)), this
                        }, fire: function () {
                            return p.fireWith(this, arguments), this
                        }, fired: function () {
                            return !!i
                        }
                    };
                    return p
                }, x.extend({
                    Deferred: function (e) {
                        var t = [["resolve", "done", x.Callbacks("once memory"), "resolved"], ["reject", "fail", x.Callbacks("once memory"), "rejected"], ["notify", "progress", x.Callbacks("memory")]],
                            n = "pending", r = {
                                state: function () {
                                    return n
                                }, always: function () {
                                    return i.done(arguments).fail(arguments), this
                                }, then: function () {
                                    var e = arguments;
                                    return x.Deferred(function (n) {
                                        x.each(t, function (t, o) {
                                            var a = o[0], s = x.isFunction(e[t]) && e[t];
                                            i[o[1]](function () {
                                                var e = s && s.apply(this, arguments);
                                                e && x.isFunction(e.promise) ? e.promise().done(n.resolve).fail(n.reject).progress(n.notify) : n[a + "With"](this === r ? n.promise() : this, s ? [e] : arguments)
                                            })
                                        }), e = null
                                    }).promise()
                                }, promise: function (e) {
                                    return null != e ? x.extend(e, r) : r
                                }
                            }, i = {};
                        return r.pipe = r.then, x.each(t, function (e, o) {
                            var a = o[2], s = o[3];
                            r[o[1]] = a.add, s && a.add(function () {
                                n = s
                            }, t[1 ^ e][2].disable, t[2][2].lock), i[o[0]] = function () {
                                return i[o[0] + "With"](this === i ? r : this, arguments), this
                            }, i[o[0] + "With"] = a.fireWith
                        }), r.promise(i), e && e.call(i, i), i
                    }, when: function (e) {
                        var t = 0, n = g.call(arguments), r = n.length,
                            i = 1 !== r || e && x.isFunction(e.promise) ? r : 0, o = 1 === i ? e : x.Deferred(),
                            a = function (e, t, n) {
                                return function (r) {
                                    t[e] = this, n[e] = arguments.length > 1 ? g.call(arguments) : r, n === s ? o.notifyWith(t, n) : --i || o.resolveWith(t, n)
                                }
                            }, s, l, u;
                        if (r > 1) for (s = Array(r), l = Array(r), u = Array(r); r > t; t++) n[t] && x.isFunction(n[t].promise) ? n[t].promise().done(a(t, u, n)).fail(o.reject).progress(a(t, l, s)) : --i;
                        return i || o.resolveWith(u, n), o.promise()
                    }
                }), x.support = function (t) {
                    var n, r, o, s, l, u, c, p, f, d = a.createElement("div");
                    if (d.setAttribute("className", "t"), d.innerHTML = "  <link/><table></table><a href='/a'>a</a><input type='checkbox'/>", n = d.getElementsByTagName("*") || [], r = d.getElementsByTagName("a")[0], !r || !r.style || !n.length) return t;
                    s = a.createElement("select"), u = s.appendChild(a.createElement("option")), o = d.getElementsByTagName("input")[0], r.style.cssText = "top:1px;float:left;opacity:.5", t.getSetAttribute = "t" !== d.className, t.leadingWhitespace = 3 === d.firstChild.nodeType, t.tbody = !d.getElementsByTagName("tbody").length, t.htmlSerialize = !!d.getElementsByTagName("link").length, t.style = /top/.test(r.getAttribute("style")), t.hrefNormalized = "/a" === r.getAttribute("href"), t.opacity = /^0.5/.test(r.style.opacity), t.cssFloat = !!r.style.cssFloat, t.checkOn = !!o.value, t.optSelected = u.selected, t.enctype = !!a.createElement("form").enctype, t.html5Clone = "<:nav></:nav>" !== a.createElement("nav").cloneNode(!0).outerHTML, t.inlineBlockNeedsLayout = !1, t.shrinkWrapBlocks = !1, t.pixelPosition = !1, t.deleteExpando = !0, t.noCloneEvent = !0, t.reliableMarginRight = !0, t.boxSizingReliable = !0, o.checked = !0, t.noCloneChecked = o.cloneNode(!0).checked, s.disabled = !0, t.optDisabled = !u.disabled;
                    try {
                        delete d.test
                    } catch (h) {
                        t.deleteExpando = !1
                    }
                    o = a.createElement("input"), o.setAttribute("value", ""), t.input = "" === o.getAttribute("value"), o.value = "t", o.setAttribute("type", "radio"), t.radioValue = "t" === o.value, o.setAttribute("checked", "t"), o.setAttribute("name", "t"), l = a.createDocumentFragment(), l.appendChild(o), t.appendChecked = o.checked, t.checkClone = l.cloneNode(!0).cloneNode(!0).lastChild.checked, d.attachEvent && (d.attachEvent("onclick", function () {
                        t.noCloneEvent = !1
                    }), d.cloneNode(!0).click());
                    for (f in {
                        submit: !0,
                        change: !0,
                        focusin: !0
                    }) d.setAttribute(c = "on" + f, "t"), t[f + "Bubbles"] = c in e || d.attributes[c].expando === !1;
                    d.style.backgroundClip = "content-box", d.cloneNode(!0).style.backgroundClip = "", t.clearCloneStyle = "content-box" === d.style.backgroundClip;
                    for (f in x(t)) break;
                    return t.ownLast = "0" !== f, x(function () {
                        var n, r, o,
                            s = "padding:0;margin:0;border:0;display:block;box-sizing:content-box;-moz-box-sizing:content-box;-webkit-box-sizing:content-box;",
                            l = a.getElementsByTagName("body")[0];
                        l && (n = a.createElement("div"), n.style.cssText = "border:0;width:0;height:0;position:absolute;top:0;left:-9999px;margin-top:1px", l.appendChild(n).appendChild(d), d.innerHTML = "<table><tr><td></td><td>t</td></tr></table>", o = d.getElementsByTagName("td"), o[0].style.cssText = "padding:0;margin:0;border:0;display:none", p = 0 === o[0].offsetHeight, o[0].style.display = "", o[1].style.display = "none", t.reliableHiddenOffsets = p && 0 === o[0].offsetHeight, d.innerHTML = "", d.style.cssText = "box-sizing:border-box;-moz-box-sizing:border-box;-webkit-box-sizing:border-box;padding:1px;border:1px;display:block;width:4px;margin-top:1%;position:absolute;top:1%;", x.swap(l, null != l.style.zoom ? {zoom: 1} : {}, function () {
                            t.boxSizing = 4 === d.offsetWidth
                        }), e.getComputedStyle && (t.pixelPosition = "1%" !== (e.getComputedStyle(d, null) || {}).top, t.boxSizingReliable = "4px" === (e.getComputedStyle(d, null) || {width: "4px"}).width, r = d.appendChild(a.createElement("div")), r.style.cssText = d.style.cssText = s, r.style.marginRight = r.style.width = "0", d.style.width = "1px", t.reliableMarginRight = !parseFloat((e.getComputedStyle(r, null) || {}).marginRight)), typeof d.style.zoom !== i && (d.innerHTML = "", d.style.cssText = s + "width:1px;padding:1px;display:inline;zoom:1", t.inlineBlockNeedsLayout = 3 === d.offsetWidth, d.style.display = "block", d.innerHTML = "<div></div>", d.firstChild.style.width = "5px", t.shrinkWrapBlocks = 3 !== d.offsetWidth, t.inlineBlockNeedsLayout && (l.style.zoom = 1)), l.removeChild(n), n = d = o = r = null)
                    }), n = s = l = u = r = o = null, t
                }({});
                var B = /(?:\{[\s\S]*\}|\[[\s\S]*\])$/, P = /([A-Z])/g;

                function R(e, n, r, i) {
                    if (x.acceptData(e)) {
                        var o, a, s = x.expando, l = e.nodeType, u = l ? x.cache : e, c = l ? e[s] : e[s] && s;
                        if (c && u[c] && (i || u[c].data) || r !== t || "string" != typeof n) return c || (c = l ? e[s] = p.pop() || x.guid++ : s), u[c] || (u[c] = l ? {} : {toJSON: x.noop}), ("object" == typeof n || "function" == typeof n) && (i ? u[c] = x.extend(u[c], n) : u[c].data = x.extend(u[c].data, n)), a = u[c], i || (a.data || (a.data = {}), a = a.data), r !== t && (a[x.camelCase(n)] = r), "string" == typeof n ? (o = a[n], null == o && (o = a[x.camelCase(n)])) : o = a, o
                    }
                }

                function W(e, t, n) {
                    if (x.acceptData(e)) {
                        var r, i, o = e.nodeType, a = o ? x.cache : e, s = o ? e[x.expando] : x.expando;
                        if (a[s]) {
                            if (t && (r = n ? a[s] : a[s].data)) {
                                x.isArray(t) ? t = t.concat(x.map(t, x.camelCase)) : t in r ? t = [t] : (t = x.camelCase(t), t = t in r ? [t] : t.split(" ")), i = t.length;
                                while (i--) delete r[t[i]];
                                if (n ? !I(r) : !x.isEmptyObject(r)) return
                            }
                            (n || (delete a[s].data, I(a[s]))) && (o ? x.cleanData([e], !0) : x.support.deleteExpando || a != a.window ? delete a[s] : a[s] = null)
                        }
                    }
                }

                x.extend({
                    cache: {},
                    noData: {applet: !0, embed: !0, object: "clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"},
                    hasData: function (e) {
                        return e = e.nodeType ? x.cache[e[x.expando]] : e[x.expando], !!e && !I(e)
                    },
                    data: function (e, t, n) {
                        return R(e, t, n)
                    },
                    removeData: function (e, t) {
                        return W(e, t)
                    },
                    _data: function (e, t, n) {
                        return R(e, t, n, !0)
                    },
                    _removeData: function (e, t) {
                        return W(e, t, !0)
                    },
                    acceptData: function (e) {
                        if (e.nodeType && 1 !== e.nodeType && 9 !== e.nodeType) return !1;
                        var t = e.nodeName && x.noData[e.nodeName.toLowerCase()];
                        return !t || t !== !0 && e.getAttribute("classid") === t
                    }
                }), x.fn.extend({
                    data: function (e, n) {
                        var r, i, o = null, a = 0, s = this[0];
                        if (e === t) {
                            if (this.length && (o = x.data(s), 1 === s.nodeType && !x._data(s, "parsedAttrs"))) {
                                for (r = s.attributes; r.length > a; a++) i = r[a].name, 0 === i.indexOf("data-") && (i = x.camelCase(i.slice(5)), $(s, i, o[i]));
                                x._data(s, "parsedAttrs", !0)
                            }
                            return o
                        }
                        return "object" == typeof e ? this.each(function () {
                            x.data(this, e)
                        }) : arguments.length > 1 ? this.each(function () {
                            x.data(this, e, n)
                        }) : s ? $(s, e, x.data(s, e)) : null
                    }, removeData: function (e) {
                        return this.each(function () {
                            x.removeData(this, e)
                        })
                    }
                });

                function $(e, n, r) {
                    if (r === t && 1 === e.nodeType) {
                        var i = "data-" + n.replace(P, "-$1").toLowerCase();
                        if (r = e.getAttribute(i), "string" == typeof r) {
                            try {
                                r = "true" === r ? !0 : "false" === r ? !1 : "null" === r ? null : +r + "" === r ? +r : B.test(r) ? x.parseJSON(r) : r
                            } catch (o) {
                            }
                            x.data(e, n, r)
                        } else r = t
                    }
                    return r
                }

                function I(e) {
                    var t;
                    for (t in e) if (("data" !== t || !x.isEmptyObject(e[t])) && "toJSON" !== t) return !1;
                    return !0
                }

                x.extend({
                    queue: function (e, n, r) {
                        var i;
                        return e ? (n = (n || "fx") + "queue", i = x._data(e, n), r && (!i || x.isArray(r) ? i = x._data(e, n, x.makeArray(r)) : i.push(r)), i || []) : t
                    }, dequeue: function (e, t) {
                        t = t || "fx";
                        var n = x.queue(e, t), r = n.length, i = n.shift(), o = x._queueHooks(e, t), a = function () {
                            x.dequeue(e, t)
                        };
                        "inprogress" === i && (i = n.shift(), r--), i && ("fx" === t && n.unshift("inprogress"), delete o.stop, i.call(e, a, o)), !r && o && o.empty.fire()
                    }, _queueHooks: function (e, t) {
                        var n = t + "queueHooks";
                        return x._data(e, n) || x._data(e, n, {
                            empty: x.Callbacks("once memory").add(function () {
                                x._removeData(e, t + "queue"), x._removeData(e, n)
                            })
                        })
                    }
                }), x.fn.extend({
                    queue: function (e, n) {
                        var r = 2;
                        return "string" != typeof e && (n = e, e = "fx", r--), r > arguments.length ? x.queue(this[0], e) : n === t ? this : this.each(function () {
                            var t = x.queue(this, e, n);
                            x._queueHooks(this, e), "fx" === e && "inprogress" !== t[0] && x.dequeue(this, e)
                        })
                    }, dequeue: function (e) {
                        return this.each(function () {
                            x.dequeue(this, e)
                        })
                    }, delay: function (e, t) {
                        return e = x.fx ? x.fx.speeds[e] || e : e, t = t || "fx", this.queue(t, function (t, n) {
                            var r = setTimeout(t, e);
                            n.stop = function () {
                                clearTimeout(r)
                            }
                        })
                    }, clearQueue: function (e) {
                        return this.queue(e || "fx", [])
                    }, promise: function (e, n) {
                        var r, i = 1, o = x.Deferred(), a = this, s = this.length, l = function () {
                            --i || o.resolveWith(a, [a])
                        };
                        "string" != typeof e && (n = e, e = t), e = e || "fx";
                        while (s--) r = x._data(a[s], e + "queueHooks"), r && r.empty && (i++, r.empty.add(l));
                        return l(), o.promise(n)
                    }
                });
                var z, X, U = /[\t\r\n\f]/g, V = /\r/g, Y = /^(?:input|select|textarea|button|object)$/i,
                    J = /^(?:a|area)$/i, G = /^(?:checked|selected)$/i, Q = x.support.getSetAttribute,
                    K = x.support.input;
                x.fn.extend({
                    attr: function (e, t) {
                        return x.access(this, x.attr, e, t, arguments.length > 1)
                    }, removeAttr: function (e) {
                        return this.each(function () {
                            x.removeAttr(this, e)
                        })
                    }, prop: function (e, t) {
                        return x.access(this, x.prop, e, t, arguments.length > 1)
                    }, removeProp: function (e) {
                        return e = x.propFix[e] || e, this.each(function () {
                            try {
                                this[e] = t, delete this[e]
                            } catch (n) {
                            }
                        })
                    }, addClass: function (e) {
                        var t, n, r, i, o, a = 0, s = this.length, l = "string" == typeof e && e;
                        if (x.isFunction(e)) return this.each(function (t) {
                            x(this).addClass(e.call(this, t, this.className))
                        });
                        if (l) for (t = (e || "").match(T) || []; s > a; a++) if (n = this[a], r = 1 === n.nodeType && (n.className ? (" " + n.className + " ").replace(U, " ") : " ")) {
                            o = 0;
                            while (i = t[o++]) 0 > r.indexOf(" " + i + " ") && (r += i + " ");
                            n.className = x.trim(r)
                        }
                        return this
                    }, removeClass: function (e) {
                        var t, n, r, i, o, a = 0, s = this.length,
                            l = 0 === arguments.length || "string" == typeof e && e;
                        if (x.isFunction(e)) return this.each(function (t) {
                            x(this).removeClass(e.call(this, t, this.className))
                        });
                        if (l) for (t = (e || "").match(T) || []; s > a; a++) if (n = this[a], r = 1 === n.nodeType && (n.className ? (" " + n.className + " ").replace(U, " ") : "")) {
                            o = 0;
                            while (i = t[o++]) while (r.indexOf(" " + i + " ") >= 0) r = r.replace(" " + i + " ", " ");
                            n.className = e ? x.trim(r) : ""
                        }
                        return this
                    }, toggleClass: function (e, t) {
                        var n = typeof e;
                        return "boolean" == typeof t && "string" === n ? t ? this.addClass(e) : this.removeClass(e) : x.isFunction(e) ? this.each(function (n) {
                            x(this).toggleClass(e.call(this, n, this.className, t), t)
                        }) : this.each(function () {
                            if ("string" === n) {
                                var t, r = 0, o = x(this), a = e.match(T) || [];
                                while (t = a[r++]) o.hasClass(t) ? o.removeClass(t) : o.addClass(t)
                            } else (n === i || "boolean" === n) && (this.className && x._data(this, "__className__", this.className), this.className = this.className || e === !1 ? "" : x._data(this, "__className__") || "")
                        })
                    }, hasClass: function (e) {
                        var t = " " + e + " ", n = 0, r = this.length;
                        for (; r > n; n++) if (1 === this[n].nodeType && (" " + this[n].className + " ").replace(U, " ").indexOf(t) >= 0) return !0;
                        return !1
                    }, val: function (e) {
                        var n, r, i, o = this[0];
                        {
                            if (arguments.length) return i = x.isFunction(e), this.each(function (n) {
                                var o;
                                1 === this.nodeType && (o = i ? e.call(this, n, x(this).val()) : e, null == o ? o = "" : "number" == typeof o ? o += "" : x.isArray(o) && (o = x.map(o, function (e) {
                                    return null == e ? "" : e + ""
                                })), r = x.valHooks[this.type] || x.valHooks[this.nodeName.toLowerCase()], r && "set" in r && r.set(this, o, "value") !== t || (this.value = o))
                            });
                            if (o) return r = x.valHooks[o.type] || x.valHooks[o.nodeName.toLowerCase()], r && "get" in r && (n = r.get(o, "value")) !== t ? n : (n = o.value, "string" == typeof n ? n.replace(V, "") : null == n ? "" : n)
                        }
                    }
                }), x.extend({
                    valHooks: {
                        option: {
                            get: function (e) {
                                var t = x.find.attr(e, "value");
                                return null != t ? t : e.text
                            }
                        }, select: {
                            get: function (e) {
                                var t, n, r = e.options, i = e.selectedIndex, o = "select-one" === e.type || 0 > i,
                                    a = o ? null : [], s = o ? i + 1 : r.length, l = 0 > i ? s : o ? i : 0;
                                for (; s > l; l++) if (n = r[l], !(!n.selected && l !== i || (x.support.optDisabled ? n.disabled : null !== n.getAttribute("disabled")) || n.parentNode.disabled && x.nodeName(n.parentNode, "optgroup"))) {
                                    if (t = x(n).val(), o) return t;
                                    a.push(t)
                                }
                                return a
                            }, set: function (e, t) {
                                var n, r, i = e.options, o = x.makeArray(t), a = i.length;
                                while (a--) r = i[a], (r.selected = x.inArray(x(r).val(), o) >= 0) && (n = !0);
                                return n || (e.selectedIndex = -1), o
                            }
                        }
                    }, attr: function (e, n, r) {
                        var o, a, s = e.nodeType;
                        if (e && 3 !== s && 8 !== s && 2 !== s) return typeof e.getAttribute === i ? x.prop(e, n, r) : (1 === s && x.isXMLDoc(e) || (n = n.toLowerCase(), o = x.attrHooks[n] || (x.expr.match.bool.test(n) ? X : z)), r === t ? o && "get" in o && null !== (a = o.get(e, n)) ? a : (a = x.find.attr(e, n), null == a ? t : a) : null !== r ? o && "set" in o && (a = o.set(e, r, n)) !== t ? a : (e.setAttribute(n, r + ""), r) : (x.removeAttr(e, n), t))
                    }, removeAttr: function (e, t) {
                        var n, r, i = 0, o = t && t.match(T);
                        if (o && 1 === e.nodeType) while (n = o[i++]) r = x.propFix[n] || n, x.expr.match.bool.test(n) ? K && Q || !G.test(n) ? e[r] = !1 : e[x.camelCase("default-" + n)] = e[r] = !1 : x.attr(e, n, ""), e.removeAttribute(Q ? n : r)
                    }, attrHooks: {
                        type: {
                            set: function (e, t) {
                                if (!x.support.radioValue && "radio" === t && x.nodeName(e, "input")) {
                                    var n = e.value;
                                    return e.setAttribute("type", t), n && (e.value = n), t
                                }
                            }
                        }
                    }, propFix: {"for": "htmlFor", "class": "className"}, prop: function (e, n, r) {
                        var i, o, a, s = e.nodeType;
                        if (e && 3 !== s && 8 !== s && 2 !== s) return a = 1 !== s || !x.isXMLDoc(e), a && (n = x.propFix[n] || n, o = x.propHooks[n]), r !== t ? o && "set" in o && (i = o.set(e, r, n)) !== t ? i : e[n] = r : o && "get" in o && null !== (i = o.get(e, n)) ? i : e[n]
                    }, propHooks: {
                        tabIndex: {
                            get: function (e) {
                                var t = x.find.attr(e, "tabindex");
                                return t ? parseInt(t, 10) : Y.test(e.nodeName) || J.test(e.nodeName) && e.href ? 0 : -1
                            }
                        }
                    }
                }), X = {
                    set: function (e, t, n) {
                        return t === !1 ? x.removeAttr(e, n) : K && Q || !G.test(n) ? e.setAttribute(!Q && x.propFix[n] || n, n) : e[x.camelCase("default-" + n)] = e[n] = !0, n
                    }
                }, x.each(x.expr.match.bool.source.match(/\w+/g), function (e, n) {
                    var r = x.expr.attrHandle[n] || x.find.attr;
                    x.expr.attrHandle[n] = K && Q || !G.test(n) ? function (e, n, i) {
                        var o = x.expr.attrHandle[n],
                            a = i ? t : (x.expr.attrHandle[n] = t) != r(e, n, i) ? n.toLowerCase() : null;
                        return x.expr.attrHandle[n] = o, a
                    } : function (e, n, r) {
                        return r ? t : e[x.camelCase("default-" + n)] ? n.toLowerCase() : null
                    }
                }), K && Q || (x.attrHooks.value = {
                    set: function (e, n, r) {
                        return x.nodeName(e, "input") ? (e.defaultValue = n, t) : z && z.set(e, n, r)
                    }
                }), Q || (z = {
                    set: function (e, n, r) {
                        var i = e.getAttributeNode(r);
                        return i || e.setAttributeNode(i = e.ownerDocument.createAttribute(r)), i.value = n += "", "value" === r || n === e.getAttribute(r) ? n : t
                    }
                }, x.expr.attrHandle.id = x.expr.attrHandle.name = x.expr.attrHandle.coords = function (e, n, r) {
                    var i;
                    return r ? t : (i = e.getAttributeNode(n)) && "" !== i.value ? i.value : null
                }, x.valHooks.button = {
                    get: function (e, n) {
                        var r = e.getAttributeNode(n);
                        return r && r.specified ? r.value : t
                    }, set: z.set
                }, x.attrHooks.contenteditable = {
                    set: function (e, t, n) {
                        z.set(e, "" === t ? !1 : t, n)
                    }
                }, x.each(["width", "height"], function (e, n) {
                    x.attrHooks[n] = {
                        set: function (e, r) {
                            return "" === r ? (e.setAttribute(n, "auto"), r) : t
                        }
                    }
                })), x.support.hrefNormalized || x.each(["href", "src"], function (e, t) {
                    x.propHooks[t] = {
                        get: function (e) {
                            return e.getAttribute(t, 4)
                        }
                    }
                }), x.support.style || (x.attrHooks.style = {
                    get: function (e) {
                        return e.style.cssText || t
                    }, set: function (e, t) {
                        return e.style.cssText = t + ""
                    }
                }), x.support.optSelected || (x.propHooks.selected = {
                    get: function (e) {
                        var t = e.parentNode;
                        return t && (t.selectedIndex, t.parentNode && t.parentNode.selectedIndex), null
                    }
                }), x.each(["tabIndex", "readOnly", "maxLength", "cellSpacing", "cellPadding", "rowSpan", "colSpan", "useMap", "frameBorder", "contentEditable"], function () {
                    x.propFix[this.toLowerCase()] = this
                }), x.support.enctype || (x.propFix.enctype = "encoding"), x.each(["radio", "checkbox"], function () {
                    x.valHooks[this] = {
                        set: function (e, n) {
                            return x.isArray(n) ? e.checked = x.inArray(x(e).val(), n) >= 0 : t
                        }
                    }, x.support.checkOn || (x.valHooks[this].get = function (e) {
                        return null === e.getAttribute("value") ? "on" : e.value
                    })
                });
                var Z = /^(?:input|select|textarea)$/i, et = /^key/, tt = /^(?:mouse|contextmenu)|click/,
                    nt = /^(?:focusinfocus|focusoutblur)$/, rt = /^([^.]*)(?:\.(.+)|)$/;

                function it() {
                    return !0
                }

                function ot() {
                    return !1
                }

                function at() {
                    try {
                        return a.activeElement
                    } catch (e) {
                    }
                }

                x.event = {
                    global: {},
                    add: function (e, n, r, o, a) {
                        var s, l, u, c, p, f, d, h, g, m, y, v = x._data(e);
                        if (v) {
                            r.handler && (c = r, r = c.handler, a = c.selector), r.guid || (r.guid = x.guid++), (l = v.events) || (l = v.events = {}), (f = v.handle) || (f = v.handle = function (e) {
                                return typeof x === i || e && x.event.triggered === e.type ? t : x.event.dispatch.apply(f.elem, arguments)
                            }, f.elem = e), n = (n || "").match(T) || [""], u = n.length;
                            while (u--) s = rt.exec(n[u]) || [], g = y = s[1], m = (s[2] || "").split(".").sort(), g && (p = x.event.special[g] || {}, g = (a ? p.delegateType : p.bindType) || g, p = x.event.special[g] || {}, d = x.extend({
                                type: g,
                                origType: y,
                                data: o,
                                handler: r,
                                guid: r.guid,
                                selector: a,
                                needsContext: a && x.expr.match.needsContext.test(a),
                                namespace: m.join(".")
                            }, c), (h = l[g]) || (h = l[g] = [], h.delegateCount = 0, p.setup && p.setup.call(e, o, m, f) !== !1 || (e.addEventListener ? e.addEventListener(g, f, !1) : e.attachEvent && e.attachEvent("on" + g, f))), p.add && (p.add.call(e, d), d.handler.guid || (d.handler.guid = r.guid)), a ? h.splice(h.delegateCount++, 0, d) : h.push(d), x.event.global[g] = !0);
                            e = null
                        }
                    },
                    remove: function (e, t, n, r, i) {
                        var o, a, s, l, u, c, p, f, d, h, g, m = x.hasData(e) && x._data(e);
                        if (m && (c = m.events)) {
                            t = (t || "").match(T) || [""], u = t.length;
                            while (u--) if (s = rt.exec(t[u]) || [], d = g = s[1], h = (s[2] || "").split(".").sort(), d) {
                                p = x.event.special[d] || {}, d = (r ? p.delegateType : p.bindType) || d, f = c[d] || [], s = s[2] && RegExp("(^|\\.)" + h.join("\\.(?:.*\\.|)") + "(\\.|$)"), l = o = f.length;
                                while (o--) a = f[o], !i && g !== a.origType || n && n.guid !== a.guid || s && !s.test(a.namespace) || r && r !== a.selector && ("**" !== r || !a.selector) || (f.splice(o, 1), a.selector && f.delegateCount--, p.remove && p.remove.call(e, a));
                                l && !f.length && (p.teardown && p.teardown.call(e, h, m.handle) !== !1 || x.removeEvent(e, d, m.handle), delete c[d])
                            } else for (d in c) x.event.remove(e, d + t[u], n, r, !0);
                            x.isEmptyObject(c) && (delete m.handle, x._removeData(e, "events"))
                        }
                    },
                    trigger: function (n, r, i, o) {
                        var s, l, u, c, p, f, d, h = [i || a], g = v.call(n, "type") ? n.type : n,
                            m = v.call(n, "namespace") ? n.namespace.split(".") : [];
                        if (u = f = i = i || a, 3 !== i.nodeType && 8 !== i.nodeType && !nt.test(g + x.event.triggered) && (g.indexOf(".") >= 0 && (m = g.split("."), g = m.shift(), m.sort()), l = 0 > g.indexOf(":") && "on" + g, n = n[x.expando] ? n : new x.Event(g, "object" == typeof n && n), n.isTrigger = o ? 2 : 3, n.namespace = m.join("."), n.namespace_re = n.namespace ? RegExp("(^|\\.)" + m.join("\\.(?:.*\\.|)") + "(\\.|$)") : null, n.result = t, n.target || (n.target = i), r = null == r ? [n] : x.makeArray(r, [n]), p = x.event.special[g] || {}, o || !p.trigger || p.trigger.apply(i, r) !== !1)) {
                            if (!o && !p.noBubble && !x.isWindow(i)) {
                                for (c = p.delegateType || g, nt.test(c + g) || (u = u.parentNode); u; u = u.parentNode) h.push(u), f = u;
                                f === (i.ownerDocument || a) && h.push(f.defaultView || f.parentWindow || e)
                            }
                            d = 0;
                            while ((u = h[d++]) && !n.isPropagationStopped()) n.type = d > 1 ? c : p.bindType || g, s = (x._data(u, "events") || {})[n.type] && x._data(u, "handle"), s && s.apply(u, r), s = l && u[l], s && x.acceptData(u) && s.apply && s.apply(u, r) === !1 && n.preventDefault();
                            if (n.type = g, !o && !n.isDefaultPrevented() && (!p._default || p._default.apply(h.pop(), r) === !1) && x.acceptData(i) && l && i[g] && !x.isWindow(i)) {
                                f = i[l], f && (i[l] = null), x.event.triggered = g;
                                try {
                                    i[g]()
                                } catch (y) {
                                }
                                x.event.triggered = t, f && (i[l] = f)
                            }
                            return n.result
                        }
                    },
                    dispatch: function (e) {
                        e = x.event.fix(e);
                        var n, r, i, o, a, s = [], l = g.call(arguments),
                            u = (x._data(this, "events") || {})[e.type] || [], c = x.event.special[e.type] || {};
                        if (l[0] = e, e.delegateTarget = this, !c.preDispatch || c.preDispatch.call(this, e) !== !1) {
                            s = x.event.handlers.call(this, e, u), n = 0;
                            while ((o = s[n++]) && !e.isPropagationStopped()) {
                                e.currentTarget = o.elem, a = 0;
                                while ((i = o.handlers[a++]) && !e.isImmediatePropagationStopped()) (!e.namespace_re || e.namespace_re.test(i.namespace)) && (e.handleObj = i, e.data = i.data, r = ((x.event.special[i.origType] || {}).handle || i.handler).apply(o.elem, l), r !== t && (e.result = r) === !1 && (e.preventDefault(), e.stopPropagation()))
                            }
                            return c.postDispatch && c.postDispatch.call(this, e), e.result
                        }
                    },
                    handlers: function (e, n) {
                        var r, i, o, a, s = [], l = n.delegateCount, u = e.target;
                        if (l && u.nodeType && (!e.button || "click" !== e.type)) for (; u != this; u = u.parentNode || this) if (1 === u.nodeType && (u.disabled !== !0 || "click" !== e.type)) {
                            for (o = [], a = 0; l > a; a++) i = n[a], r = i.selector + " ", o[r] === t && (o[r] = i.needsContext ? x(r, this).index(u) >= 0 : x.find(r, this, null, [u]).length), o[r] && o.push(i);
                            o.length && s.push({elem: u, handlers: o})
                        }
                        return n.length > l && s.push({elem: this, handlers: n.slice(l)}), s
                    },
                    fix: function (e) {
                        if (e[x.expando]) return e;
                        var t, n, r, i = e.type, o = e, s = this.fixHooks[i];
                        s || (this.fixHooks[i] = s = tt.test(i) ? this.mouseHooks : et.test(i) ? this.keyHooks : {}), r = s.props ? this.props.concat(s.props) : this.props, e = new x.Event(o), t = r.length;
                        while (t--) n = r[t], e[n] = o[n];
                        return e.target || (e.target = o.srcElement || a), 3 === e.target.nodeType && (e.target = e.target.parentNode), e.metaKey = !!e.metaKey, s.filter ? s.filter(e, o) : e
                    },
                    props: "altKey bubbles cancelable ctrlKey currentTarget eventPhase metaKey relatedTarget shiftKey target timeStamp view which".split(" "),
                    fixHooks: {},
                    keyHooks: {
                        props: "char charCode key keyCode".split(" "), filter: function (e, t) {
                            return null == e.which && (e.which = null != t.charCode ? t.charCode : t.keyCode), e
                        }
                    },
                    mouseHooks: {
                        props: "button buttons clientX clientY fromElement offsetX offsetY pageX pageY screenX screenY toElement".split(" "),
                        filter: function (e, n) {
                            var r, i, o, s = n.button, l = n.fromElement;
                            return null == e.pageX && null != n.clientX && (i = e.target.ownerDocument || a, o = i.documentElement, r = i.body, e.pageX = n.clientX + (o && o.scrollLeft || r && r.scrollLeft || 0) - (o && o.clientLeft || r && r.clientLeft || 0), e.pageY = n.clientY + (o && o.scrollTop || r && r.scrollTop || 0) - (o && o.clientTop || r && r.clientTop || 0)), !e.relatedTarget && l && (e.relatedTarget = l === e.target ? n.toElement : l), e.which || s === t || (e.which = 1 & s ? 1 : 2 & s ? 3 : 4 & s ? 2 : 0), e
                        }
                    },
                    special: {
                        load: {noBubble: !0}, focus: {
                            trigger: function () {
                                if (this !== at() && this.focus) try {
                                    return this.focus(), !1
                                } catch (e) {
                                }
                            }, delegateType: "focusin"
                        }, blur: {
                            trigger: function () {
                                return this === at() && this.blur ? (this.blur(), !1) : t
                            }, delegateType: "focusout"
                        }, click: {
                            trigger: function () {
                                return x.nodeName(this, "input") && "checkbox" === this.type && this.click ? (this.click(), !1) : t
                            }, _default: function (e) {
                                return x.nodeName(e.target, "a")
                            }
                        }, beforeunload: {
                            postDispatch: function (e) {
                                e.result !== t && (e.originalEvent.returnValue = e.result)
                            }
                        }
                    },
                    simulate: function (e, t, n, r) {
                        var i = x.extend(new x.Event, n, {type: e, isSimulated: !0, originalEvent: {}});
                        r ? x.event.trigger(i, null, t) : x.event.dispatch.call(t, i), i.isDefaultPrevented() && n.preventDefault()
                    }
                }, x.removeEvent = a.removeEventListener ? function (e, t, n) {
                    e.removeEventListener && e.removeEventListener(t, n, !1)
                } : function (e, t, n) {
                    var r = "on" + t;
                    e.detachEvent && (typeof e[r] === i && (e[r] = null), e.detachEvent(r, n))
                }, x.Event = function (e, n) {
                    return this instanceof x.Event ? (e && e.type ? (this.originalEvent = e, this.type = e.type, this.isDefaultPrevented = e.defaultPrevented || e.returnValue === !1 || e.getPreventDefault && e.getPreventDefault() ? it : ot) : this.type = e, n && x.extend(this, n), this.timeStamp = e && e.timeStamp || x.now(), this[x.expando] = !0, t) : new x.Event(e, n)
                }, x.Event.prototype = {
                    isDefaultPrevented: ot,
                    isPropagationStopped: ot,
                    isImmediatePropagationStopped: ot,
                    preventDefault: function () {
                        var e = this.originalEvent;
                        this.isDefaultPrevented = it, e && (e.preventDefault ? e.preventDefault() : e.returnValue = !1)
                    },
                    stopPropagation: function () {
                        var e = this.originalEvent;
                        this.isPropagationStopped = it, e && (e.stopPropagation && e.stopPropagation(), e.cancelBubble = !0)
                    },
                    stopImmediatePropagation: function () {
                        this.isImmediatePropagationStopped = it, this.stopPropagation()
                    }
                }, x.each({mouseenter: "mouseover", mouseleave: "mouseout"}, function (e, t) {
                    x.event.special[e] = {
                        delegateType: t, bindType: t, handle: function (e) {
                            var n, r = this, i = e.relatedTarget, o = e.handleObj;
                            return (!i || i !== r && !x.contains(r, i)) && (e.type = o.origType, n = o.handler.apply(this, arguments), e.type = t), n
                        }
                    }
                }), x.support.submitBubbles || (x.event.special.submit = {
                    setup: function () {
                        return x.nodeName(this, "form") ? !1 : (x.event.add(this, "click._submit keypress._submit", function (e) {
                            var n = e.target, r = x.nodeName(n, "input") || x.nodeName(n, "button") ? n.form : t;
                            r && !x._data(r, "submitBubbles") && (x.event.add(r, "submit._submit", function (e) {
                                e._submit_bubble = !0
                            }), x._data(r, "submitBubbles", !0))
                        }), t)
                    }, postDispatch: function (e) {
                        e._submit_bubble && (delete e._submit_bubble, this.parentNode && !e.isTrigger && x.event.simulate("submit", this.parentNode, e, !0))
                    }, teardown: function () {
                        return x.nodeName(this, "form") ? !1 : (x.event.remove(this, "._submit"), t)
                    }
                }), x.support.changeBubbles || (x.event.special.change = {
                    setup: function () {
                        return Z.test(this.nodeName) ? (("checkbox" === this.type || "radio" === this.type) && (x.event.add(this, "propertychange._change", function (e) {
                            "checked" === e.originalEvent.propertyName && (this._just_changed = !0)
                        }), x.event.add(this, "click._change", function (e) {
                            this._just_changed && !e.isTrigger && (this._just_changed = !1), x.event.simulate("change", this, e, !0)
                        })), !1) : (x.event.add(this, "beforeactivate._change", function (e) {
                            var t = e.target;
                            Z.test(t.nodeName) && !x._data(t, "changeBubbles") && (x.event.add(t, "change._change", function (e) {
                                !this.parentNode || e.isSimulated || e.isTrigger || x.event.simulate("change", this.parentNode, e, !0)
                            }), x._data(t, "changeBubbles", !0))
                        }), t)
                    }, handle: function (e) {
                        var n = e.target;
                        return this !== n || e.isSimulated || e.isTrigger || "radio" !== n.type && "checkbox" !== n.type ? e.handleObj.handler.apply(this, arguments) : t
                    }, teardown: function () {
                        return x.event.remove(this, "._change"), !Z.test(this.nodeName)
                    }
                }), x.support.focusinBubbles || x.each({focus: "focusin", blur: "focusout"}, function (e, t) {
                    var n = 0, r = function (e) {
                        x.event.simulate(t, e.target, x.event.fix(e), !0)
                    };
                    x.event.special[t] = {
                        setup: function () {
                            0 === n++ && a.addEventListener(e, r, !0)
                        }, teardown: function () {
                            0 === --n && a.removeEventListener(e, r, !0)
                        }
                    }
                }), x.fn.extend({
                    on: function (e, n, r, i, o) {
                        var a, s;
                        if ("object" == typeof e) {
                            "string" != typeof n && (r = r || n, n = t);
                            for (a in e) this.on(a, n, r, e[a], o);
                            return this
                        }
                        if (null == r && null == i ? (i = n, r = n = t) : null == i && ("string" == typeof n ? (i = r, r = t) : (i = r, r = n, n = t)), i === !1) i = ot; else if (!i) return this;
                        return 1 === o && (s = i, i = function (e) {
                            return x().off(e), s.apply(this, arguments)
                        }, i.guid = s.guid || (s.guid = x.guid++)), this.each(function () {
                            x.event.add(this, e, i, r, n)
                        })
                    }, one: function (e, t, n, r) {
                        return this.on(e, t, n, r, 1)
                    }, off: function (e, n, r) {
                        var i, o;
                        if (e && e.preventDefault && e.handleObj) return i = e.handleObj, x(e.delegateTarget).off(i.namespace ? i.origType + "." + i.namespace : i.origType, i.selector, i.handler), this;
                        if ("object" == typeof e) {
                            for (o in e) this.off(o, n, e[o]);
                            return this
                        }
                        return (n === !1 || "function" == typeof n) && (r = n, n = t), r === !1 && (r = ot), this.each(function () {
                            x.event.remove(this, e, r, n)
                        })
                    }, trigger: function (e, t) {
                        return this.each(function () {
                            x.event.trigger(e, t, this)
                        })
                    }, triggerHandler: function (e, n) {
                        var r = this[0];
                        return r ? x.event.trigger(e, n, r, !0) : t
                    }
                });
                var st = /^.[^:#\[\.,]*$/, lt = /^(?:parents|prev(?:Until|All))/, ut = x.expr.match.needsContext,
                    ct = {children: !0, contents: !0, next: !0, prev: !0};
                x.fn.extend({
                    find: function (e) {
                        var t, n = [], r = this, i = r.length;
                        if ("string" != typeof e) return this.pushStack(x(e).filter(function () {
                            for (t = 0; i > t; t++) if (x.contains(r[t], this)) return !0
                        }));
                        for (t = 0; i > t; t++) x.find(e, r[t], n);
                        return n = this.pushStack(i > 1 ? x.unique(n) : n), n.selector = this.selector ? this.selector + " " + e : e, n
                    }, has: function (e) {
                        var t, n = x(e, this), r = n.length;
                        return this.filter(function () {
                            for (t = 0; r > t; t++) if (x.contains(this, n[t])) return !0
                        })
                    }, not: function (e) {
                        return this.pushStack(ft(this, e || [], !0))
                    }, filter: function (e) {
                        return this.pushStack(ft(this, e || [], !1))
                    }, is: function (e) {
                        return !!ft(this, "string" == typeof e && ut.test(e) ? x(e) : e || [], !1).length
                    }, closest: function (e, t) {
                        var n, r = 0, i = this.length, o = [],
                            a = ut.test(e) || "string" != typeof e ? x(e, t || this.context) : 0;
                        for (; i > r; r++) for (n = this[r]; n && n !== t; n = n.parentNode) if (11 > n.nodeType && (a ? a.index(n) > -1 : 1 === n.nodeType && x.find.matchesSelector(n, e))) {
                            n = o.push(n);
                            break
                        }
                        return this.pushStack(o.length > 1 ? x.unique(o) : o)
                    }, index: function (e) {
                        return e ? "string" == typeof e ? x.inArray(this[0], x(e)) : x.inArray(e.jquery ? e[0] : e, this) : this[0] && this[0].parentNode ? this.first().prevAll().length : -1
                    }, add: function (e, t) {
                        var n = "string" == typeof e ? x(e, t) : x.makeArray(e && e.nodeType ? [e] : e),
                            r = x.merge(this.get(), n);
                        return this.pushStack(x.unique(r))
                    }, addBack: function (e) {
                        return this.add(null == e ? this.prevObject : this.prevObject.filter(e))
                    }
                });

                function pt(e, t) {
                    do e = e[t]; while (e && 1 !== e.nodeType);
                    return e
                }

                x.each({
                    parent: function (e) {
                        var t = e.parentNode;
                        return t && 11 !== t.nodeType ? t : null
                    }, parents: function (e) {
                        return x.dir(e, "parentNode")
                    }, parentsUntil: function (e, t, n) {
                        return x.dir(e, "parentNode", n)
                    }, next: function (e) {
                        return pt(e, "nextSibling")
                    }, prev: function (e) {
                        return pt(e, "previousSibling")
                    }, nextAll: function (e) {
                        return x.dir(e, "nextSibling")
                    }, prevAll: function (e) {
                        return x.dir(e, "previousSibling")
                    }, nextUntil: function (e, t, n) {
                        return x.dir(e, "nextSibling", n)
                    }, prevUntil: function (e, t, n) {
                        return x.dir(e, "previousSibling", n)
                    }, siblings: function (e) {
                        return x.sibling((e.parentNode || {}).firstChild, e)
                    }, children: function (e) {
                        return x.sibling(e.firstChild)
                    }, contents: function (e) {
                        return x.nodeName(e, "iframe") ? e.contentDocument || e.contentWindow.document : x.merge([], e.childNodes)
                    }
                }, function (e, t) {
                    x.fn[e] = function (n, r) {
                        var i = x.map(this, t, n);
                        return "Until" !== e.slice(-5) && (r = n), r && "string" == typeof r && (i = x.filter(r, i)), this.length > 1 && (ct[e] || (i = x.unique(i)), lt.test(e) && (i = i.reverse())), this.pushStack(i)
                    }
                }), x.extend({
                    filter: function (e, t, n) {
                        var r = t[0];
                        return n && (e = ":not(" + e + ")"), 1 === t.length && 1 === r.nodeType ? x.find.matchesSelector(r, e) ? [r] : [] : x.find.matches(e, x.grep(t, function (e) {
                            return 1 === e.nodeType
                        }))
                    }, dir: function (e, n, r) {
                        var i = [], o = e[n];
                        while (o && 9 !== o.nodeType && (r === t || 1 !== o.nodeType || !x(o).is(r))) 1 === o.nodeType && i.push(o), o = o[n];
                        return i
                    }, sibling: function (e, t) {
                        var n = [];
                        for (; e; e = e.nextSibling) 1 === e.nodeType && e !== t && n.push(e);
                        return n
                    }
                });

                function ft(e, t, n) {
                    if (x.isFunction(t)) return x.grep(e, function (e, r) {
                        return !!t.call(e, r, e) !== n
                    });
                    if (t.nodeType) return x.grep(e, function (e) {
                        return e === t !== n
                    });
                    if ("string" == typeof t) {
                        if (st.test(t)) return x.filter(t, e, n);
                        t = x.filter(t, e)
                    }
                    return x.grep(e, function (e) {
                        return x.inArray(e, t) >= 0 !== n
                    })
                }

                function dt(e) {
                    var t = ht.split("|"), n = e.createDocumentFragment();
                    if (n.createElement) while (t.length) n.createElement(t.pop());
                    return n
                }

                var ht = "abbr|article|aside|audio|bdi|canvas|data|datalist|details|figcaption|figure|footer|header|hgroup|mark|meter|nav|output|progress|section|summary|time|video",
                    gt = / jQuery\d+="(?:null|\d+)"/g, mt = RegExp("<(?:" + ht + ")[\\s/>]", "i"), yt = /^\s+/,
                    vt = /<(?!area|br|col|embed|hr|img|input|link|meta|param)(([\w:]+)[^>]*)\/>/gi, bt = /<([\w:]+)/,
                    xt = /<tbody/i, wt = /<|&#?\w+;/, Tt = /<(?:script|style|link)/i, Ct = /^(?:checkbox|radio)$/i,
                    Nt = /checked\s*(?:[^=]|=\s*.checked.)/i, kt = /^$|\/(?:java|ecma)script/i, Et = /^true\/(.*)/,
                    St = /^\s*<!(?:\[CDATA\[|--)|(?:\]\]|--)>\s*$/g, At = {
                        option: [1, "<select multiple='multiple'>", "</select>"],
                        legend: [1, "<fieldset>", "</fieldset>"],
                        area: [1, "<map>", "</map>"],
                        param: [1, "<object>", "</object>"],
                        thead: [1, "<table>", "</table>"],
                        tr: [2, "<table><tbody>", "</tbody></table>"],
                        col: [2, "<table><tbody></tbody><colgroup>", "</colgroup></table>"],
                        td: [3, "<table><tbody><tr>", "</tr></tbody></table>"],
                        _default: x.support.htmlSerialize ? [0, "", ""] : [1, "X<div>", "</div>"]
                    }, jt = dt(a), Dt = jt.appendChild(a.createElement("div"));
                At.optgroup = At.option, At.tbody = At.tfoot = At.colgroup = At.caption = At.thead, At.th = At.td, x.fn.extend({
                    text: function (e) {
                        return x.access(this, function (e) {
                            return e === t ? x.text(this) : this.empty().append((this[0] && this[0].ownerDocument || a).createTextNode(e))
                        }, null, e, arguments.length)
                    }, append: function () {
                        return this.domManip(arguments, function (e) {
                            if (1 === this.nodeType || 11 === this.nodeType || 9 === this.nodeType) {
                                var t = Lt(this, e);
                                t.appendChild(e)
                            }
                        })
                    }, prepend: function () {
                        return this.domManip(arguments, function (e) {
                            if (1 === this.nodeType || 11 === this.nodeType || 9 === this.nodeType) {
                                var t = Lt(this, e);
                                t.insertBefore(e, t.firstChild)
                            }
                        })
                    }, before: function () {
                        return this.domManip(arguments, function (e) {
                            this.parentNode && this.parentNode.insertBefore(e, this)
                        })
                    }, after: function () {
                        return this.domManip(arguments, function (e) {
                            this.parentNode && this.parentNode.insertBefore(e, this.nextSibling)
                        })
                    }, remove: function (e, t) {
                        var n, r = e ? x.filter(e, this) : this, i = 0;
                        for (; null != (n = r[i]); i++) t || 1 !== n.nodeType || x.cleanData(Ft(n)), n.parentNode && (t && x.contains(n.ownerDocument, n) && _t(Ft(n, "script")), n.parentNode.removeChild(n));
                        return this
                    }, empty: function () {
                        var e, t = 0;
                        for (; null != (e = this[t]); t++) {
                            1 === e.nodeType && x.cleanData(Ft(e, !1));
                            while (e.firstChild) e.removeChild(e.firstChild);
                            e.options && x.nodeName(e, "select") && (e.options.length = 0)
                        }
                        return this
                    }, clone: function (e, t) {
                        return e = null == e ? !1 : e, t = null == t ? e : t, this.map(function () {
                            return x.clone(this, e, t)
                        })
                    }, html: function (e) {
                        return x.access(this, function (e) {
                            var n = this[0] || {}, r = 0, i = this.length;
                            if (e === t) return 1 === n.nodeType ? n.innerHTML.replace(gt, "") : t;
                            if (!("string" != typeof e || Tt.test(e) || !x.support.htmlSerialize && mt.test(e) || !x.support.leadingWhitespace && yt.test(e) || At[(bt.exec(e) || ["", ""])[1].toLowerCase()])) {
                                e = e.replace(vt, "<$1></$2>");
                                try {
                                    for (; i > r; r++) n = this[r] || {}, 1 === n.nodeType && (x.cleanData(Ft(n, !1)), n.innerHTML = e);
                                    n = 0
                                } catch (o) {
                                }
                            }
                            n && this.empty().append(e)
                        }, null, e, arguments.length)
                    }, replaceWith: function () {
                        var e = x.map(this, function (e) {
                            return [e.nextSibling, e.parentNode]
                        }), t = 0;
                        return this.domManip(arguments, function (n) {
                            var r = e[t++], i = e[t++];
                            i && (r && r.parentNode !== i && (r = this.nextSibling), x(this).remove(), i.insertBefore(n, r))
                        }, !0), t ? this : this.remove()
                    }, detach: function (e) {
                        return this.remove(e, !0)
                    }, domManip: function (e, t, n) {
                        e = d.apply([], e);
                        var r, i, o, a, s, l, u = 0, c = this.length, p = this, f = c - 1, h = e[0],
                            g = x.isFunction(h);
                        if (g || !(1 >= c || "string" != typeof h || x.support.checkClone) && Nt.test(h)) return this.each(function (r) {
                            var i = p.eq(r);
                            g && (e[0] = h.call(this, r, i.html())), i.domManip(e, t, n)
                        });
                        if (c && (l = x.buildFragment(e, this[0].ownerDocument, !1, !n && this), r = l.firstChild, 1 === l.childNodes.length && (l = r), r)) {
                            for (a = x.map(Ft(l, "script"), Ht), o = a.length; c > u; u++) i = l, u !== f && (i = x.clone(i, !0, !0), o && x.merge(a, Ft(i, "script"))), t.call(this[u], i, u);
                            if (o) for (s = a[a.length - 1].ownerDocument, x.map(a, qt), u = 0; o > u; u++) i = a[u], kt.test(i.type || "") && !x._data(i, "globalEval") && x.contains(s, i) && (i.src ? x._evalUrl(i.src) : x.globalEval((i.text || i.textContent || i.innerHTML || "").replace(St, "")));
                            l = r = null
                        }
                        return this
                    }
                });

                function Lt(e, t) {
                    return x.nodeName(e, "table") && x.nodeName(1 === t.nodeType ? t : t.firstChild, "tr") ? e.getElementsByTagName("tbody")[0] || e.appendChild(e.ownerDocument.createElement("tbody")) : e
                }

                function Ht(e) {
                    return e.type = (null !== x.find.attr(e, "type")) + "/" + e.type, e
                }

                function qt(e) {
                    var t = Et.exec(e.type);
                    return t ? e.type = t[1] : e.removeAttribute("type"), e
                }

                function _t(e, t) {
                    var n, r = 0;
                    for (; null != (n = e[r]); r++) x._data(n, "globalEval", !t || x._data(t[r], "globalEval"))
                }

                function Mt(e, t) {
                    if (1 === t.nodeType && x.hasData(e)) {
                        var n, r, i, o = x._data(e), a = x._data(t, o), s = o.events;
                        if (s) {
                            delete a.handle, a.events = {};
                            for (n in s) for (r = 0, i = s[n].length; i > r; r++) x.event.add(t, n, s[n][r])
                        }
                        a.data && (a.data = x.extend({}, a.data))
                    }
                }

                function Ot(e, t) {
                    var n, r, i;
                    if (1 === t.nodeType) {
                        if (n = t.nodeName.toLowerCase(), !x.support.noCloneEvent && t[x.expando]) {
                            i = x._data(t);
                            for (r in i.events) x.removeEvent(t, r, i.handle);
                            t.removeAttribute(x.expando)
                        }
                        "script" === n && t.text !== e.text ? (Ht(t).text = e.text, qt(t)) : "object" === n ? (t.parentNode && (t.outerHTML = e.outerHTML), x.support.html5Clone && e.innerHTML && !x.trim(t.innerHTML) && (t.innerHTML = e.innerHTML)) : "input" === n && Ct.test(e.type) ? (t.defaultChecked = t.checked = e.checked, t.value !== e.value && (t.value = e.value)) : "option" === n ? t.defaultSelected = t.selected = e.defaultSelected : ("input" === n || "textarea" === n) && (t.defaultValue = e.defaultValue)
                    }
                }

                x.each({
                    appendTo: "append",
                    prependTo: "prepend",
                    insertBefore: "before",
                    insertAfter: "after",
                    replaceAll: "replaceWith"
                }, function (e, t) {
                    x.fn[e] = function (e) {
                        var n, r = 0, i = [], o = x(e), a = o.length - 1;
                        for (; a >= r; r++) n = r === a ? this : this.clone(!0), x(o[r])[t](n), h.apply(i, n.get());
                        return this.pushStack(i)
                    }
                });

                function Ft(e, n) {
                    var r, o, a = 0,
                        s = typeof e.getElementsByTagName !== i ? e.getElementsByTagName(n || "*") : typeof e.querySelectorAll !== i ? e.querySelectorAll(n || "*") : t;
                    if (!s) for (s = [], r = e.childNodes || e; null != (o = r[a]); a++) !n || x.nodeName(o, n) ? s.push(o) : x.merge(s, Ft(o, n));
                    return n === t || n && x.nodeName(e, n) ? x.merge([e], s) : s
                }

                function Bt(e) {
                    Ct.test(e.type) && (e.defaultChecked = e.checked)
                }

                x.extend({
                    clone: function (e, t, n) {
                        var r, i, o, a, s, l = x.contains(e.ownerDocument, e);
                        if (x.support.html5Clone || x.isXMLDoc(e) || !mt.test("<" + e.nodeName + ">") ? o = e.cloneNode(!0) : (Dt.innerHTML = e.outerHTML, Dt.removeChild(o = Dt.firstChild)), !(x.support.noCloneEvent && x.support.noCloneChecked || 1 !== e.nodeType && 11 !== e.nodeType || x.isXMLDoc(e))) for (r = Ft(o), s = Ft(e), a = 0; null != (i = s[a]); ++a) r[a] && Ot(i, r[a]);
                        if (t) if (n) for (s = s || Ft(e), r = r || Ft(o), a = 0; null != (i = s[a]); a++) Mt(i, r[a]); else Mt(e, o);
                        return r = Ft(o, "script"), r.length > 0 && _t(r, !l && Ft(e, "script")), r = s = i = null, o
                    }, buildFragment: function (e, t, n, r) {
                        var i, o, a, s, l, u, c, p = e.length, f = dt(t), d = [], h = 0;
                        for (; p > h; h++) if (o = e[h], o || 0 === o) if ("object" === x.type(o)) x.merge(d, o.nodeType ? [o] : o); else if (wt.test(o)) {
                            s = s || f.appendChild(t.createElement("div")), l = (bt.exec(o) || ["", ""])[1].toLowerCase(), c = At[l] || At._default, s.innerHTML = c[1] + o.replace(vt, "<$1></$2>") + c[2], i = c[0];
                            while (i--) s = s.lastChild;
                            if (!x.support.leadingWhitespace && yt.test(o) && d.push(t.createTextNode(yt.exec(o)[0])), !x.support.tbody) {
                                o = "table" !== l || xt.test(o) ? "<table>" !== c[1] || xt.test(o) ? 0 : s : s.firstChild, i = o && o.childNodes.length;
                                while (i--) x.nodeName(u = o.childNodes[i], "tbody") && !u.childNodes.length && o.removeChild(u)
                            }
                            x.merge(d, s.childNodes), s.textContent = "";
                            while (s.firstChild) s.removeChild(s.firstChild);
                            s = f.lastChild
                        } else d.push(t.createTextNode(o));
                        s && f.removeChild(s), x.support.appendChecked || x.grep(Ft(d, "input"), Bt), h = 0;
                        while (o = d[h++]) if ((!r || -1 === x.inArray(o, r)) && (a = x.contains(o.ownerDocument, o), s = Ft(f.appendChild(o), "script"), a && _t(s), n)) {
                            i = 0;
                            while (o = s[i++]) kt.test(o.type || "") && n.push(o)
                        }
                        return s = null, f
                    }, cleanData: function (e, t) {
                        var n, r, o, a, s = 0, l = x.expando, u = x.cache, c = x.support.deleteExpando,
                            f = x.event.special;
                        for (; null != (n = e[s]); s++) if ((t || x.acceptData(n)) && (o = n[l], a = o && u[o])) {
                            if (a.events) for (r in a.events) f[r] ? x.event.remove(n, r) : x.removeEvent(n, r, a.handle);
                            u[o] && (delete u[o], c ? delete n[l] : typeof n.removeAttribute !== i ? n.removeAttribute(l) : n[l] = null, p.push(o))
                        }
                    }, _evalUrl: function (e) {
                        return x.ajax({url: e, type: "GET", dataType: "script", async: !1, global: !1, "throws": !0})
                    }
                }), x.fn.extend({
                    wrapAll: function (e) {
                        if (x.isFunction(e)) return this.each(function (t) {
                            x(this).wrapAll(e.call(this, t))
                        });
                        if (this[0]) {
                            var t = x(e, this[0].ownerDocument).eq(0).clone(!0);
                            this[0].parentNode && t.insertBefore(this[0]), t.map(function () {
                                var e = this;
                                while (e.firstChild && 1 === e.firstChild.nodeType) e = e.firstChild;
                                return e
                            }).append(this)
                        }
                        return this
                    }, wrapInner: function (e) {
                        return x.isFunction(e) ? this.each(function (t) {
                            x(this).wrapInner(e.call(this, t))
                        }) : this.each(function () {
                            var t = x(this), n = t.contents();
                            n.length ? n.wrapAll(e) : t.append(e)
                        })
                    }, wrap: function (e) {
                        var t = x.isFunction(e);
                        return this.each(function (n) {
                            x(this).wrapAll(t ? e.call(this, n) : e)
                        })
                    }, unwrap: function () {
                        return this.parent().each(function () {
                            x.nodeName(this, "body") || x(this).replaceWith(this.childNodes)
                        }).end()
                    }
                });
                var Pt, Rt, Wt, $t = /alpha\([^)]*\)/i, It = /opacity\s*=\s*([^)]*)/, zt = /^(top|right|bottom|left)$/,
                    Xt = /^(none|table(?!-c[ea]).+)/, Ut = /^margin/, Vt = RegExp("^(" + w + ")(.*)$", "i"),
                    Yt = RegExp("^(" + w + ")(?!px)[a-z%]+$", "i"), Jt = RegExp("^([+-])=(" + w + ")", "i"),
                    Gt = {BODY: "block"}, Qt = {position: "absolute", visibility: "hidden", display: "block"},
                    Kt = {letterSpacing: 0, fontWeight: 400}, Zt = ["Top", "Right", "Bottom", "Left"],
                    en = ["Webkit", "O", "Moz", "ms"];

                function tn(e, t) {
                    if (t in e) return t;
                    var n = t.charAt(0).toUpperCase() + t.slice(1), r = t, i = en.length;
                    while (i--) if (t = en[i] + n, t in e) return t;
                    return r
                }

                function nn(e, t) {
                    return e = t || e, "none" === x.css(e, "display") || !x.contains(e.ownerDocument, e)
                }

                function rn(e, t) {
                    var n, r, i, o = [], a = 0, s = e.length;
                    for (; s > a; a++) r = e[a], r.style && (o[a] = x._data(r, "olddisplay"), n = r.style.display, t ? (o[a] || "none" !== n || (r.style.display = ""), "" === r.style.display && nn(r) && (o[a] = x._data(r, "olddisplay", ln(r.nodeName)))) : o[a] || (i = nn(r), (n && "none" !== n || !i) && x._data(r, "olddisplay", i ? n : x.css(r, "display"))));
                    for (a = 0; s > a; a++) r = e[a], r.style && (t && "none" !== r.style.display && "" !== r.style.display || (r.style.display = t ? o[a] || "" : "none"));
                    return e
                }

                x.fn.extend({
                    css: function (e, n) {
                        return x.access(this, function (e, n, r) {
                            var i, o, a = {}, s = 0;
                            if (x.isArray(n)) {
                                for (o = Rt(e), i = n.length; i > s; s++) a[n[s]] = x.css(e, n[s], !1, o);
                                return a
                            }
                            return r !== t ? x.style(e, n, r) : x.css(e, n)
                        }, e, n, arguments.length > 1)
                    }, show: function () {
                        return rn(this, !0)
                    }, hide: function () {
                        return rn(this)
                    }, toggle: function (e) {
                        return "boolean" == typeof e ? e ? this.show() : this.hide() : this.each(function () {
                            nn(this) ? x(this).show() : x(this).hide()
                        })
                    }
                }), x.extend({
                    cssHooks: {
                        opacity: {
                            get: function (e, t) {
                                if (t) {
                                    var n = Wt(e, "opacity");
                                    return "" === n ? "1" : n
                                }
                            }
                        }
                    },
                    cssNumber: {
                        columnCount: !0,
                        fillOpacity: !0,
                        fontWeight: !0,
                        lineHeight: !0,
                        opacity: !0,
                        order: !0,
                        orphans: !0,
                        widows: !0,
                        zIndex: !0,
                        zoom: !0
                    },
                    cssProps: {"float": x.support.cssFloat ? "cssFloat" : "styleFloat"},
                    style: function (e, n, r, i) {
                        if (e && 3 !== e.nodeType && 8 !== e.nodeType && e.style) {
                            var o, a, s, l = x.camelCase(n), u = e.style;
                            if (n = x.cssProps[l] || (x.cssProps[l] = tn(u, l)), s = x.cssHooks[n] || x.cssHooks[l], r === t) return s && "get" in s && (o = s.get(e, !1, i)) !== t ? o : u[n];
                            if (a = typeof r, "string" === a && (o = Jt.exec(r)) && (r = (o[1] + 1) * o[2] + parseFloat(x.css(e, n)), a = "number"), !(null == r || "number" === a && isNaN(r) || ("number" !== a || x.cssNumber[l] || (r += "px"), x.support.clearCloneStyle || "" !== r || 0 !== n.indexOf("background") || (u[n] = "inherit"), s && "set" in s && (r = s.set(e, r, i)) === t))) try {
                                u[n] = r
                            } catch (c) {
                            }
                        }
                    },
                    css: function (e, n, r, i) {
                        var o, a, s, l = x.camelCase(n);
                        return n = x.cssProps[l] || (x.cssProps[l] = tn(e.style, l)), s = x.cssHooks[n] || x.cssHooks[l], s && "get" in s && (a = s.get(e, !0, r)), a === t && (a = Wt(e, n, i)), "normal" === a && n in Kt && (a = Kt[n]), "" === r || r ? (o = parseFloat(a), r === !0 || x.isNumeric(o) ? o || 0 : a) : a
                    }
                }), e.getComputedStyle ? (Rt = function (t) {
                    return e.getComputedStyle(t, null)
                }, Wt = function (e, n, r) {
                    var i, o, a, s = r || Rt(e), l = s ? s.getPropertyValue(n) || s[n] : t, u = e.style;
                    return s && ("" !== l || x.contains(e.ownerDocument, e) || (l = x.style(e, n)), Yt.test(l) && Ut.test(n) && (i = u.width, o = u.minWidth, a = u.maxWidth, u.minWidth = u.maxWidth = u.width = l, l = s.width, u.width = i, u.minWidth = o, u.maxWidth = a)), l
                }) : a.documentElement.currentStyle && (Rt = function (e) {
                    return e.currentStyle
                }, Wt = function (e, n, r) {
                    var i, o, a, s = r || Rt(e), l = s ? s[n] : t, u = e.style;
                    return null == l && u && u[n] && (l = u[n]), Yt.test(l) && !zt.test(n) && (i = u.left, o = e.runtimeStyle, a = o && o.left, a && (o.left = e.currentStyle.left), u.left = "fontSize" === n ? "1em" : l, l = u.pixelLeft + "px", u.left = i, a && (o.left = a)), "" === l ? "auto" : l
                });

                function on(e, t, n) {
                    var r = Vt.exec(t);
                    return r ? Math.max(0, r[1] - (n || 0)) + (r[2] || "px") : t
                }

                function an(e, t, n, r, i) {
                    var o = n === (r ? "border" : "content") ? 4 : "width" === t ? 1 : 0, a = 0;
                    for (; 4 > o; o += 2) "margin" === n && (a += x.css(e, n + Zt[o], !0, i)), r ? ("content" === n && (a -= x.css(e, "padding" + Zt[o], !0, i)), "margin" !== n && (a -= x.css(e, "border" + Zt[o] + "Width", !0, i))) : (a += x.css(e, "padding" + Zt[o], !0, i), "padding" !== n && (a += x.css(e, "border" + Zt[o] + "Width", !0, i)));
                    return a
                }

                function sn(e, t, n) {
                    var r = !0, i = "width" === t ? e.offsetWidth : e.offsetHeight, o = Rt(e),
                        a = x.support.boxSizing && "border-box" === x.css(e, "boxSizing", !1, o);
                    if (0 >= i || null == i) {
                        if (i = Wt(e, t, o), (0 > i || null == i) && (i = e.style[t]), Yt.test(i)) return i;
                        r = a && (x.support.boxSizingReliable || i === e.style[t]), i = parseFloat(i) || 0
                    }
                    return i + an(e, t, n || (a ? "border" : "content"), r, o) + "px"
                }

                function ln(e) {
                    var t = a, n = Gt[e];
                    return n || (n = un(e, t), "none" !== n && n || (Pt = (Pt || x("<iframe frameborder='0' width='0' height='0'/>").css("cssText", "display:block !important")).appendTo(t.documentElement), t = (Pt[0].contentWindow || Pt[0].contentDocument).document, t.write("<!doctype html><html><body>"), t.close(), n = un(e, t), Pt.detach()), Gt[e] = n), n
                }

                function un(e, t) {
                    var n = x(t.createElement(e)).appendTo(t.body), r = x.css(n[0], "display");
                    return n.remove(), r
                }

                x.each(["height", "width"], function (e, n) {
                    x.cssHooks[n] = {
                        get: function (e, r, i) {
                            return r ? 0 === e.offsetWidth && Xt.test(x.css(e, "display")) ? x.swap(e, Qt, function () {
                                return sn(e, n, i)
                            }) : sn(e, n, i) : t
                        }, set: function (e, t, r) {
                            var i = r && Rt(e);
                            return on(e, t, r ? an(e, n, r, x.support.boxSizing && "border-box" === x.css(e, "boxSizing", !1, i), i) : 0)
                        }
                    }
                }), x.support.opacity || (x.cssHooks.opacity = {
                    get: function (e, t) {
                        return It.test((t && e.currentStyle ? e.currentStyle.filter : e.style.filter) || "") ? .01 * parseFloat(RegExp.$1) + "" : t ? "1" : ""
                    }, set: function (e, t) {
                        var n = e.style, r = e.currentStyle, i = x.isNumeric(t) ? "alpha(opacity=" + 100 * t + ")" : "",
                            o = r && r.filter || n.filter || "";
                        n.zoom = 1, (t >= 1 || "" === t) && "" === x.trim(o.replace($t, "")) && n.removeAttribute && (n.removeAttribute("filter"), "" === t || r && !r.filter) || (n.filter = $t.test(o) ? o.replace($t, i) : o + " " + i)
                    }
                }), x(function () {
                    x.support.reliableMarginRight || (x.cssHooks.marginRight = {
                        get: function (e, n) {
                            return n ? x.swap(e, {display: "inline-block"}, Wt, [e, "marginRight"]) : t
                        }
                    }), !x.support.pixelPosition && x.fn.position && x.each(["top", "left"], function (e, n) {
                        x.cssHooks[n] = {
                            get: function (e, r) {
                                return r ? (r = Wt(e, n), Yt.test(r) ? x(e).position()[n] + "px" : r) : t
                            }
                        }
                    })
                }), x.expr && x.expr.filters && (x.expr.filters.hidden = function (e) {
                    return 0 >= e.offsetWidth && 0 >= e.offsetHeight || !x.support.reliableHiddenOffsets && "none" === (e.style && e.style.display || x.css(e, "display"))
                }, x.expr.filters.visible = function (e) {
                    return !x.expr.filters.hidden(e)
                }), x.each({margin: "", padding: "", border: "Width"}, function (e, t) {
                    x.cssHooks[e + t] = {
                        expand: function (n) {
                            var r = 0, i = {}, o = "string" == typeof n ? n.split(" ") : [n];
                            for (; 4 > r; r++) i[e + Zt[r] + t] = o[r] || o[r - 2] || o[0];
                            return i
                        }
                    }, Ut.test(e) || (x.cssHooks[e + t].set = on)
                });
                var cn = /%20/g, pn = /\[\]$/, fn = /\r?\n/g, dn = /^(?:submit|button|image|reset|file)$/i,
                    hn = /^(?:input|select|textarea|keygen)/i;
                x.fn.extend({
                    serialize: function () {
                        return x.param(this.serializeArray())
                    }, serializeArray: function () {
                        return this.map(function () {
                            var e = x.prop(this, "elements");
                            return e ? x.makeArray(e) : this
                        }).filter(function () {
                            var e = this.type;
                            return this.name && !x(this).is(":disabled") && hn.test(this.nodeName) && !dn.test(e) && (this.checked || !Ct.test(e))
                        }).map(function (e, t) {
                            var n = x(this).val();
                            return null == n ? null : x.isArray(n) ? x.map(n, function (e) {
                                return {name: t.name, value: e.replace(fn, "\r\n")}
                            }) : {name: t.name, value: n.replace(fn, "\r\n")}
                        }).get()
                    }
                }), x.param = function (e, n) {
                    var r, i = [], o = function (e, t) {
                        t = x.isFunction(t) ? t() : null == t ? "" : t, i[i.length] = encodeURIComponent(e) + "=" + encodeURIComponent(t)
                    };
                    if (n === t && (n = x.ajaxSettings && x.ajaxSettings.traditional), x.isArray(e) || e.jquery && !x.isPlainObject(e)) x.each(e, function () {
                        o(this.name, this.value)
                    }); else for (r in e) gn(r, e[r], n, o);
                    return i.join("&").replace(cn, "+")
                };

                function gn(e, t, n, r) {
                    var i;
                    if (x.isArray(t)) x.each(t, function (t, i) {
                        n || pn.test(e) ? r(e, i) : gn(e + "[" + ("object" == typeof i ? t : "") + "]", i, n, r)
                    }); else if (n || "object" !== x.type(t)) r(e, t); else for (i in t) gn(e + "[" + i + "]", t[i], n, r)
                }

                x.each("blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error contextmenu".split(" "), function (e, t) {
                    x.fn[t] = function (e, n) {
                        return arguments.length > 0 ? this.on(t, null, e, n) : this.trigger(t)
                    }
                }), x.fn.extend({
                    hover: function (e, t) {
                        return this.mouseenter(e).mouseleave(t || e)
                    }, bind: function (e, t, n) {
                        return this.on(e, null, t, n)
                    }, unbind: function (e, t) {
                        return this.off(e, null, t)
                    }, delegate: function (e, t, n, r) {
                        return this.on(t, e, n, r)
                    }, undelegate: function (e, t, n) {
                        return 1 === arguments.length ? this.off(e, "**") : this.off(t, e || "**", n)
                    }
                });
                var mn, yn, vn = x.now(), bn = /\?/, xn = /#.*$/, wn = /([?&])_=[^&]*/,
                    Tn = /^(.*?):[ \t]*([^\r\n]*)\r?$/gm,
                    Cn = /^(?:about|app|app-storage|.+-extension|file|res|widget):$/, Nn = /^(?:GET|HEAD)$/,
                    kn = /^\/\//, En = /^([\w.+-]+:)(?:\/\/([^\/?#:]*)(?::(\d+)|)|)/, Sn = x.fn.load, An = {}, jn = {},
                    Dn = "*/".concat("*");
                try {
                    yn = o.href
                } catch (Ln) {
                    yn = a.createElement("a"), yn.href = "", yn = yn.href
                }
                mn = En.exec(yn.toLowerCase()) || [];

                function Hn(e) {
                    return function (t, n) {
                        "string" != typeof t && (n = t, t = "*");
                        var r, i = 0, o = t.toLowerCase().match(T) || [];
                        if (x.isFunction(n)) while (r = o[i++]) "+" === r[0] ? (r = r.slice(1) || "*", (e[r] = e[r] || []).unshift(n)) : (e[r] = e[r] || []).push(n)
                    }
                }

                function qn(e, n, r, i) {
                    var o = {}, a = e === jn;

                    function s(l) {
                        var u;
                        return o[l] = !0, x.each(e[l] || [], function (e, l) {
                            var c = l(n, r, i);
                            return "string" != typeof c || a || o[c] ? a ? !(u = c) : t : (n.dataTypes.unshift(c), s(c), !1)
                        }), u
                    }

                    return s(n.dataTypes[0]) || !o["*"] && s("*")
                }

                function _n(e, n) {
                    var r, i, o = x.ajaxSettings.flatOptions || {};
                    for (i in n) n[i] !== t && ((o[i] ? e : r || (r = {}))[i] = n[i]);
                    return r && x.extend(!0, e, r), e
                }

                x.fn.load = function (e, n, r) {
                    if ("string" != typeof e && Sn) return Sn.apply(this, arguments);
                    var i, o, a, s = this, l = e.indexOf(" ");
                    return l >= 0 && (i = e.slice(l, e.length), e = e.slice(0, l)), x.isFunction(n) ? (r = n, n = t) : n && "object" == typeof n && (a = "POST"), s.length > 0 && x.ajax({
                        url: e,
                        type: a,
                        dataType: "html",
                        data: n
                    }).done(function (e) {
                        o = arguments, s.html(i ? x("<div>").append(x.parseHTML(e)).find(i) : e)
                    }).complete(r && function (e, t) {
                        s.each(r, o || [e.responseText, t, e])
                    }), this
                }, x.each(["ajaxStart", "ajaxStop", "ajaxComplete", "ajaxError", "ajaxSuccess", "ajaxSend"], function (e, t) {
                    x.fn[t] = function (e) {
                        return this.on(t, e)
                    }
                }), x.extend({
                    active: 0,
                    lastModified: {},
                    etag: {},
                    ajaxSettings: {
                        url: yn,
                        type: "GET",
                        isLocal: Cn.test(mn[1]),
                        global: !0,
                        processData: !0,
                        async: !0,
                        contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                        accepts: {
                            "*": Dn,
                            text: "text/plain",
                            html: "text/html",
                            xml: "application/xml, text/xml",
                            json: "application/json, text/javascript"
                        },
                        contents: {xml: /xml/, html: /html/, json: /json/},
                        responseFields: {xml: "responseXML", text: "responseText", json: "responseJSON"},
                        converters: {
                            "* text": String,
                            "text html": !0,
                            "text json": x.parseJSON,
                            "text xml": x.parseXML
                        },
                        flatOptions: {url: !0, context: !0}
                    },
                    ajaxSetup: function (e, t) {
                        return t ? _n(_n(e, x.ajaxSettings), t) : _n(x.ajaxSettings, e)
                    },
                    ajaxPrefilter: Hn(An),
                    ajaxTransport: Hn(jn),
                    ajax: function (e, n) {
                        "object" == typeof e && (n = e, e = t), n = n || {};
                        var r, i, o, a, s, l, u, c, p = x.ajaxSetup({}, n), f = p.context || p,
                            d = p.context && (f.nodeType || f.jquery) ? x(f) : x.event, h = x.Deferred(),
                            g = x.Callbacks("once memory"), m = p.statusCode || {}, y = {}, v = {}, b = 0,
                            w = "canceled", C = {
                                readyState: 0, getResponseHeader: function (e) {
                                    var t;
                                    if (2 === b) {
                                        if (!c) {
                                            c = {};
                                            while (t = Tn.exec(a)) c[t[1].toLowerCase()] = t[2]
                                        }
                                        t = c[e.toLowerCase()]
                                    }
                                    return null == t ? null : t
                                }, getAllResponseHeaders: function () {
                                    return 2 === b ? a : null
                                }, setRequestHeader: function (e, t) {
                                    var n = e.toLowerCase();
                                    return b || (e = v[n] = v[n] || e, y[e] = t), this
                                }, overrideMimeType: function (e) {
                                    return b || (p.mimeType = e), this
                                }, statusCode: function (e) {
                                    var t;
                                    if (e) if (2 > b) for (t in e) m[t] = [m[t], e[t]]; else C.always(e[C.status]);
                                    return this
                                }, abort: function (e) {
                                    var t = e || w;
                                    return u && u.abort(t), k(0, t), this
                                }
                            };
                        if (h.promise(C).complete = g.add, C.success = C.done, C.error = C.fail, p.url = ((e || p.url || yn) + "").replace(xn, "").replace(kn, mn[1] + "//"), p.type = n.method || n.type || p.method || p.type, p.dataTypes = x.trim(p.dataType || "*").toLowerCase().match(T) || [""], null == p.crossDomain && (r = En.exec(p.url.toLowerCase()), p.crossDomain = !(!r || r[1] === mn[1] && r[2] === mn[2] && (r[3] || ("http:" === r[1] ? "80" : "443")) === (mn[3] || ("http:" === mn[1] ? "80" : "443")))), p.data && p.processData && "string" != typeof p.data && (p.data = x.param(p.data, p.traditional)), qn(An, p, n, C), 2 === b) return C;
                        l = p.global, l && 0 === x.active++ && x.event.trigger("ajaxStart"), p.type = p.type.toUpperCase(), p.hasContent = !Nn.test(p.type), o = p.url, p.hasContent || (p.data && (o = p.url += (bn.test(o) ? "&" : "?") + p.data, delete p.data), p.cache === !1 && (p.url = wn.test(o) ? o.replace(wn, "$1_=" + vn++) : o + (bn.test(o) ? "&" : "?") + "_=" + vn++)), p.ifModified && (x.lastModified[o] && C.setRequestHeader("If-Modified-Since", x.lastModified[o]), x.etag[o] && C.setRequestHeader("If-None-Match", x.etag[o])), (p.data && p.hasContent && p.contentType !== !1 || n.contentType) && C.setRequestHeader("Content-Type", p.contentType), C.setRequestHeader("Accept", p.dataTypes[0] && p.accepts[p.dataTypes[0]] ? p.accepts[p.dataTypes[0]] + ("*" !== p.dataTypes[0] ? ", " + Dn + "; q=0.01" : "") : p.accepts["*"]);
                        for (i in p.headers) C.setRequestHeader(i, p.headers[i]);
                        if (p.beforeSend && (p.beforeSend.call(f, C, p) === !1 || 2 === b)) return C.abort();
                        w = "abort";
                        for (i in {success: 1, error: 1, complete: 1}) C[i](p[i]);
                        if (u = qn(jn, p, n, C)) {
                            C.readyState = 1, l && d.trigger("ajaxSend", [C, p]), p.async && p.timeout > 0 && (s = setTimeout(function () {
                                C.abort("timeout")
                            }, p.timeout));
                            try {
                                b = 1, u.send(y, k)
                            } catch (N) {
                                if (!(2 > b)) throw N;
                                k(-1, N)
                            }
                        } else k(-1, "No Transport");

                        function k(e, n, r, i) {
                            var c, y, v, w, T, N = n;
                            2 !== b && (b = 2, s && clearTimeout(s), u = t, a = i || "", C.readyState = e > 0 ? 4 : 0, c = e >= 200 && 300 > e || 304 === e, r && (w = Mn(p, C, r)), w = On(p, w, C, c), c ? (p.ifModified && (T = C.getResponseHeader("Last-Modified"), T && (x.lastModified[o] = T), T = C.getResponseHeader("etag"), T && (x.etag[o] = T)), 204 === e || "HEAD" === p.type ? N = "nocontent" : 304 === e ? N = "notmodified" : (N = w.state, y = w.data, v = w.error, c = !v)) : (v = N, (e || !N) && (N = "error", 0 > e && (e = 0))), C.status = e, C.statusText = (n || N) + "", c ? h.resolveWith(f, [y, N, C]) : h.rejectWith(f, [C, N, v]), C.statusCode(m), m = t, l && d.trigger(c ? "ajaxSuccess" : "ajaxError", [C, p, c ? y : v]), g.fireWith(f, [C, N]), l && (d.trigger("ajaxComplete", [C, p]), --x.active || x.event.trigger("ajaxStop")))
                        }

                        return C
                    },
                    getJSON: function (e, t, n) {
                        return x.get(e, t, n, "json")
                    },
                    getScript: function (e, n) {
                        return x.get(e, t, n, "script")
                    }
                }), x.each(["get", "post"], function (e, n) {
                    x[n] = function (e, r, i, o) {
                        return x.isFunction(r) && (o = o || i, i = r, r = t), x.ajax({
                            url: e,
                            type: n,
                            dataType: o,
                            data: r,
                            success: i
                        })
                    }
                });

                function Mn(e, n, r) {
                    var i, o, a, s, l = e.contents, u = e.dataTypes;
                    while ("*" === u[0]) u.shift(), o === t && (o = e.mimeType || n.getResponseHeader("Content-Type"));
                    if (o) for (s in l) if (l[s] && l[s].test(o)) {
                        u.unshift(s);
                        break
                    }
                    if (u[0] in r) a = u[0]; else {
                        for (s in r) {
                            if (!u[0] || e.converters[s + " " + u[0]]) {
                                a = s;
                                break
                            }
                            i || (i = s)
                        }
                        a = a || i
                    }
                    return a ? (a !== u[0] && u.unshift(a), r[a]) : t
                }

                function On(e, t, n, r) {
                    var i, o, a, s, l, u = {}, c = e.dataTypes.slice();
                    if (c[1]) for (a in e.converters) u[a.toLowerCase()] = e.converters[a];
                    o = c.shift();
                    while (o) if (e.responseFields[o] && (n[e.responseFields[o]] = t), !l && r && e.dataFilter && (t = e.dataFilter(t, e.dataType)), l = o, o = c.shift()) if ("*" === o) o = l; else if ("*" !== l && l !== o) {
                        if (a = u[l + " " + o] || u["* " + o], !a) for (i in u) if (s = i.split(" "), s[1] === o && (a = u[l + " " + s[0]] || u["* " + s[0]])) {
                            a === !0 ? a = u[i] : u[i] !== !0 && (o = s[0], c.unshift(s[1]));
                            break
                        }
                        if (a !== !0) if (a && e["throws"]) t = a(t); else try {
                            t = a(t)
                        } catch (p) {
                            return {state: "parsererror", error: a ? p : "No conversion from " + l + " to " + o}
                        }
                    }
                    return {state: "success", data: t}
                }

                x.ajaxSetup({
                    accepts: {script: "text/javascript, application/javascript, application/ecmascript, application/x-ecmascript"},
                    contents: {script: /(?:java|ecma)script/},
                    converters: {
                        "text script": function (e) {
                            return x.globalEval(e), e
                        }
                    }
                }), x.ajaxPrefilter("script", function (e) {
                    e.cache === t && (e.cache = !1), e.crossDomain && (e.type = "GET", e.global = !1)
                }), x.ajaxTransport("script", function (e) {
                    if (e.crossDomain) {
                        var n, r = a.head || x("head")[0] || a.documentElement;
                        return {
                            send: function (t, i) {
                                n = a.createElement("script"), n.async = !0, e.scriptCharset && (n.charset = e.scriptCharset), n.src = e.url, n.onload = n.onreadystatechange = function (e, t) {
                                    (t || !n.readyState || /loaded|complete/.test(n.readyState)) && (n.onload = n.onreadystatechange = null, n.parentNode && n.parentNode.removeChild(n), n = null, t || i(200, "success"))
                                }, r.insertBefore(n, r.firstChild)
                            }, abort: function () {
                                n && n.onload(t, !0)
                            }
                        }
                    }
                });
                var Fn = [], Bn = /(=)\?(?=&|$)|\?\?/;
                x.ajaxSetup({
                    jsonp: "callback", jsonpCallback: function () {
                        var e = Fn.pop() || x.expando + "_" + vn++;
                        return this[e] = !0, e
                    }
                }), x.ajaxPrefilter("json jsonp", function (n, r, i) {
                    var o, a, s,
                        l = n.jsonp !== !1 && (Bn.test(n.url) ? "url" : "string" == typeof n.data && !(n.contentType || "").indexOf("application/x-www-form-urlencoded") && Bn.test(n.data) && "data");
                    return l || "jsonp" === n.dataTypes[0] ? (o = n.jsonpCallback = x.isFunction(n.jsonpCallback) ? n.jsonpCallback() : n.jsonpCallback, l ? n[l] = n[l].replace(Bn, "$1" + o) : n.jsonp !== !1 && (n.url += (bn.test(n.url) ? "&" : "?") + n.jsonp + "=" + o), n.converters["script json"] = function () {
                        return s || x.error(o + " was not called"), s[0]
                    }, n.dataTypes[0] = "json", a = e[o], e[o] = function () {
                        s = arguments
                    }, i.always(function () {
                        e[o] = a, n[o] && (n.jsonpCallback = r.jsonpCallback, Fn.push(o)), s && x.isFunction(a) && a(s[0]), s = a = t
                    }), "script") : t
                });
                var Pn, Rn, Wn = 0, $n = e.ActiveXObject && function () {
                    var e;
                    for (e in Pn) Pn[e](t, !0)
                };

                function In() {
                    try {
                        return new e.XMLHttpRequest
                    } catch (t) {
                    }
                }

                function zn() {
                    try {
                        return new e.ActiveXObject("Microsoft.XMLHTTP")
                    } catch (t) {
                    }
                }

                x.ajaxSettings.xhr = e.ActiveXObject ? function () {
                    return !this.isLocal && In() || zn()
                } : In, Rn = x.ajaxSettings.xhr(), x.support.cors = !!Rn && "withCredentials" in Rn, Rn = x.support.ajax = !!Rn, Rn && x.ajaxTransport(function (n) {
                    if (!n.crossDomain || x.support.cors) {
                        var r;
                        return {
                            send: function (i, o) {
                                var a, s, l = n.xhr();
                                if (n.username ? l.open(n.type, n.url, n.async, n.username, n.password) : l.open(n.type, n.url, n.async), n.xhrFields) for (s in n.xhrFields) l[s] = n.xhrFields[s];
                                n.mimeType && l.overrideMimeType && l.overrideMimeType(n.mimeType), n.crossDomain || i["X-Requested-With"] || (i["X-Requested-With"] = "XMLHttpRequest");
                                try {
                                    for (s in i) l.setRequestHeader(s, i[s])
                                } catch (u) {
                                }
                                l.send(n.hasContent && n.data || null), r = function (e, i) {
                                    var s, u, c, p;
                                    try {
                                        if (r && (i || 4 === l.readyState)) if (r = t, a && (l.onreadystatechange = x.noop, $n && delete Pn[a]), i) 4 !== l.readyState && l.abort(); else {
                                            p = {}, s = l.status, u = l.getAllResponseHeaders(), "string" == typeof l.responseText && (p.text = l.responseText);
                                            try {
                                                c = l.statusText
                                            } catch (f) {
                                                c = ""
                                            }
                                            s || !n.isLocal || n.crossDomain ? 1223 === s && (s = 204) : s = p.text ? 200 : 404
                                        }
                                    } catch (d) {
                                        i || o(-1, d)
                                    }
                                    p && o(s, c, p, u)
                                }, n.async ? 4 === l.readyState ? setTimeout(r) : (a = ++Wn, $n && (Pn || (Pn = {}, x(e).unload($n)), Pn[a] = r), l.onreadystatechange = r) : r()
                            }, abort: function () {
                                r && r(t, !0)
                            }
                        }
                    }
                });
                var Xn, Un, Vn = /^(?:toggle|show|hide)$/, Yn = RegExp("^(?:([+-])=|)(" + w + ")([a-z%]*)$", "i"),
                    Jn = /queueHooks$/, Gn = [nr], Qn = {
                        "*": [function (e, t) {
                            var n = this.createTween(e, t), r = n.cur(), i = Yn.exec(t),
                                o = i && i[3] || (x.cssNumber[e] ? "" : "px"),
                                a = (x.cssNumber[e] || "px" !== o && +r) && Yn.exec(x.css(n.elem, e)), s = 1, l = 20;
                            if (a && a[3] !== o) {
                                o = o || a[3], i = i || [], a = +r || 1;
                                do s = s || ".5", a /= s, x.style(n.elem, e, a + o); while (s !== (s = n.cur() / r) && 1 !== s && --l)
                            }
                            return i && (a = n.start = +a || +r || 0, n.unit = o, n.end = i[1] ? a + (i[1] + 1) * i[2] : +i[2]), n
                        }]
                    };

                function Kn() {
                    return setTimeout(function () {
                        Xn = t
                    }), Xn = x.now()
                }

                function Zn(e, t, n) {
                    var r, i = (Qn[t] || []).concat(Qn["*"]), o = 0, a = i.length;
                    for (; a > o; o++) if (r = i[o].call(n, t, e)) return r
                }

                function er(e, t, n) {
                    var r, i, o = 0, a = Gn.length, s = x.Deferred().always(function () {
                        delete l.elem
                    }), l = function () {
                        if (i) return !1;
                        var t = Xn || Kn(), n = Math.max(0, u.startTime + u.duration - t), r = n / u.duration || 0,
                            o = 1 - r, a = 0, l = u.tweens.length;
                        for (; l > a; a++) u.tweens[a].run(o);
                        return s.notifyWith(e, [u, o, n]), 1 > o && l ? n : (s.resolveWith(e, [u]), !1)
                    }, u = s.promise({
                        elem: e,
                        props: x.extend({}, t),
                        opts: x.extend(!0, {specialEasing: {}}, n),
                        originalProperties: t,
                        originalOptions: n,
                        startTime: Xn || Kn(),
                        duration: n.duration,
                        tweens: [],
                        createTween: function (t, n) {
                            var r = x.Tween(e, u.opts, t, n, u.opts.specialEasing[t] || u.opts.easing);
                            return u.tweens.push(r), r
                        },
                        stop: function (t) {
                            var n = 0, r = t ? u.tweens.length : 0;
                            if (i) return this;
                            for (i = !0; r > n; n++) u.tweens[n].run(1);
                            return t ? s.resolveWith(e, [u, t]) : s.rejectWith(e, [u, t]), this
                        }
                    }), c = u.props;
                    for (tr(c, u.opts.specialEasing); a > o; o++) if (r = Gn[o].call(u, e, c, u.opts)) return r;
                    return x.map(c, Zn, u), x.isFunction(u.opts.start) && u.opts.start.call(e, u), x.fx.timer(x.extend(l, {
                        elem: e,
                        anim: u,
                        queue: u.opts.queue
                    })), u.progress(u.opts.progress).done(u.opts.done, u.opts.complete).fail(u.opts.fail).always(u.opts.always)
                }

                function tr(e, t) {
                    var n, r, i, o, a;
                    for (n in e) if (r = x.camelCase(n), i = t[r], o = e[n], x.isArray(o) && (i = o[1], o = e[n] = o[0]), n !== r && (e[r] = o, delete e[n]), a = x.cssHooks[r], a && "expand" in a) {
                        o = a.expand(o), delete e[r];
                        for (n in o) n in e || (e[n] = o[n], t[n] = i)
                    } else t[r] = i
                }

                x.Animation = x.extend(er, {
                    tweener: function (e, t) {
                        x.isFunction(e) ? (t = e, e = ["*"]) : e = e.split(" ");
                        var n, r = 0, i = e.length;
                        for (; i > r; r++) n = e[r], Qn[n] = Qn[n] || [], Qn[n].unshift(t)
                    }, prefilter: function (e, t) {
                        t ? Gn.unshift(e) : Gn.push(e)
                    }
                });

                function nr(e, t, n) {
                    var r, i, o, a, s, l, u = this, c = {}, p = e.style, f = e.nodeType && nn(e),
                        d = x._data(e, "fxshow");
                    n.queue || (s = x._queueHooks(e, "fx"), null == s.unqueued && (s.unqueued = 0, l = s.empty.fire, s.empty.fire = function () {
                        s.unqueued || l()
                    }), s.unqueued++, u.always(function () {
                        u.always(function () {
                            s.unqueued--, x.queue(e, "fx").length || s.empty.fire()
                        })
                    })), 1 === e.nodeType && ("height" in t || "width" in t) && (n.overflow = [p.overflow, p.overflowX, p.overflowY], "inline" === x.css(e, "display") && "none" === x.css(e, "float") && (x.support.inlineBlockNeedsLayout && "inline" !== ln(e.nodeName) ? p.zoom = 1 : p.display = "inline-block")), n.overflow && (p.overflow = "hidden", x.support.shrinkWrapBlocks || u.always(function () {
                        p.overflow = n.overflow[0], p.overflowX = n.overflow[1], p.overflowY = n.overflow[2]
                    }));
                    for (r in t) if (i = t[r], Vn.exec(i)) {
                        if (delete t[r], o = o || "toggle" === i, i === (f ? "hide" : "show")) continue;
                        c[r] = d && d[r] || x.style(e, r)
                    }
                    if (!x.isEmptyObject(c)) {
                        d ? "hidden" in d && (f = d.hidden) : d = x._data(e, "fxshow", {}), o && (d.hidden = !f), f ? x(e).show() : u.done(function () {
                            x(e).hide()
                        }), u.done(function () {
                            var t;
                            x._removeData(e, "fxshow");
                            for (t in c) x.style(e, t, c[t])
                        });
                        for (r in c) a = Zn(f ? d[r] : 0, r, u), r in d || (d[r] = a.start, f && (a.end = a.start, a.start = "width" === r || "height" === r ? 1 : 0))
                    }
                }

                function rr(e, t, n, r, i) {
                    return new rr.prototype.init(e, t, n, r, i)
                }

                x.Tween = rr, rr.prototype = {
                    constructor: rr, init: function (e, t, n, r, i, o) {
                        this.elem = e, this.prop = n, this.easing = i || "swing", this.options = t, this.start = this.now = this.cur(), this.end = r, this.unit = o || (x.cssNumber[n] ? "" : "px")
                    }, cur: function () {
                        var e = rr.propHooks[this.prop];
                        return e && e.get ? e.get(this) : rr.propHooks._default.get(this)
                    }, run: function (e) {
                        var t, n = rr.propHooks[this.prop];
                        return this.pos = t = this.options.duration ? x.easing[this.easing](e, this.options.duration * e, 0, 1, this.options.duration) : e, this.now = (this.end - this.start) * t + this.start, this.options.step && this.options.step.call(this.elem, this.now, this), n && n.set ? n.set(this) : rr.propHooks._default.set(this), this
                    }
                }, rr.prototype.init.prototype = rr.prototype, rr.propHooks = {
                    _default: {
                        get: function (e) {
                            var t;
                            return null == e.elem[e.prop] || e.elem.style && null != e.elem.style[e.prop] ? (t = x.css(e.elem, e.prop, ""), t && "auto" !== t ? t : 0) : e.elem[e.prop]
                        }, set: function (e) {
                            x.fx.step[e.prop] ? x.fx.step[e.prop](e) : e.elem.style && (null != e.elem.style[x.cssProps[e.prop]] || x.cssHooks[e.prop]) ? x.style(e.elem, e.prop, e.now + e.unit) : e.elem[e.prop] = e.now
                        }
                    }
                }, rr.propHooks.scrollTop = rr.propHooks.scrollLeft = {
                    set: function (e) {
                        e.elem.nodeType && e.elem.parentNode && (e.elem[e.prop] = e.now)
                    }
                }, x.each(["toggle", "show", "hide"], function (e, t) {
                    var n = x.fn[t];
                    x.fn[t] = function (e, r, i) {
                        return null == e || "boolean" == typeof e ? n.apply(this, arguments) : this.animate(ir(t, !0), e, r, i)
                    }
                }), x.fn.extend({
                    fadeTo: function (e, t, n, r) {
                        return this.filter(nn).css("opacity", 0).show().end().animate({opacity: t}, e, n, r)
                    }, animate: function (e, t, n, r) {
                        var i = x.isEmptyObject(e), o = x.speed(t, n, r), a = function () {
                            var t = er(this, x.extend({}, e), o);
                            (i || x._data(this, "finish")) && t.stop(!0)
                        };
                        return a.finish = a, i || o.queue === !1 ? this.each(a) : this.queue(o.queue, a)
                    }, stop: function (e, n, r) {
                        var i = function (e) {
                            var t = e.stop;
                            delete e.stop, t(r)
                        };
                        return "string" != typeof e && (r = n, n = e, e = t), n && e !== !1 && this.queue(e || "fx", []), this.each(function () {
                            var t = !0, n = null != e && e + "queueHooks", o = x.timers, a = x._data(this);
                            if (n) a[n] && a[n].stop && i(a[n]); else for (n in a) a[n] && a[n].stop && Jn.test(n) && i(a[n]);
                            for (n = o.length; n--;) o[n].elem !== this || null != e && o[n].queue !== e || (o[n].anim.stop(r), t = !1, o.splice(n, 1));
                            (t || !r) && x.dequeue(this, e)
                        })
                    }, finish: function (e) {
                        return e !== !1 && (e = e || "fx"), this.each(function () {
                            var t, n = x._data(this), r = n[e + "queue"], i = n[e + "queueHooks"], o = x.timers,
                                a = r ? r.length : 0;
                            for (n.finish = !0, x.queue(this, e, []), i && i.stop && i.stop.call(this, !0), t = o.length; t--;) o[t].elem === this && o[t].queue === e && (o[t].anim.stop(!0), o.splice(t, 1));
                            for (t = 0; a > t; t++) r[t] && r[t].finish && r[t].finish.call(this);
                            delete n.finish
                        })
                    }
                });

                function ir(e, t) {
                    var n, r = {height: e}, i = 0;
                    for (t = t ? 1 : 0; 4 > i; i += 2 - t) n = Zt[i], r["margin" + n] = r["padding" + n] = e;
                    return t && (r.opacity = r.width = e), r
                }

                x.each({
                    slideDown: ir("show"),
                    slideUp: ir("hide"),
                    slideToggle: ir("toggle"),
                    fadeIn: {opacity: "show"},
                    fadeOut: {opacity: "hide"},
                    fadeToggle: {opacity: "toggle"}
                }, function (e, t) {
                    x.fn[e] = function (e, n, r) {
                        return this.animate(t, e, n, r)
                    }
                }), x.speed = function (e, t, n) {
                    var r = e && "object" == typeof e ? x.extend({}, e) : {
                        complete: n || !n && t || x.isFunction(e) && e,
                        duration: e,
                        easing: n && t || t && !x.isFunction(t) && t
                    };
                    return r.duration = x.fx.off ? 0 : "number" == typeof r.duration ? r.duration : r.duration in x.fx.speeds ? x.fx.speeds[r.duration] : x.fx.speeds._default, (null == r.queue || r.queue === !0) && (r.queue = "fx"), r.old = r.complete, r.complete = function () {
                        x.isFunction(r.old) && r.old.call(this), r.queue && x.dequeue(this, r.queue)
                    }, r
                }, x.easing = {
                    linear: function (e) {
                        return e
                    }, swing: function (e) {
                        return .5 - Math.cos(e * Math.PI) / 2
                    }
                }, x.timers = [], x.fx = rr.prototype.init, x.fx.tick = function () {
                    var e, n = x.timers, r = 0;
                    for (Xn = x.now(); n.length > r; r++) e = n[r], e() || n[r] !== e || n.splice(r--, 1);
                    n.length || x.fx.stop(), Xn = t
                }, x.fx.timer = function (e) {
                    e() && x.timers.push(e) && x.fx.start()
                }, x.fx.interval = 13, x.fx.start = function () {
                    Un || (Un = setInterval(x.fx.tick, x.fx.interval))
                }, x.fx.stop = function () {
                    clearInterval(Un), Un = null
                }, x.fx.speeds = {
                    slow: 600,
                    fast: 200,
                    _default: 400
                }, x.fx.step = {}, x.expr && x.expr.filters && (x.expr.filters.animated = function (e) {
                    return x.grep(x.timers, function (t) {
                        return e === t.elem
                    }).length
                }), x.fn.offset = function (e) {
                    if (arguments.length) return e === t ? this : this.each(function (t) {
                        x.offset.setOffset(this, e, t)
                    });
                    var n, r, o = {top: 0, left: 0}, a = this[0], s = a && a.ownerDocument;
                    if (s) return n = s.documentElement, x.contains(n, a) ? (typeof a.getBoundingClientRect !== i && (o = a.getBoundingClientRect()), r = or(s), {
                        top: o.top + (r.pageYOffset || n.scrollTop) - (n.clientTop || 0),
                        left: o.left + (r.pageXOffset || n.scrollLeft) - (n.clientLeft || 0)
                    }) : o
                }, x.offset = {
                    setOffset: function (e, t, n) {
                        var r = x.css(e, "position");
                        "static" === r && (e.style.position = "relative");
                        var i = x(e), o = i.offset(), a = x.css(e, "top"), s = x.css(e, "left"),
                            l = ("absolute" === r || "fixed" === r) && x.inArray("auto", [a, s]) > -1, u = {}, c = {},
                            p, f;
                        l ? (c = i.position(), p = c.top, f = c.left) : (p = parseFloat(a) || 0, f = parseFloat(s) || 0), x.isFunction(t) && (t = t.call(e, n, o)), null != t.top && (u.top = t.top - o.top + p), null != t.left && (u.left = t.left - o.left + f), "using" in t ? t.using.call(e, u) : i.css(u)
                    }
                }, x.fn.extend({
                    position: function () {
                        if (this[0]) {
                            var e, t, n = {top: 0, left: 0}, r = this[0];
                            return "fixed" === x.css(r, "position") ? t = r.getBoundingClientRect() : (e = this.offsetParent(), t = this.offset(), x.nodeName(e[0], "html") || (n = e.offset()), n.top += x.css(e[0], "borderTopWidth", !0), n.left += x.css(e[0], "borderLeftWidth", !0)), {
                                top: t.top - n.top - x.css(r, "marginTop", !0),
                                left: t.left - n.left - x.css(r, "marginLeft", !0)
                            }
                        }
                    }, offsetParent: function () {
                        return this.map(function () {
                            var e = this.offsetParent || s;
                            while (e && !x.nodeName(e, "html") && "static" === x.css(e, "position")) e = e.offsetParent;
                            return e || s
                        })
                    }
                }), x.each({scrollLeft: "pageXOffset", scrollTop: "pageYOffset"}, function (e, n) {
                    var r = /Y/.test(n);
                    x.fn[e] = function (i) {
                        return x.access(this, function (e, i, o) {
                            var a = or(e);
                            return o === t ? a ? n in a ? a[n] : a.document.documentElement[i] : e[i] : (a ? a.scrollTo(r ? x(a).scrollLeft() : o, r ? o : x(a).scrollTop()) : e[i] = o, t)
                        }, e, i, arguments.length, null)
                    }
                });

                function or(e) {
                    return x.isWindow(e) ? e : 9 === e.nodeType ? e.defaultView || e.parentWindow : !1
                }

                x.each({Height: "height", Width: "width"}, function (e, n) {
                    x.each({padding: "inner" + e, content: n, "": "outer" + e}, function (r, i) {
                        x.fn[i] = function (i, o) {
                            var a = arguments.length && (r || "boolean" != typeof i),
                                s = r || (i === !0 || o === !0 ? "margin" : "border");
                            return x.access(this, function (n, r, i) {
                                var o;
                                return x.isWindow(n) ? n.document.documentElement["client" + e] : 9 === n.nodeType ? (o = n.documentElement, Math.max(n.body["scroll" + e], o["scroll" + e], n.body["offset" + e], o["offset" + e], o["client" + e])) : i === t ? x.css(n, r, s) : x.style(n, r, i, s)
                            }, n, a ? i : t, a, null)
                        }
                    })
                }), x.fn.size = function () {
                    return this.length
                }, x.fn.andSelf = x.fn.addBack, "object" == typeof module && module && "object" == typeof module.exports ? module.exports = x : (e.jQuery = e.$ = x, "function" == typeof define && define.amd && define("jquery", [], function () {
                    return x
                }))
            })(window);
            jQuery.noConflict();

            function containsSerialisedString(text) {
                // we can't display the highlight on objects with strings (manifest as "s:digit") because this might change the length
                return ((/s:\d/.exec(text)) ? true : false);
            }


            // patch console free browsers
            window.console = window.console || {
                log: function () {
                }
            };

            ;

            (function ($) {

                var srdb;

                srdb = function () {

                    var t = this,
                        dom = $('html');

                    $.extend(t, {

                        errors: {},
                        report: {},
                        info: {},
                        prev_data: {},
                        tables: 0,
                        rows: 0,
                        changes: 0,
                        updates: 0,
                        time: [0.0],
                        button: false,
                        running: false,
                        countdown: null,
                        escape: false,
                        searches: 1,

                        // constructor
                        init: function () {

                            // search replace ui
                            if ($('.row-db').length) {

                                // show/hide tables
                                dom.on('click', '[name="use_tables"]', t.toggle_tables);
                                dom.find('[name="use_tables"][checked]').click();

                                // toggle regex mode
                                dom.on('click', '[name="regex"]', t.toggle_regex);
                                dom.find('[name="regex"][checked]').click();

                                // ajax form
                                dom.on('submit', 'form', t.submit_proxy);
                                dom.on('click', '[type="submit"]', t.submit);

                                // prevent accidental browsing away
                                window.onbeforeunload = function () {
                                    return t.running ? t.confirm_strings.unload_running : t.confirm_strings.unload_default;
                                };

                                // deleted ui
                            } else {

                                // mailchimp
                                dom.on('submit', 'form[action*="list-manage.com"]', t.mailchimp);

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
                    </p>'
                        ,
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

                        confirm_strings: {
                            live_run: 'Are you absolutely ready to run search/replace? Make sure you have backed up your database!',
                            modify: 'Are you absolutely ready to modify the tables? Make sure you have backed up your database!',
                            unload_default: 'DON\'T FORGET TO DELETE THIS SCRIPT!!!\n\nClick the delete button at the bottom to remove it.',
                            unload_running: 'The script is still in progress, do you definitely want to leave this page?'
                        },

                        toggle_tables: function () {
                            if (this.id == 'all_tables') {
                                dom.find('.table-select').slideUp(400);
                            } else {
                                dom.find('.table-select').slideDown(400);
                            }
                        },

                        add_search: function () {
                            var $row = $('.sr-boxes');
                            $new_search = $('<div class="sr-box-' + t.searches + '"></div>').appendTo($row);
                            $('<label for="search-' + t.searches + '"><span class="label-text">replace</span> ' +
                                '<span class="hide-if-regex-off regex-left">/</span>' +
                                '<input id="search-' + t.searches + '" type="text" placeholder="search for&hellip;" ' +
                                'value="<?php $this->esc_html_attr( $this->search,
                                    true ); ?>" name="search-' + t.searches + '"/> ' +
                                '<span class="hide-if-regex-off regex-right">/</span></label> ' +
                                '<label for="replace-' + t.searches + '"><span class="label-text">with </span>' +
                                '<input id="replace-' + t.searches + '" type="text" placeholder="replace with&hellip;"' +
                                'value="<?php $this->esc_html_attr( $this->replace,
                                    true ); ?>" name="replace-' + t.searches + '"/></label>').appendTo($new_search);

                            if (t.searches == 1)
                                dom.removeClass('multisearch-off');

                            t.searches = t.searches + 1;

                        },

                        remove_search: function () {
                            t.searches = t.searches - 1;
                            key = '.sr-box-' + t.searches;
                            $rem_search = $(key);
                            $rem_search.remove();
                            if (t.searches == 1)
                                dom.addClass('multisearch-off');
                        },

                        toggle_regex: function () {
                            if ($(this).is(':checked'))
                                dom.removeClass('regex-off').addClass('regex-on');
                            else
                                dom.removeClass('regex-on').addClass('regex-off');
                        },

                        reset: function () {
                            t.errors = {};
                            t.report = {};
                            t.tables = 0;
                            t.rows = 0;
                            t.changes = 0;
                            t.updates = 0;
                            t.time = [0.0];
                        },

                        map_form_data: function ($form) {
                            var data_temp = $form.serializeArray(),
                                data = {};
                            $.map(data_temp, function (field, i) {
                                if (data[field.name]) {
                                    if (!$.isArray(data[field.name]))
                                        data[field.name] = [data[field.name]];
                                    data[field.name].push(field.value);
                                } else {
                                    if (field.value === '1')
                                        field.value = true;
                                    data[field.name] = field.value;
                                }
                            });
                            return data;
                        },

                        submit_proxy: function (e) {
                            if (t.button !== 'submit[delete]')
                                return false;
                            return true;
                        },

                        submit: function (e) {

                            // workaround for submission not coming from a button click
                            var $button = $(this),
                                $form = $(this).parents('form'),
                                submit = $button.attr('name'),
                                button_text = $button.val(),
                                seconds = 5;

                            // track button clicked
                            t.button = submit;

                            // reset escape parameter
                            t.escape = false;

                            // add spinner
                            $button.addClass('active');

                            if (submit == 'submit[delete]' && !t.running) {
                                if (!confirm('Do you really want to delete the Search/Replace script directory and -all its contents-?')) {
                                    t.complete();
                                    return false;
                                }

                                window.onbeforeunload = null;
                                $('[type="submit"]').not($button).attr('disabled', 'disabled');
                                return true;
                            }

                            if (submit == 'submit[liverun]' && !window.confirm(t.confirm_strings.live_run)) {
                                t.complete();
                                return false;
                            }

                            if ((submit == 'submit[innodb]' || submit == 'submit[utf8]' || submit == 'submit[utf8mb4]') && !window.confirm(t.confirm_strings.modify)) {
                                t.complete();
                                return false;
                            }

                            // disable buttons & add spinner
                            $('[type="submit"]').attr('disabled', 'disabled');

                            // stop normal submission
                            e.preventDefault();

                            // reset reports
                            t.reset();

                            // get form data as an object
                            data = t.map_form_data($form);

                            // use all tables if none selected
                            if (dom.find('#all_tables').is(':checked') || !data['tables[]'] || !data['tables[]'].length)
                                data['tables[]'] = $.map($('select[name^="tables"] option'), function (el, i) {
                                    return $(el).attr('value');
                                });

                            data['search'] = [];
                            data['replace'] = [];
                            for (i = 0; i < t.searches; i++) {
                                s_key = 'search-' + i;
                                r_key = 'replace-' + i;
                                data['search'].push(data[s_key]);
                                data['replace'].push(data[r_key])
                            }

                            // check we don't just have one table selected as we get a string not array
                            if (!$.isArray(data['tables[]']))
                                data['tables[]'] = [data['tables[]']];

                            // add in ajax and submit params
                            data = $.extend({
                                ajax: true,
                                submit: submit
                            }, data);

                            // count down & stop button
                            if (submit.match(/dryrun|liverun|innodb|utf8|utf8mb4/)) {

                                // insert stop button
                                $('<input type="submit" name="submit[stop]" value="stop" class="stop-button" />')
                                    .click(function () {
                                        clearInterval(t.countdown);
                                        t.escape = true;
                                        t.complete();
                                        $('[type="submit"].db-required').removeAttr('disabled');
                                        $button.val(button_text);
                                    })
                                    .insertAfter($button);

                                if (submit.match(/liverun|innodb|utf8|utf8mb4/)) {

                                    $button.val(button_text + ' in ... ' + seconds);

                                    t.countdown = setInterval(function () {
                                        if (seconds == 0) {
                                            clearInterval(t.countdown);
                                            $button.val(button_text);
                                            t.run(data);
                                            return;
                                        }
                                        $button.val(button_text + ' in ... ' + --seconds);
                                    }, 1000);

                                } else {
                                    t.run(data);
                                }

                            } else {
                                t.run(data);
                            }

                            return false;
                        },

                        // trigger ajax
                        run: function (data) {
                            var $feedback = $('.errors, .report'),
                                feedback_length = $feedback.length;

                            // set running flag
                            t.running = true;

                            // clear previous errors
                            if (feedback_length) {
                                $feedback.each(function (i) {
                                    $(this).fadeOut(200, function () {
                                        $(this).remove();

                                        // start recursive table post
                                        if (i + 1 == feedback_length)
                                            t.recursive_fetch_json(data, 0);
                                    });
                                });
                            } else {
                                // start recursive table post
                                t.recursive_fetch_json(data, 0);
                            }

                            return false;
                        },

                        complete: function () {
                            // remove spinner
                            $('[type="submit"]')
                                .removeClass('active')
                                .not('.db-required')
                                .removeAttr('disabled');
                            if (typeof t.errors.db != 'undefined' && !t.errors.db.length)
                                $('[type="submit"].db-required').removeAttr('disabled');
                            t.running = false;
                            $('.stop-button').remove();
                        },

                        recursive_fetch_json: function (data, i) {

                            // break from loop
                            if (t.escape) {
                                return false;
                            }
                            if (data['tables[]'].length && typeof data['tables[]'][i] == 'undefined') {
                                t.complete();
                                return false;
                            }

                            // clone
                            var post_data = $.extend(true, {}, data),
                                dry_run = data.submit != 'submit[liverun]',
                                strings = dry_run ? t.strings_dry : t.strings_live,
                                result = true,
                                start = Date.now() / 1000,
                                end = start;

                            // remap values so we just do one table at a time
                            post_data['tables[]'] = [data['tables[]'][i]];
                            post_data.use_tables = 'subset';

                            // processing function
                            function process_response(response) {

                                if (response) {

                                    var errors = response.errors,
                                        report = response.report,
                                        info = response.info;

                                    // append errors
                                    $.each(errors, function (type, error_list) {

                                        if (!error_list.length) {
                                            if (type == 'db') {
                                                $('.successful-connection').show();
                                                $('[name="use_tables"]').removeAttr('disabled');
                                                // update the table dropdown if we're changing db
                                                if ($('.table-select').html() == '' || (t.prev_data.name && t.prev_data.name !== data.name))
                                                    $('.table-select').html(info.table_select);
                                                // add/remove innodb button if innodb is available or not
                                                if ($.inArray('InnoDB', info.engines) >= 0 && !$('[name="submit\[innodb\]"]').length)
                                                    $('[name="submit\[utf8\]"]').before('<input type="submit" name="submit[innodb]" value="convert to innodb" class="db-required secondary field-advanced" />');
                                            }
                                            return;
                                        }

                                        var $row = $('.row-' + type),
                                            $errors = $row.find('.errors');

                                        if (!$errors.length) {
                                            $errors = $('<div class="errors"></div>').hide().insertAfter($('legend,h1', $row));
                                            $errors.fadeIn(200);
                                        }

                                        $.each(error_list, function (i, error) {
                                            if (!t.errors[type] || $.inArray(error, t.errors[type]) < 0)
                                                $('<p>' + error + '</p>').hide().appendTo($errors).fadeIn(200);
                                        });

                                        if (type == 'db') {
                                            $('[name="use_tables"]').eq(0).click().end().attr('disabled', 'disabled');
                                            $('.table-select').html('');
                                            $('[name="submit\[innodb\]"]').remove();

                                        }


                                        $('.successful-connection').hide();

                                        var $row = $('.row-' + type),
                                            $errors = $row.find('.errors');

                                    });


                                    // scroll back to top most errors block
                                    //if ( t.errors !== errors && $( '.errors' ).length && $( '.errors' ).eq( 0 ).offset().top < $( 'body' ).scrollTop() )
                                    //  $( 'html,body' ).animate( { scrollTop: $( '.errors' ).eq(0).offset().top }, 300 );

                                    // track errors
                                    $.extend(true, t.errors, errors);

                                    // track info
                                    $.extend(true, t.info, info);
                                    // append reports
                                    if (report.length > 0) {

                                        var $row = $('.row-results'),
                                            $report = $row.find('.report'),
                                            $table_reports = [];

                                        for (c = 0; c < report.length; c++) {
                                            $table_reports[c] = $row.find('.table-reports-' + c + '');

                                            if (!$report.length)
                                                $report[c] = $('<div class="report report-"' + c + '"></div>').appendTo($row);

                                            end = Date.now() / 1000;
                                            if (t.time.length != report.length) {
                                                t.time.push(0.0);
                                            }
                                            t.tables += report[c].tables;
                                            t.rows += report[c].rows;
                                            t.changes += report[c].change;
                                            t.updates += report[c].updates;
                                            t.time[c] += t.get_time(start, end);
                                            console.log($report[c]);
                                            if (i == 0) {

                                                //                                if (!$report[c].find('.main-report').length) {
                                                $(t.report_tpl)
                                                    .find('[data-report="search_replace"]').html(strings.search_replace).end()
                                                    .find('[data-report="search"]').text(data.search[c]).end()
                                                    .find('[data-report="replace"]').text(data.replace[c]).end()
                                                    .find('[data-report="dry_run"]').html(strings.updates).end()
                                                    .prependTo($report[c]);
                                            }
                                            $('.main-report')
                                                .find('[data-report="tables"]').html(t.tables).end()
                                                .find('[data-report="rows"]').html(t.rows).end()
                                                .find('[data-report="changes"]').html(t.changes).end()
                                                .find('[data-report="updates"]').html(t.updates).end()
                                                .find('[data-report="time"]').html(t.time[c].toFixed(7)).end();

                                            if (!$table_reports[c].length)
                                                $table_reports[c] = $('\
                                        <table class="table-reports-' + c + '" >\
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
                                        </table>').appendTo($report[c]);

                                            $.each(report[c].table_reports, function (table, table_report) {

                                                var $view_changes = '',
                                                    changes_length = table_report.changes.length;

                                                if (changes_length) {
                                                    $view_changes = $('<a href="#" title="View the first ' + changes_length + ' modifications">view changes</a>')
                                                        .data('report', table_report)
                                                        .data('table', table)
                                                        .click(t.changes_overlay);
                                                }
                                                $('<tr class="' + table + '">' + t.table_report_tpl + '</tr>')
                                                    .hide()
                                                    .find('[data-report="table"]').html(table).end()
                                                    .find('[data-report="rows"]').html(table_report.rows).end()
                                                    .find('[data-report="changes"]').html(table_report.change + ' ').append($view_changes).end()
                                                    .find('[data-report="updates"]').html(table_report.updates).end()
                                                    .find('[data-report="time"]').html(t.get_time(start, end).toFixed(7)).end()
                                                    .appendTo($table_reports[c].find('tbody'))
                                                    .fadeIn(150);

                                            });

                                            $.extend(true, t.report, report[c]);

                                        }
                                        // fetch next table
                                        console.log(i, report[0].table_reports);
                                        t.recursive_fetch_json(data, ++i);


                                    } else if (report.collation) {

                                        var $row = $('.row-results'),
                                            $report = $row.find('.report'),
                                            $table_reports = $row.find('.table-reports');

                                        if (!$report.length)
                                            $report = $('<div class="report"></div>').appendTo($row);

                                        if (!$table_reports.length)
                                            $table_reports = $('\
                                        <table class="table-reports">\
                                            <thead>\
                                                <tr>\
                                                    <th>Table</th>\
                                                    <th>Charset</th>\
                                                    <th>Collation</th>\
                                                </tr>\
                                            </thead>\
                                            <tbody></tbody>\
                                        </table>').appendTo($report);

                                        $.each(report.converted, function (table, converted) {

                                            $('\
                                                <tr class="' + table + '">\
                                                    <td>' + table + '</td>\
                                                    <td>' + report.collation.replace(/^([^_]+).*$/, '$1') + '</td>\
                                                    <td>' + report.collation + '</td>\
                                                </tr>')
                                                .hide()
                                                .appendTo($table_reports.find('tbody'))
                                                .fadeIn(150);

                                            $('.table-select option[value="' + table + '"]').html(function () {
                                                return $(this).html().replace(new RegExp('collation: .*?$'), 'collation: ' + report.collation);
                                            });

                                        });

                                        // fetch next table
                                        t.recursive_fetch_json(data, ++i);

                                    } else {

                                        console.log('no report');
                                        t.complete();

                                    }

                                } else {

                                    console.log('no response');
                                    t.complete();

                                }

                                // remember previous request
                                t.prev_data = $.extend({}, data);


                                return true;
                            }

                            return $.ajax({
                                url: window.location.href,
                                data: post_data,
                                type: 'POST',
                                dataType: 'json',
                                // sometimes WordPress forces a 404, we can still get responseJSON in some cases though
                                error: function (xhr) {
                                    if (xhr.responseJSON)
                                        process_response(xhr.responseJSON);
                                    else {
                                        // handle error
                                        alert(
                                            'The script encountered an error while running an AJAX request.\
                                    \
                                    If you are using your hosts file to map a domain try browsing via the IP address directly.\
                                    \
                                    If you are still running into problems we recommend trying the CLI script bundled with this package.\
                                    See the README for details.'
                                        );

                                        try {
                                            process_response({errors: {db: ['The script encountered an error while running an AJAX request.']}});
                                        } catch (e) {
                                            // We're not interested in the nuts and bolts.
                                            // Squelch exceptions and just use process_response to print a generic error.
                                        }
                                        // Reactivate the interface.
                                        t.complete();
                                    }
                                },
                                success: function (data) {
                                    process_response(data);
                                }
                            });

                        },

                        get_time: function (start, end) {
                            start = start || 0.0;
                            end = end || 0.0;
                            start = parseFloat(start);
                            end = parseFloat(end);
                            var diff = end - start;
                            return parseFloat(diff < 0.0 ? 0.0 : diff);
                        },

                        changes_overlay: function (e) {
                            e.preventDefault();

                            var $overlay = $('.changes-overlay'),
                                table = $(this).data('table'),
                                report = $(this).data('report')
                            changes = report.changes,
                                search = $('[name="search"]').val(),
                                replace = $('[name="replace"]').val(),
                                regex = $('[name="regex"]').is(':checked'),
                                regex_i = $('[name="regex_i"]').is(':checked'),
                                regex_m = $('[name="regex_m"]').is(':checked'),
                                regex_search_iter = new RegExp(search, 'g' + (regex_i ? 'i' : '') + (regex_m ? 'm' : '')),
                                regex_search = new RegExp(search, 'g' + (regex_i ? 'i' : '') + (regex_m ? 'm' : ''));

                            if (!$overlay.length) {
                                $overlay = $('<div class="changes-overlay"><div class="overlay-header"><a class="close" href="#close">&times; Close</a><h1></h1></div><div class="changes"></div></div>')
                                    .hide()
                                    .find('.close')
                                    .click(function (e) {
                                        e.preventDefault();
                                        $overlay.fadeOut(300);
                                        $('body').css({overflow: 'auto'});
                                    })
                                    .end()
                                    .appendTo($('body'));
                                $(document).on('keyup', function (e) {
                                    // escape key
                                    if ($overlay.is(':visible') && e.which == 27) {
                                        $overlay.find('.close').click();
                                    }
                                });
                            }

                            $('body').css({overflow: 'hidden'});

                            $overlay
                                .find('h1').html(table + ' <small>Showing first 20 changes</small>').end()
                                .find('.changes').html('').end()
                                .fadeIn(300)
                                .find('.changes').html(function () {
                                var $changes = $(this);
                                $.each(changes, function (i, item) {
                                    if (i >= 20)
                                        return false;
                                    var match_search,
                                        match_replace,
                                        text,
                                        $change = $('\
                                            <div class="diff-wrap">\
                                                <h3>row ' + item.row + ', column `' + item.column + '`</h3>\
                                                <div class="diff">\
                                                    <pre class="from"></pre>\
                                                    <pre class="to"></pre>\
                                                </div>\
                                            </div>')
                                            .find('.from').text(item.from).end()
                                            .find('.to').text(item.to).end()
                                            .appendTo($changes);

                                    var from_div = $change.find('.from');
                                    var to_div = $change.find('.to');

                                    var original_text = from_div.html();

                                    // Only display highlights if this isn't a serialised object.
                                    // We CANNOT show highlights properly without writing a FULL COMPLETE
                                    // php compatible serialize unserialize pair.
                                    // Any attempt to work around the above restriction will not work,
                                    // if you try it, you will find you are -writing such functions yourself-!
                                    if (!containsSerialisedString(original_text)) {
                                        if (regex) {
                                            var result_of_regex;

                                            var copied_char_from_source = 0;

                                            var output_search_panel = '';
                                            var output_replace_panel = '';

                                            while (result_of_regex = regex_search_iter.exec(original_text)) {
                                                var search_match_start = result_of_regex.index;
                                                var search_match_end = regex_search_iter.lastIndex;

                                                output_search_panel = output_search_panel + original_text.slice(copied_char_from_source, search_match_start);
                                                output_replace_panel = output_replace_panel + original_text.slice(copied_char_from_source, search_match_start);

                                                output_search_panel = output_search_panel + '<span class="highlight">';
                                                output_search_panel = output_search_panel + original_text.slice(search_match_start, search_match_end);
                                                output_search_panel = output_search_panel + '</span>';
                                                output_replace_panel = output_replace_panel + '<span class="highlight">';
                                                output_replace_panel = output_replace_panel + original_text.slice(search_match_start, search_match_end).replace(regex_search, replace);
                                                output_replace_panel = output_replace_panel + '</span>';

                                                copied_char_from_source = search_match_end;
                                            }

                                            output_search_panel = output_search_panel + original_text.slice(copied_char_from_source);
                                            output_replace_panel = output_replace_panel + original_text.slice(copied_char_from_source);

                                            from_div.html(output_search_panel);
                                            to_div.html(output_replace_panel);
                                        } else {
                                            // Do a multiple straight up search replace on search with the highlight string we want to put in.
                                            var original_chunks = original_text.split(search);

                                            from_div.html(original_chunks.join('<span class="highlight">' + search + '</span>'));

                                            if (replace) {
                                                // only display highlights if this isn't a serialised object
                                                if (!containsSerialisedString(to_div.html())) {
                                                    to_div.html(original_chunks.join('<span class="highlight">' + replace + '</span>'));
                                                }
                                            }
                                        }
                                    }
                                    return true;
                                });
                                $(this).scrollTop(0);
                            }).end();

                        },

                        onunload: function () {
                            return window.confirm(t.running ? t.confirm_strings.unload_running : t.confirm_strings.unload_default);
                        },

                        fetch_products: function () {

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
                            $.ajax({
                                type: 'GET',
                                url: 'http://products.network.interconnectit.com/api/core/get_posts/',
                                data: {order: 'ASC', orderby: 'menu_order title'},
                                dataType: 'jsonp',
                                jsonpCallback: 'show_products',
                                contentType: 'application/json',
                                success: function (products) {
                                    $products = $('.row-products .content').html('');
                                    $.each(products.posts, function (i, product) {

                                        // run template replacement
                                        $products.append(tpl.replace(/{{([a-z\.\[\]0-9_]+)}}/g, function (match, p1, offset, search) {
                                            return typeof eval('product.' + p1) != 'undefined' ? eval('product.' + p1) : '';
                                        }));

                                    });
                                },
                                error: function (e) {

                                }
                            });

                        },

                        fetch_blogs: function () {

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
                            $.ajax({
                                type: 'GET',
                                url: 'http://interconnectit.com/api/core/get_posts/',
                                data: {count: 3, category__not_in: [216]},
                                dataType: 'jsonp',
                                jsonpCallback: 'show_blogs',
                                contentType: 'application/json',
                                success: function (blogs) {
                                    $blogs = $('.row-blog .content').html('');
                                    $.each(blogs.posts, function (i, blog) {

                                        // run template replacement
                                        $blogs.append(tpl.replace(/{{([a-z\.\[\]0-9_]+)}}/g, function (match, p1, offset, search) {
                                            var value = typeof eval('blog.' + p1) != 'undefined' ? eval('blog.' + p1) : '';
                                            if (p1 == 'date')
                                                value = new Date(value).toDateString();
                                            if (p1 == 'categories')
                                                value = $.map(value, function (category, i) {
                                                    return category.title;
                                                }).join(', ');
                                            return value;
                                        }));

                                    });
                                },
                                error: function (e) {

                                }
                            });

                        },

                        mailchimp: function (e) {
                            e.preventDefault();

                            var $this = $(this),
                                $form = $this.is('form') ? $this : $this.parents('form'),
                                $button = $form.find('input[type="submit"]').addClass('active'),
                                action = $form.attr('action').replace(/subscribe\/post$/, 'subscribe/post-json');

                            // remove errors
                            $('.row-subscribe .errors').remove();

                            // get response from mailchimp
                            $.ajax({
                                type: 'GET',
                                url: action,
                                data: $form.serialize() + '&c=?',
                                dataType: 'json',
                                success: function (response) {

                                    if (response && response.result == 'success') {
                                        $form.find('>*').fadeOut(150, function () {
                                            $form.html('');
                                            $('<div class="content"><p class="thanks">Success! We didn&rsquo;t think it was possible but now we like you even more!</p></div>')
                                                .hide()
                                                .insertAfter($form)
                                                .fadeIn(300);
                                            $form.remove();
                                        });
                                    }

                                    if (response && response.result != 'success') {

                                        $('<div class="errors"><p>Computer says no&hellip; Can you check you&rsquo;ve filled in the email address field correctly?</p></div>')
                                            .hide()
                                            .insertAfter('.row-subscribe h1')
                                            .fadeIn(200);

                                    }
                                },
                                complete: function () {
                                    $button.removeClass('active');
                                }
                            });

                        }

                    });

                    // constructor
                    t.init();

                    return t;
                }

                // load on ready
                $(document).ready(srdb);

            })(jQuery);
        </script>
        </body>
        </html>
        <?php
    }

}

// initialise
new icit_srdb_ui();

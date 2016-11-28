<?php

class icit_srdb_ui extends icit_srdb
{

    /**
     * @var string Root path of the CMS
     */
    public $path;

    public $is_typo = false;
    public $is_wordpress = false;
    public $is_drupal = false;
    public $is_joomla = false;
    public $is_docker = false;

    public function __construct()
    {

// php 5.4 date timezone requirement, shouldn't affect anything
        date_default_timezone_set('Europe/London');

// prevent fatals from hiding the UI
        register_shutdown_function(array($this, 'fatal_handler'));

// flag to bootstrap WP, Drupal or Joomla
        $bootstrap = true; // isset( $_GET[ 'bootstrap' ] );

// discover environment
        if ($bootstrap && $this->is_wordpress()) {

// prevent warnings if the charset and collate aren't defined
            if (!defined('DB_CHARSET')) {
                define('DB_CHARSET', 'utf8');
            }
            if (!defined('DB_COLLATE')) {
                define('DB_COLLATE', '');
            }

// populate db details
            $name = DB_NAME;
            $user = DB_USER;
            $pass = DB_PASSWORD;

// Port and host need to be split apart.
            if (strstr(DB_HOST, ':') !== false) {
                $parts = explode(':', DB_HOST);
                $host = $parts[0];
                $port_input = $parts[1];

                $port = abs((int)$port_input);
            } else {
                $host = DB_HOST;
                $port = 3306;
            }

            $charset = DB_CHARSET;
            $collate = DB_COLLATE;

            $this->response($name, $user, $pass, $host, $port, $charset, $collate);

        } elseif ($bootstrap && $this->is_drupal()) {
            $database = Database::getConnection();
            $database_opts = $database->getConnectionOptions();

// populate db details
            $name = $database_opts['database'];
            $user = $database_opts['username'];
            $pass = $database_opts['password'];
            $host = $database_opts['host'];
            $port = $database_opts['port'];
            $charset = 'utf8';
            $collate = '';

            $port_as_string = (string)$port ? (string)$port : "0";
            if ((string)abs((int)$port) !== $port_as_string) {
                $port = 3306;
            } else {
                $port = (string)abs((int)$port);
            }

            $this->response($name, $user, $pass, $host, $port, $charset, $collate);

        } elseif ($bootstrap && $this->is_joomla()) {
// Create a JConfig object
            $jconfig = new JConfig();

// populate db details
            $name = $jconfig->db;
            $user = $jconfig->user;
            $pass = $jconfig->password;

// Port and host need to be split apart.
            if (strstr($jconfig->host, ':') !== false) {
                $parts = explode(':', $jconfig->host);
                $host = $parts[0];
                $port_input = $parts[1];

                $port = abs((int)$port_input);
            } else {
                $host = $jconfig->host;
                $port = 3306;
            }
            $charset = 'utf8';
            $collate = '';

            $this->response($name, $user, $pass, $host, $port, $charset, $collate);
        } elseif ($this->is_typo()) {

// populate db details
            $name = DB_NAME;
            $user = DB_USER;
            $pass = DB_PASSWORD;
            $port = DB_PORT;
            $host = DB_HOST;
            $charset = 'utf8';
            $collate = '';

            $portAsString = (string)$port ? (string)$port : "0";
            if ((string)abs((int)$port) !== $portAsString) {
                $port = 3306;
            } else {
                $port = (string)abs((int)$port);
            }

            $this->response($name, $user, $pass, $host, $port, $charset, $collate);

        } elseif ($bootstrap && $this->is_docker()) {
            $name = '';
            $user = 'root';
            $pass = DB_PASSWORD;
            $host = DB_HOST;
            $port = DB_PORT;
            $charset = 'utf8';
            $collate = '';

            $this->response($name, $user, $pass, $host, $port, $charset, $collate);

        } else {

            $this->response();

        }

    }

    public function response($name = '', $user = '', $pass = '', $host = '127.0.0.1', $port = 3306, $charset = 'utf8', $collate = '')
    {

        if (version_compare(PHP_VERSION, '5.2') < 0) {
            $this->add_error("The script requires php version 5.2 or above, whereas your php version is: " . PHP_VERSION . ". Please update php and try again", "compatibility");
        }


        if (!extension_loaded("mbstring")) {
            $this->add_error("This script requires mbstring. Please install mbstring and try again", "compatibility");

        }

// always override with post data
        if (isset($_POST['name'])) {
            $name = $_POST['name']; // your database
            $user = $_POST['user']; // your db userid
            $pass = $_POST['pass']; // your db password
            $host = $_POST['host']; // normally localhost, but not necessarily.

            $port_input = $_POST['port'];

// Make sure that the string version of absint(port) is identical to the string input.
// This prevents expressions, decimals, spaces, etc.
            $port_as_string = (string)$port_input ? (string)$port_input : "0";
            if ((string)abs((int)$port_input) !== $port_as_string) {
// Mangled port number: non numeric.
                $this->add_error('Port number must be a positive integer. If you are unsure, try the default port 3306.', 'db');

// Force a bad run by supplying nonsense.
                $port = "nonsense";
            } else {
                $port = abs((int)$port_input);
            }


            $charset = 'utf8'; // isset( $_POST[ 'char' ] ) ? stripcslashes( $_POST[ 'char' ] ) : '';	// your db charset
            $collate = '';
        }

// Search replace details
        $search = isset($_POST['search']) ? $_POST['search'] : '';
        $replace = isset($_POST['replace']) ? $_POST['replace'] : '';

// regex options
        $regex = isset($_POST['regex']);
        $regex_i = isset($_POST['regex_i']);
        $regex_m = isset($_POST['regex_m']);
        $regex_s = isset($_POST['regex_s']);
        $regex_x = isset($_POST['regex_x']);

// Tables to scanned
        $tables = isset($_POST['tables']) && is_array($_POST['tables']) ? $_POST['tables'] : array();
        if (isset($_POST['use_tables']) && $_POST['use_tables'] == 'all')
            $tables = array();

// exclude / include columns
        $exclude_cols = isset($_POST['exclude_cols']) ? $_POST['exclude_cols'] : array();
        $include_cols = isset($_POST['include_cols']) ? $_POST['include_cols'] : array();

        foreach (array('exclude_cols', 'include_cols') as $maybe_string_arg) {
            if (is_string($$maybe_string_arg))
                $$maybe_string_arg = array_filter(array_map('trim', explode(',', $$maybe_string_arg)));
        }

// update class vars
        $vars = array(
            'name', 'user', 'pass', 'host', 'port',
            'charset', 'collate', 'tables',
            'search', 'replace',
            'exclude_cols', 'include_cols',
            'regex', 'regex_i', 'regex_m', 'regex_s', 'regex_x'
        );

        foreach ($vars as $var) {
            if (isset($$var))
                $this->set($var, $$var);
        }

// are doing something?
        $show = '';
        if (isset($_POST['submit'])) {
            if (is_array($_POST['submit']))
                $show = key($_POST['submit']);
            if (is_string($_POST['submit']))
                $show = preg_replace('/submit\[([a-z0-9]+)\]/', '$1', $_POST['submit']);
        }

// is it an AJAX call
        $ajax = isset($_POST['ajax']);

// body callback
        $html = 'ui';

        switch ($show) {

// remove search replace
            case 'delete':

// determine if it's the folder of compiled version
                if (basename(__FILE__) == 'index.php')
                    $path = str_replace(basename(__FILE__), '', __FILE__);
                else
                    $path = __FILE__;

                $delete_script_success = $this->delete_script($path);

                if (self::DELETE_SCRIPT_FAIL_UNSAFE === $delete_script_success) {
                    $this->add_error('Delete aborted! You seem to have placed Search/Replace into your WordPress or Drupal root. Please remove Search/Replace manually.', 'delete');
                } else {
                    if ((self::DELETE_SCRIPT_SUCCESS === $delete_script_success) && !(is_file(__FILE__) && file_exists(__FILE__))) {
                        $this->add_error('Search/Replace has been successfully removed from your server', 'delete');
                    } else {
                        $this->add_error('Could not fully delete Search/Replace. You will have to delete it manually', 'delete');
                    }
                }

                $html = 'deleted';

                break;

            case 'liverun':

// bsy-web, 20130621: Check live run was explicitly clicked and only set false then
                $this->set('dry_run', false);

            case 'dryrun':

// build regex string
// non UI implements can just pass in complete regex string
                if ($this->regex) {
                    $mods = '';
                    if ($this->regex_i) $mods .= 'i';
                    if ($this->regex_s) $mods .= 's';
                    if ($this->regex_m) $mods .= 'm';
                    if ($this->regex_x) $mods .= 'x';
                    $this->search = '/' . $this->search . '/' . $mods;
                }

// call search replace class
                $parent = parent::__construct(array(
                    'name' => $this->get('name'),
                    'user' => $this->get('user'),
                    'pass' => $this->get('pass'),
                    'host' => $this->get('host'),
                    'port' => $this->get('port'),
                    'search' => $this->get('search'),
                    'replace' => $this->get('replace'),
                    'tables' => $this->get('tables'),
                    'dry_run' => $this->get('dry_run'),
                    'regex' => $this->get('regex'),
                    'exclude_cols' => $this->get('exclude_cols'),
                    'include_cols' => $this->get('include_cols')
                ));

                break;

            case 'innodb':

// call search replace class to alter engine
                $parent = parent::__construct(array(
                    'name' => $this->get('name'),
                    'user' => $this->get('user'),
                    'pass' => $this->get('pass'),
                    'host' => $this->get('host'),
                    'port' => $this->get('port'),
                    'tables' => $this->get('tables'),
                    'alter_engine' => 'InnoDB',
                ));

                break;

            case 'utf8':

// call search replace class to alter engine
                $parent = parent::__construct(array(
                    'name' => $this->get('name'),
                    'user' => $this->get('user'),
                    'pass' => $this->get('pass'),
                    'host' => $this->get('host'),
                    'port' => $this->get('port'),
                    'tables' => $this->get('tables'),
                    'alter_collation' => 'utf8_unicode_ci',
                ));

                break;

            case 'utf8mb4':

// call search replace class to alter engine
                $parent = parent::__construct(array(
                    'name' => $this->get('name'),
                    'user' => $this->get('user'),
                    'pass' => $this->get('pass'),
                    'host' => $this->get('host'),
                    'port' => $this->get('port'),
                    'tables' => $this->get('tables'),
                    'alter_collation' => 'utf8mb4_unicode_ci',
                ));

                break;

            case 'update':
            default:

// get tables or error messages
                $this->db_setup();

                if ($this->db_valid()) {
// get engines
                    $this->set('engines', $this->get_engines());

// get tables
                    $this->set('all_tables', $this->get_tables());

                }

                break;
        }

        $info = array(
            'table_select' => $this->table_select(false),
            'engines' => $this->get('engines')
        );

// set header again before output in case WP does it's thing
        header('HTTP/1.1 200 OK');
        header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
        header('Pragma: no-cache'); // HTTP 1.0.
        header('Expires: 0'); // Proxies.
        if (!$ajax) {
            $this->html($html);
        } else {

// return json version of results
            header('Content-Type: application/json');

            echo json_encode(array(
                'errors' => $this->get('errors'),
                'report' => $this->get('report'),
                'info' => $info
            ));

            exit;

        }

    }


    public function exceptions($exception)
    {
        $this->add_error('<p class="exception">' . $exception->getMessage() . '</p>');
    }

    public function errors($no, $message, $file, $line)
    {
        $this->add_error('<p class="error">' . "<strong>{$no}:</strong> {$message} in {$file} on line {$line}" . '</p>', 'results');
    }

    public function fatal_handler()
    {
        $error = error_get_last();

        if ($error !== NULL) {
            $errno = $error["type"];
            $errfile = $error["file"];
            $errline = $error["line"];
            $errstr = $error["message"];

            if ($errno == 1) {
                header('HTTP/1.1 200 OK');
                $this->add_error('<p class="error">Could not bootstrap environment.<br /> ' . "Fatal error in {$errfile}, line {$errline}. {$errstr}" . '</p>', 'environment');
                $this->response();
            }
        }
    }

    /**
     * Return an array of all files and directories recursively below $path.
     *
     * If $path is a file, returns an array containing just that filename.
     *
     * @param string $path directory/file path.
     *
     * @return array
     */
    public function determine_all_files_below_path($path)
    {
// A file contains only 'itself'.
        if (is_file($path)) {
            return array($path);
        }

        $directory_contents = glob($path . '/*');

        $full_recursive_contents = array();

// Every directory contains all of its files, plus 'itself'.
        foreach ($directory_contents as $item_filename) {
            $full_recursive_contents = array_merge($full_recursive_contents, $this->determine_all_files_below_path($item_filename));
        }
        $full_recursive_contents = array_merge($full_recursive_contents, array($path));

        return $full_recursive_contents;
    }

    /**
     * @param $path Filename to inspect
     *
     * @return boolean true if it is most likely nothing to do with WordPress or Drupal.
     */
    public function safe_to_delete_filename($path)
    {
// You'll have to edit this list if
// more files are included in SRDB.

// Using an untargeted deletion operation is
// entirely unacceptable.
        $srdb_filename_whitelist = array(
            'composer.json',
            'index.php',
            'package.json',
            'README.md',
            'srdb.class.php',
            'srdb.cli.php',

            'srdb-tests',
            'charset-test.php',
            'DataSet.xml',
            'DataSetGenerator.php',
            'db.sql',
            'SrdbTest.php'
        );

        foreach ($srdb_filename_whitelist as $whitelist_item) {
            if (false !== stripos($path, $whitelist_item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks an array of fully qualified filenames to see if they are all
     * SRDB filenames.
     *
     * @param array $array_of_paths
     *
     * @return boolean true if all paths are most likely SRDB files.
     */
    public function safe_to_delete_all_filenames($array_of_paths)
    {
        foreach ($array_of_paths as $path) {
            if (!$this->safe_to_delete_filename($path)) {
                return false;
            }
        }

        return true;
    }

    const DELETE_SCRIPT_SUCCESS = 0;
    const DELETE_SCRIPT_FAIL_CANT_DELETE = -1;
    const DELETE_SCRIPT_FAIL_UNSAFE = -2;

    /**
     * http://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
     *
     * @param string $path directory/file path
     *
     * @return integer DELETE_SCRIPT_SUCCESS for success, DELETE_SCRIPT_FAIL_CANT_DELETE for physical failure, DELETE_SCRIPT_FAIL_UNSAFE for 'Shouldn't delete wordpress' failure.
     */
    public function delete_script($path)
    {
        $all_targets = $this->determine_all_files_below_path($path);

        $all_targets_minus_containing_directory = $all_targets;

// Proceed if all files identified (except the current directory)
// match a list of whitelisted deletable filenames.
        array_pop($all_targets_minus_containing_directory);
        $can_proceed = $this->safe_to_delete_all_filenames($all_targets_minus_containing_directory);

        if (!$can_proceed) return self::DELETE_SCRIPT_FAIL_UNSAFE;

        foreach ($all_targets as $target_filename) {
            if (is_file($target_filename)) {
                if (false === @unlink($target_filename)) return self::DELETE_SCRIPT_FAIL_CANT_DELETE;
            } else {
                if (false === @rmdir($target_filename)) return self::DELETE_SCRIPT_FAIL_CANT_DELETE;
            }
        }

        return self::DELETE_SCRIPT_SUCCESS;
    }

    /**
     * Attempts to detect a Typo3 installation
     *
     * @return bool Whether it is a Typo3 installation and we have database credentials
     */
    public function is_typo()
    {
        $pathMod = '';
        $depth = 0;
        $maxDepth = 4;
        $configFile = 'typo3conf/LocalConfiguration.php';
        $currentDir = getcwd();

        while (!file_exists($currentDir . "{$pathMod}/{$configFile}")) {
            $pathMod .= '/..';
            if ($depth++ >= $maxDepth) {
                break;
            }
        }

        if (file_exists($currentDir . "{$pathMod}/{$configFile}")) {
            $configPath = $currentDir . "{$pathMod}/{$configFile}";

            $config = require $configPath;

            define('DB_NAME', $config['DB']['database']);
            define('DB_USER', $config['DB']['username']);
            define('DB_PASSWORD', $config['DB']['password']);
            define('DB_HOST', $config['DB']['host']);
            define('DB_PORT', $config['DB']['port']);

            return true;
        }

        return false;
    }


    /**
     * Attempts to detect a WordPress installation and bootstraps the environment with it
     *
     * @return bool    Whether it is a WP install and we have database credentials
     */
    public function is_wordpress()
    {

        $path_mod = '';
        $depth = 0;
        $max_depth = 4;
        $bootstrap_file = 'wp-blog-header.php';

        while (!file_exists(dirname(__FILE__) . "{$path_mod}/{$bootstrap_file}")) {
            $path_mod .= '/..';
            if ($depth++ >= $max_depth)
                break;
        }

        if (file_exists(dirname(__FILE__) . "{$path_mod}/{$bootstrap_file}")) {

// store WP path
            $this->path = dirname(__FILE__) . $path_mod;

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

// prevent multisite redirect
                define('WP_INSTALLING', true);

// prevent super/total cache
                define('DONOTCACHEDB', true);
                define('DONOTCACHEPAGE', true);
                define('DONOTCACHEOBJECT', true);
                define('DONOTCDN', true);
                define('DONOTMINIFY', true);

// cancel batcache
                if (function_exists('batcache_cancel'))
                    batcache_cancel();

// bootstrap WordPress
                require(dirname(__FILE__) . "{$path_mod}/{$bootstrap_file}");

                $this->set('path', ABSPATH);

                $this->set('is_wordpress', true);

                return true;

            } catch (Exception $error) {

// try and get database values using regex approach
                $db_details = $this->define_find($this->path . '/wp-config.php');

                if ($db_details) {

                    define('DB_NAME', $db_details['name']);
                    define('DB_USER', $db_details['user']);
                    define('DB_PASSWORD', $db_details['pass']);
                    define('DB_HOST', $db_details['host']);
                    define('DB_CHARSET', $db_details['char']);
                    define('DB_COLLATE', $db_details['coll']);

// additional error message
                    $this->add_error('WordPress detected but could not bootstrap environment. There might be a PHP error, possibly caused by changes to the database', 'db');

                }

                if ($db_details)
                    return true;

            }

        }

        return false;
    }


    public function is_drupal()
    {

        $path_mod = '';
        $depth = 0;
        $max_depth = 4;
        $bootstrap_file = 'includes/bootstrap.inc';

        while (!file_exists(dirname(__FILE__) . "{$path_mod}/{$bootstrap_file}")) {
            $path_mod .= '/..';
            if ($depth++ >= $max_depth)
                break;
        }

        if (file_exists(dirname(__FILE__) . "{$path_mod}/{$bootstrap_file}")) {

            try {
// require the bootstrap include
                require_once(dirname(__FILE__) . "{$path_mod}/{$bootstrap_file}");

// define drupal root
                if (!defined('DRUPAL_ROOT'))
                    define('DRUPAL_ROOT', dirname(__FILE__) . $path_mod);

// load drupal
                drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

// confirm environment
                $this->set('is_drupal', true);

                return true;

            } catch (Exception $error) {
// We can't add_error here as 'db' because if the db errors array is not empty, the interface doesn't activate!
// This is a consequence of the 'complete' method in JavaScript
                $this->add_error('Drupal detected but could not bootstrap to retrieve configuration. There might be a PHP error, possibly caused by changes to the database', 'recoverable_db');
            }

        }

        return false;
    }

    public function is_joomla()
    {

        $path_mod = '';
        $depth = 0;
        $max_depth = 4;
        $bootstrap_file = 'configuration.php';

        while (!file_exists(dirname(__FILE__) . "{$path_mod}/{$bootstrap_file}")) {
            $path_mod .= '/..';
            if ($depth++ >= $max_depth)
                break;
        }

        if (file_exists(dirname(__FILE__) . "{$path_mod}/{$bootstrap_file}")) {

            try {
// require the bootstrap include
                require_once(dirname(__FILE__) . "{$path_mod}/{$bootstrap_file}");

// Create a JConfig object
                $jconfig = new JConfig();

// confirm environment
                $this->set('is_joomla', true);

                return true;

            } catch (Exception $error) {
// We can't add_error here as 'db' because if the db errors array is not empty, the interface doesn't activate!
// This is a consequence of the 'complete' method in JavaScript
                $this->add_error('Joomla detected but could not retrieve configuration. This could be a PHP error, possibly caused by a false positive', 'recoverable_db');
            }

        }
    }

    /**
     * Attempts to detect a Docker container and bootstraps the environment with it
     *
     * @return bool    Whether it is a Docker container and we have a linked database
     */
    public function is_docker()
    {

        putenv('MYSQL_ENV_MYSQL_VERSION=5.6.22');
        putenv('MYSQL_ENV_MYSQL_ROOT_PASSWORD=root');
        putenv('MYSQL_PORT_3306_TCP_PORT=3306');
        putenv('MYSQL_PORT_3306_TCP_ADDR=172.17.0.67');

        if (file_exists('/.dockerenv') && file_exists('/.dockerinit')) {

            if (getenv('MYSQL_ENV_MYSQL_VERSION')) {

                define('DB_PASSWORD', getenv('MYSQL_ENV_MYSQL_ROOT_PASSWORD'));
                define('DB_HOST', getenv('MYSQL_PORT_3306_TCP_ADDR'));
                define('DB_PORT', getenv('MYSQL_PORT_3306_TCP_PORT'));

            } else {

                $this->add_error('Docker environment detected but could not find a linked MySQL container.', 'db');

            }

            return true;

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
    public function define_find($filename = 'wp-config.php')
    {

        if ($filename == 'wp-config.php') {
            $filename = dirname(__FILE__) . '/' . basename($filename);

// look up one directory if config file doesn't exist in current directory
            if (!file_exists($filename))
                $filename = dirname(__FILE__) . '/../' . basename($filename);
        }

        if (file_exists($filename) && is_file($filename) && is_readable($filename)) {
            $file = @fopen($filename, 'r');
            $file_content = fread($file, filesize($filename));
            @fclose($file);
        }

        preg_match_all('/define\s*?\(\s*?([\'"])(DB_NAME|DB_USER|DB_PASSWORD|DB_HOST|DB_CHARSET|DB_COLLATE)\1\s*?,\s*?([\'"])([^\3]*?)\3\s*?\)\s*?;/si', $file_content, $defines);

        if ((isset($defines[2]) && !empty($defines[2])) && (isset($defines[4]) && !empty($defines[4]))) {
            foreach ($defines[2] as $key => $define) {

                switch ($define) {
                    case 'DB_NAME':
                        $name = $defines[4][$key];
                        break;
                    case 'DB_USER':
                        $user = $defines[4][$key];
                        break;
                    case 'DB_PASSWORD':
                        $pass = $defines[4][$key];
                        break;
                    case 'DB_HOST':
                        $host = $defines[4][$key];
                        break;
                    case 'DB_CHARSET':
                        $char = $defines[4][$key];
                        break;
                    case 'DB_COLLATE':
                        $coll = $defines[4][$key];
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
     * Display the current url
     *
     */
    public function self_link()
    {
        return 'http://' . $_SERVER['HTTP_HOST'] . rtrim($_SERVER['REQUEST_URI'], '/');
    }


    /**
     * Simple html escaping
     *
     * @param string $string Thing that needs escaping
     * @param bool $echo Do we echo or return?
     *
     * @return string    Escaped string.
     */
    public function esc_html_attr($string = '', $echo = false)
    {
        $output = htmlentities($string, ENT_QUOTES, 'UTF-8');
        if ($echo)
            echo $output;
        else
            return $output;
    }

    public function checked($value, $value2, $echo = true)
    {
        $output = $value == $value2 ? ' checked="checked"' : '';
        if ($echo)
            echo $output;
        return $output;
    }

    public function selected($value, $value2, $echo = true)
    {
        $output = $value == $value2 ? ' selected="selected"' : '';
        if ($echo)
            echo $output;
        return $output;
    }


    public function get_errors($type)
    {
        if (!isset($this->errors[$type]) || !count($this->errors[$type]))
            return;

        echo '<div class="errors">';
        foreach ($this->errors[$type] as $error) {
            if ($error instanceof Exception)
                echo '<p class="exception">' . $error->getMessage() . '</p>';
            elseif (is_string($error))
                echo $error;
        }
        echo '</div>';
    }


    public function get_report($table = null)
    {

        $report = $this->get('report');

        if (empty($report))
            return;

        $dry_run = $this->get('dry_run');
        $search = $this->get('search');
        $replace = $this->get('replace');

// Calc the time taken.
        $time = array_sum(explode(' ', $report['end'])) - array_sum(explode(' ', $report['start']));
        if ($time < 0){
            $time = $time * -1;
        }
        $srch_rplc_input_phrase = $dry_run ?
            'searching for <strong>"' . $this->esc_html_attr($search) . '"</strong> (to be replaced by <strong>"' . $this->esc_html_attr($replace) . '"</strong>)' :
            'replacing <strong>"' . $this->esc_html_attr($search) . '"</strong> with <strong>"' . $this->esc_html_attr($replace) . '"</strong>';

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
        foreach ($report['table_reports'] as $table => $t_report) {

            $t_time = array_sum(explode(' ', $t_report['end'])) - array_sum(explode(' ', $t_report['start']));

            echo '
        <tr>';
            printf('
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


    public function table_select($echo = true)
    {

        $table_select = '';

        if (!empty($this->all_tables)) {
            $table_select .= '<select name="tables[]" multiple="multiple">';
            foreach ($this->all_tables as $table) {
                $size = $table['Data_length'] / 1000;
                $size_unit = 'kb';
                if ($size > 1000) {
                    $size = $size / 1000;
                    $size_unit = 'Mb';
                }
                if ($size > 1000) {
                    $size = $size / 1000;
                    $size_unit = 'Gb';
                }
                $size = number_format($size, 2) . $size_unit;
                $rows = $table['Rows'] > 1 ? 'rows' : 'row';

                $table_select .= sprintf('<option value="%s" %s>%s</option>',
                    $this->esc_html_attr($table[0], false),
                    $this->selected(true, in_array($table[0], $this->tables), false),
                    "{$table[0]}: {$table['Engine']}, rows: {$table['Rows']}, size: {$size}, collation: {$table['Collation']}, character_set: {$table['Character_set']}"
                );
            }
            $table_select .= '</select>';
        }

        if ($echo)
            echo $table_select;
        return $table_select;
    }

    public function isSecure()
    {
        return
            !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    }

    public function ui()
    {
        if (!$this->isSecure()) {
            ?>
            <div class="special-errors">
                <h4>Warning</h4>
                <?php echo printf('<p>The network connection you are using is transmitting your password unencrypted. <br/> Consider using an https:// connection, or change your database password after using the script </p>'); ?>
            </div>
            <?php
        }

// Warn if we're running in safe mode as we'll probably time out.
        if (ini_get('safe_mode')) {
            ?>
            <div class="special-errors">
                <h4>Warning</h4>
                <?php echo printf('<p>Safe mode is on so you may run into problems if it takes longer than %s seconds to process your request.</p>', ini_get('max_execution_time')); ?>
            </div>
            <?php
        }
        ?>

        <?php

        include('templates/ui.php');

    }

    public function deleted()
    {
        include('templates/delete.php');
    }

    public function html($body)
    {
        $classes = array('no-js');
        $classes[] = $this->regex ? 'regex-on' : 'regex-off';
        include('templates/html.php');
    }

}

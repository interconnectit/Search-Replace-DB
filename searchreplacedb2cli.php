#!/usr/bin/php -q

<?php

require_once('searchreplacedb2.php'); // include the proper srdb script

echo "########################### Ignore Above ###############################\n\n";

// source: https://github.com/interconnectit/Search-Replace-DB/blob/master/searchreplacedb2.php

/* Flags for options, all values required */
$shortopts  = "";
$shortopts .= "h:";  // host name // $host
$shortopts .= "d:"; // database name // $data
$shortopts .= "u:"; // user name // $user
$shortopts .= "p:"; // password // $pass
$shortopts .= "c:"; // character set // $char
$shortopts .= "s:"; // search // $srch
$shortopts .= "r:"; // replace // $rplc
$shortopts .= ""; // These options do not accept values

// All long options require values 
$longopts  = array(
    "host:",    // $host
    "database:", // $data
    "user:", // $user
    "pass:", // $pass
    "charset:", // $char
    "search:", // $srch
    "replace:", // $rplc
    "help", // $help_text
    "dry-run", // engage in a dry run, print options, show results
);

/* Store arg values */
$arg_count = $_SERVER["argc"];
$args_array = $_SERVER["argv"];
$options = getopt($shortopts, $longopts); // Store array of options and values
// var_dump($options);

/* Map options to correct vars from srdb script */
if (isset($options["h"])){
  $host = $options["h"];}
elseif(isset($options["host"])){
  $host = $options["host"];}
else{
  echo "Abort! Host name required, use --host or -h\n";
  exit;}

if (isset($options["d"])){
  $data = $options["d"];}
elseif(isset($options["data"])){
  $data = $options["data"];}

if (isset($options["u"])){
  $user = $options["u"];}
elseif(isset($options["user"])){
  $user = $options["user"];}

if (isset($options["p"])){
  $pass = $options["p"];}
elseif(isset($options["pass"])){
  $pass = $options["pass"];}

if (isset($options["c"])){
  $char = $options["c"];}
elseif(isset($options["charset"])){
  $char = $options["charset"];}

if (isset($options["s"])){
  $srch = $options["s"];}
elseif(isset($options["search"])){
  $srch = $options["search"];}

if (isset($options["r"])){
  $rplc = $options["r"];}
elseif(isset($options["replace"])){
  $rplc = $options["replace"];}

/* Show values if this is a dry-run */
if (isset($options["dry-run"])){
echo "Are you sure these are correct?\n";
echo "host: ".$host."\n";
echo "database: ".$data."\n";
echo "user: ".$user."\n";
echo "pass: ".$pass."\n";
echo "charset: ".$char."\n";
echo "search: ".$srch."\n";
echo "replace: ".$rplc."\n";
}

/* Reproduce what's done in Case 3 to test the server before proceeding */
        $connection = @mysql_connect( $host, $user, $pass );
        if ( ! $connection ) {
                $errors[] = mysql_error( );
                echo "Error: ".$errors[];
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
                echo "Error: ".$errors[];
        } else {
                while ( $table = mysql_fetch_array( $all_tables_mysql ) ) {
                        $all_tables[] = $table[ 0 ];
                }
        }

/* Execute Case 5 with the actual search + replace */


/**
 * Step 1 
 * 
echo "Prepare to engage, type 'go':\n";
$str1 = fread(STDIN, 80); // Read up to 80 characters or a newline
if (trim($str1) != "go"){
  echo "Aborting!\n";
  exit;
  }

else{
  echo "\n",'Thanks for typing ' , $str1, "\n";
  echo "# of Args: ".$arg_count."\n";
  echo "Echo Args:";
  foreach ($args_array as $the_arg){
    echo $the_arg."\n";
    }
  echo "And list of opts:\n";
  var_dump($options); // Return values from options
}
*/

/* Step 2 */
// echo "Asking another question, say 'yes':\n";
// $str2 = fread(STDIN, 80); // Read again

?>


<?php

/**
 *
 * Safe Search and Replace on Database with Serialized Data v3.1.0
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
 * Version 3.1.0:
 *        * Added port number option to both web and CLI interfaces.
 *        * More reliable fallback on non-PDO systems.
 *        * Confirmation on 'Delete me'
 *        * Comprehensive check to prevent accidental deletion of web projects
 *        * Removed mysql functions and replaced with mysqli
 *
 * Version 3.0.0:
 *        * Major overhaul
 *        * Multibyte string replacements
 *        * UI completely redesigned
 *        * Removed all links from script until 'delete' has been clicked to avoid
 *          security risk from our access logs
 *        * Search replace functionality moved to it's own separate class
 *        * Replacements done table by table to avoid timeouts
 *        * Convert tables to InnoDB
 *        * Convert tables to utf8_unicode_ci
 *        * Use PDO if available
 *        * Preview/view changes
 *        * Optionally use preg_replace()
 *        * Scripts bootstraps WordPress/Drupal to avoid issues with unknown
 *          serialised objects/classes
 *        * Added marketing stuff to deleted screen (sorry but we're running a
 *          business!)
 *
 * Version 2.2.0:
 *        * Added remove script patch from David Anderson (wordshell.net)
 *        * Added ability to replace strings with nothing
 *        * Copy changes
 *        * Added code to recursive_unserialize_replace to deal with objects not
 *        just arrays. This was submitted by Tina Matter.
 *        ToDo: Test object handling. Not sure how it will cope with object in the
 *        db created with classes that don't exist in anything but the base PHP.
 *
 * Version 2.1.0:
 *              - Changed to version 2.1.0
 *        * Following change by Sergei Biryukov - merged in and tested by Dave Coveney
 *              - Added Charset Support (tested with UTF-8, not tested on other charsets)
 *        * Following changes implemented by James Whitehead with thanks to all the commenters and feedback given!
 *        - Removed PHP warnings if you go to step 3+ without DB details.
 *        - Added options to skip changing the guid column. If there are other
 *        columns that need excluding you can add them to the $exclude_cols global
 *        array. May choose to add another option to the table select page to let
 *        you add to this array from the front end.
 *        - Minor tweak to label styling.
 *        - Added comments to each of the functions.
 *        - Removed a dead param from icit_srdb_replacer
 * Version 2.0.0:
 *        - returned to using unserialize function to check if string is
 *        serialized or not
 *        - marked is_serialized_string function as deprecated
 *        - changed form order to improve usability and make use on multisites a
 *        bit less scary
 *        - changed to version 2, as really should have done when the UI was
 *        introduced
 *        - added a recursive array walker to deal with serialized strings being
 *        stored in serialized strings. Yes, really.
 *        - changes by James R Whitehead (kudos for recursive walker) and David
 *        Coveney 2011-08-26
 *  Version 1.0.2:
 *    - typos corrected, button text tweak - David Coveney / Robert O'Rourke
 *  Version 1.0.1
 *    - styling and form added by James R Whitehead.
 *
 *  Credits:  moz667 at gmail dot com for his recursive_array_replace posted at
 *            uk.php.net which saved me a little time - a perfect sample for me
 *            and seems to work in all cases.
 *
 */

require_once('src/srdb.class.php');
require_once('src/ui.php');

// initialise
new icit_srdb_ui();

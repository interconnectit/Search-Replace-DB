# Changelog

# Version 4.1.3
 * Fix regex search/replace using WebUI

# Version 4.1.2
 * Now you can suppress the checks for PHP and be able to run this tool in an old setup

# Version 4.1.1
 * Class autoloader in composer.json

## Version 4.1
 * Ability to connect using SSL, command line only feature
 * New debug option for printing message errors

## Version 4.0.1
 * Fix bug on auto-delete

## Version 4.0
 * Support for continuous integration through Travis CI
 * Ability to do multiple search-replaces
 * Ability to exclude tables
 * Remove specific loaders for WP
 * Script now checks whether the correct version of PHP is used
 * Script checks if necessary modules are installed
 * Script checks if the connection is secure and gives a warning otherwise
 * Bug fixes
 * UI Tweaks
 * Password is not mandatory in CLI

## Version 3.1.0
 * Added port number option to both web and CLI interfaces.
 * More reliable fallback on non-PDO systems.
 * Confirmation on 'Delete me'
 * Comprehensive check to prevent accidental deletion of web projects
 * Removed mysql functions and replaced with mysqli

## Version 3.0
 * Major overhaul
 * Multibyte string replacements
 * Convert tables to InnoDB
 * Convert tables to utf8_unicode_ci
 * Preview/view changes in report
 * Optionally use preg_replace()
 * Better error/exception handling & reporting
 * Reports per table
 * Exclude/include multiple columns

## Version 2.2.0
 * Added remove script patch from David Anderson (wordshell.net)
 * Added ability to replace strings with nothing
 * Copy changes
 * Added code to recursive_unserialize_replace to deal with objects not just arrays. This was submitted by Tina Matter.

 TODO: Test object handling. Not sure how it will cope with object in the
 db created with classes that don't exist in anything but the base PHP.

## Version 2.1.0
 * Following change by Sergei Biryukov - merged in and tested by Dave Coveney
 * Added Charset Support (tested with UTF-8, not tested on other charsets)
 * Following changes implemented by James Whitehead with thanks to all the commenters and feedback given!
 * Removed PHP warnings if you go to step 3+ without DB details.
 * Added options to skip changing the guid column. If there are other columns that need excluding you can add them to the $exclude_cols global array. May choose to add another option to the table select page to let you add to this array from the front end.
 * Minor tweak to label styling.
 * Added comments to each of the functions.
 * Removed a dead param from icit_srdb_replacer

## Version 2.0.0
 * Returned to using unserialize function to check if string is serialized or not
 * Marked is_serialized_string function as deprecated
 * Changed form order to improve usability and make use on multisites a bit less scary
 * Changed to version 2, as really should have done when the UI was introduced
 * Added a recursive array walker to deal with serialized strings being stored in serialized strings. Yes, really.
 * Changes by James R Whitehead (kudos for recursive walker) and David Coveney 2011-08-26

## Version 1.0.2
 * Typos corrected, button text tweak - David Coveney / Robert O'Rourke

## Version 1.0.1
 * Styling and form added by James R Whitehead.

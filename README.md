# Search Replace DB

This script was made to aid the process of migrating PHP and MySQL based websites. It has additional features for WordPress and Drupal but works for most other similar CMSes.

If you find a problem let us know in the issues area and if you can improve the code then please fork the repository and send us a pull request :)

## Usage

1. Migrate all your website files
2. Upload the script folder to your web root or higher (eg. the same folder as `wp-config.php` or `wp-content`)
3. Browse to the script folder URL in your web browser
4. Fill in the fields as needed
5. Choose the `Dry run` button to do a dry run without searching/replacing

## Warnings & Limitations

1. Three character UTF8 seems to break in certain cases.
2. We can't test every possible case, though we do our best. Backups and verifications are important.
3. The license for this script is GPL v3 and no longer WTFPL. Please bear this in mind if contributing or branching.
4. You use this script at your own risk and we have no responsibility for any problems it may cause. Do backups.

### CLI script

```
ARGS
	-h, --host
		Required. The hostname of the database server.
	-n, --name
		Required. Database name.
	-u, --user
		Required. Database user.
	-p, --pass
		Required. Database user's password.
	-s, --search
		String to search for or `preg_replace()` style
		regular expression.
	-r, --replace
		None empty string to replace search with or
		`preg_replace()` style replacement.
	-t, --tables
		If set only runs the script on the specified table, comma
		separate for multiple values.
	-i, --include-cols
		If set only runs the script on the specified columns, comma
		separate for multiple values.
	-x, --exclude-cols
		If set excludes the specified columns, comma separate for
		multiple values.
	-g, --regex [no value]
		Treats value for -s or --search as a regular expression and
		-r or --replace as a regular expression replacement.
	-l, --pagesize
		How rows to fetch at a time from a table.
	-z, --dry-run [no value]
		Prevents any updates happening so you can preview the number
		of changes to be made
	-e, --alter-engine
		Changes the database table to the specified database engine
		eg. InnoDB or MyISAM. If specified search/replace arguments
		are ignored. They will not be run simultaneously.
	-a, --alter-collation
		Changes the database table to the specified collation
		eg. utf8_unicode_ci. If specified search/replace arguments
		are ignored. They will not be run simultaneously.
	-v, --verbose [true|false]
		Defaults to true, can be set to false to run script silently.
	--help
		Displays this help message ;)
```

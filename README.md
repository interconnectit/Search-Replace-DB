# Search Replace DB

This script was made to aid the process of migrating PHP and MySQL based websites. It has additional features for WordPress but works for most other similar CMSes.

If you find a problem let us know in the issues area and if you can improve the code then please fork the repository and send us a pull request :)

## Usage

1. Migrate all your website files
2. Upload the script to your web root (or the same folder as wp-config.php)
3. Browse to the script's URL in your web browser
4. Follow the on-screen instructions
5. Select the `Dry-run` checkbox to do a dry run without searching/replacing

### CLI script

1. Run the CLI script from the command line like so:
   ```
   ./searchreplacedb2cli.php --host localhost --user root --database test --pass "pass" 
      --charset utf8 --search "findMe" --replace "replaceMe"
   ```
2. use the `--dry-run` flag to do a dry run without searching/replacing

You can use short form arguments too so `--host` becomes `-h` and so on.

## _Note_

If you use some dynamic processing to setup the database definitions in WordPress try using the 'filestream' branch. Let us know if you find any bugs or have any suggestions to improve it.

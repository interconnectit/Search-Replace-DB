<?php
/*
 * This file is part of Search-Replace-DB.
 * Copyright © 2020  Interconnect IT Limited
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

/**
 * Search Replace class unit tests
 * Written for PHPUnit
 *
 * Requires a mysql database with the following schema and utf8_unicode_ci collation:
 *
 *        posts
 *            id INT
 *            content VARCHAR(2000)
 *            url VARCHAR(2000)
 *            serialised VARCHAR(2000)
 *
 */

date_default_timezone_set( 'Europe/London' );

class SrdbTest extends \PHPUnit\Framework\TestCase {

    static private $pdo;

    const TESTDB = array(
        'host'  => '127.0.0.1',
        'name'  => 'srdbtest',
        'user'  => 'travis',
        'pass'  => '',
        'table' => 'posts'
    );

    public static function setUpBeforeClass(): void {
        // get class to test
        require_once( dirname( __FILE__ ) . '/../srdb.class.php' );

        // setup the database

        self::$pdo = new PDO( "mysql:host=" . static::TESTDB['host'],
            static::TESTDB['user'],
            static::TESTDB['pass'],
            array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4' ) );

        self::$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

        self::$pdo->query( "CREATE DATABASE IF NOT EXISTS `" . static::TESTDB['user'] . "` CHARACTER SET = 'utf8mb4' COLLATE = 'utf8mb4_general_ci';" );
        self::$pdo->query( "CREATE TABLE IF NOT EXISTS `" . static::TESTDB['name'] . "`.`" . static::TESTDB['table'] . "` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`content` blob,
				`url` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
				`serialised` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
				PRIMARY KEY (`id`)
			  ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;" );

        self::$pdo->query( "USE `" . static::TESTDB['name'] . "`;" );

        // Get the charset of the table.
        $charset = self::get_table_character_set();
        if ( $charset ) {
            self::$pdo->query( "SET NAMES {$charset};" );
        }
        static::loadContent();
    }

    public static function loadContent(): void {
        static::$pdo->exec( "TRUNCATE table `" . static::TESTDB['table'] . "`" );
        $stm = self::$pdo->prepare(
            "INSERT INTO
                        `" . static::TESTDB['name'] . "`.`" . static::TESTDB['table'] . "`
                        (`id`, `content`, `url`,`serialised`)
                        VALUES (?, ?, ?, ?)" );

        $xml = simplexml_load_file( dirname( __FILE__ ) . '/DataSet.xml' );

        foreach ( $xml->table[0]->row as $row ) {
            $stm->bindValue( 1, (int) $row->value[0], PDO::PARAM_INT );
            $stm->bindValue( 2, (string) $row->value[1] );
            $stm->bindValue( 3, (string) $row->value[2] );
            $stm->bindValue( 4, serialize( unserialize( (string) $row->value[3] ) ) );
            $stm->execute();
        }
    }

    protected function setUp(): void {
        static::loadContent();
    }


    public static function tearDownAfterClass(): void {
        self::$pdo = null;
    }


    public static function get_table_character_set() {
        $charset = self::$pdo->query( "SELECT c.`character_set_name`
			FROM information_schema.`TABLES` t
				LEFT JOIN information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` c
				ON (t.`TABLE_COLLATION` = c.`COLLATION_NAME`)
			WHERE t.table_schema = '" . static::TESTDB['name'] . "'
				AND t.table_name = '" . static::TESTDB['table'] . "'
			LIMIT 1;" );

        $encoding = false;
        if ( $charset ) {
            $result = $charset->fetch();
            if ( isset( $result['character_set_name'] ) ) {
                $encoding = $result['character_set_name'];
            }
        }

        return $encoding;
    }

    /*
     * @test search replace
     */
    public function testSearchReplace() {

        // search replace strings
        $search  = 'example.com';
        $replace = 'example.org';

        // runs search/replace
        $srdb = new icit_srdb( array_merge( array(
            'search'  => $search,
            'replace' => $replace,
            'dry_run' => false
        ), static::TESTDB ) );

        // results from sample data

        // no errors
        $this->assertEmpty( $srdb->errors['results'],
            "Search replace script errors were found: \n" . implode( "\n", $srdb->errors['results'] ) );
        $this->assertEmpty( $srdb->errors['db'],
            "Search replace script database errors were found: \n" . implode( "\n", $srdb->errors['db'] ) );

        // update statements run
        $updates = $srdb->report['updates'];
        $this->assertEquals( 50, $updates, 'Wrong number of updates reported' );

        // cells changed
        $changes = $srdb->report['change'];
        $this->assertEquals( 150, $changes, 'Wrong number of cells changed reported' );

        // test the database is actually changed
        $modified = self::$pdo->query( "SELECT url FROM `" . static::TESTDB['table'] . "` LIMIT 1;" )->fetchColumn();
        $this->assertRegExp( "/{$replace}/", $modified );

    }

    public function testSearchReplaceUnicode() {

        // search replace strings
        $search  = 'perspiciatis';
        $replace = '😸';

        // runs search/replace
        $srdb = new icit_srdb( array_merge( array(
            'search'  => $search,
            'replace' => $replace,
            'dry_run' => false
        ), static::TESTDB ) );

        // results from sample data

        // no errors
        $this->assertEmpty( $srdb->errors['results'],
            "Search replace script errors were found: \n" . implode( "\n", $srdb->errors['results'] ) );
        $this->assertEmpty( $srdb->errors['db'],
            "Search replace script database errors were found: \n" . implode( "\n", $srdb->errors['db'] ) );

        // update statements run
        $updates = $srdb->report['updates'];
        $this->assertEquals( 50, $updates, 'Wrong number of updates reported' );

        // cells changed
        $changes = $srdb->report['change'];
        $this->assertEquals( 50, $changes, 'Wrong number of cells changed reported' );

        // test the database is actually changed
        $modified = self::$pdo->query( "SELECT content FROM `" . static::TESTDB['table'] . "` LIMIT 1;" )->fetchColumn();
        $this->assertRegExp( "/{$replace}/", $modified );

    }


    /*
     * @test str_replace regex
     */
    public function testRegexReplace() {

        // search replace strings
        $search  = '#https?://([a-z0-9\.-]+)/#';
        $replace = 'https://\1/';

        // class instance with regex enabled
        $srdb = new icit_srdb( array_merge( array(
            'regex'   => true,
            'dry_run' => false
        ), static::TESTDB ) );

        // direct method invocation
        $subject  = 'http://example.com/';
        $result   = 'https://example.com/';
        $replaced = $srdb->str_replace( $search, $replace, $subject );
        $this->assertEquals( $result, $replaced );
    }

    public function testRegexReplaceDB() {
        // search replace strings
        $search  = '#^https?://([a-z0-9\.]+)/#';
        $replace = 'https://$1/';

        $result = '#https://example.com/#';

        // class instance with regex enabled
        $srdb = new icit_srdb( array_merge( array(
            'search'  => $search,
            'replace' => $replace,
            'regex'   => true,
            'dry_run' => false
        ), static::TESTDB ) );

        // results from sample data
        // no errors
        $this->assertEmpty( $srdb->errors['results'],
            "Search replace script errors were found: \n" . implode( "\n", $srdb->errors['results'] ) );
        $this->assertEmpty( $srdb->errors['db'],
            "Search replace script database errors were found: \n" . implode( "\n", $srdb->errors['db'] ) );

        // update statements run
        $updates = $srdb->report['updates'];
        $this->assertEquals( 50, $updates, 'Wrong number of updates reported' );

        // cells changed
        $changes = $srdb->report['change'];
        $this->assertEquals( 100, $changes, 'Wrong number of changes reported' );

        // test the database is actually changed
        $modified = self::$pdo->query( "SELECT url FROM `" . static::TESTDB['table'] . "` LIMIT 1;" )->fetchColumn();
        $this->assertRegExp( $result, $modified, 'Database not updated, modified result is ' . $modified );

    }

    /**
     * @test str_replace serialised data
     */
    public function testStrReplaceSerialised() {

        // search replace strings
        $search  = 'serialised string';
        $replace = 'longer serialised string';

        // class instance with regex enabled
        $srdb = new icit_srdb( array_merge( array(
            'search'  => $search,
            'replace' => $replace,
            'dry_run' => false
        ), static::TESTDB ) );

        // results from sample data

        // no errors
        $this->assertEmpty( $srdb->errors['results'],
            "Search replace script errors were found: \n" . implode( "\n", $srdb->errors['results'] ) );
        $this->assertEmpty( $srdb->errors['db'],
            "Search replace script database errors were found: \n" . implode( "\n", $srdb->errors['db'] ) );

        // update statements run
        $updates = $srdb->report['updates'];
        $this->assertEquals( 50, $updates, 'Wrong number of updates reported' );

        // cells changed
        $changes = $srdb->report['change'];
        $this->assertEquals( 50, $changes, 'Wrong number of changes reported' );

        // check unserialised values are what they should be
        $modified = self::$pdo->query( "SELECT serialised FROM `" . static::TESTDB['table'] . "` LIMIT 1;" )->fetchColumn();
        $from     = unserialize( $modified );

        $this->assertEquals( $replace, $from['string'], 'Unserialised array value not updated' );

    }

    /*
     * @test recursive unserialize replace
     */
    public function testRecursiveUnserializeReplace() {

        // search replace strings
        $search  = 'serialised string';
        $replace = 'longer longer serialised string';

        // class instance with regex enabled
        $srdb = new icit_srdb( array_merge( array(
            'search'  => $search,
            'replace' => $replace,
            'dry_run' => false
        ), static::TESTDB ) );

        // results from sample data

        // no errors
        $this->assertEmpty( $srdb->errors['results'],
            "Search replace script errors were found: \n" . implode( "\n", $srdb->errors['results'] ) );
        $this->assertEmpty( $srdb->errors['db'],
            "Search replace script database errors were found: \n" . implode( "\n", $srdb->errors['db'] ) );

        // check unserialised values are what they should be
        $modified = self::$pdo->query( "SELECT serialised FROM `" . static::TESTDB['table'] . "` LIMIT 1;" )->fetchColumn();
        $from     = unserialize( $modified );

        $this->assertEquals( $replace, $from['nested']['string'], 'Unserialised nested array value not updated' );

    }

    /*
     * @test include columns
     */
    public function testIncludeColumns() {

        // search replace strings
        $search  = 'example.com';
        $replace = 'example.org';

        // class instance with regex enabled
        $srdb = new icit_srdb( array_merge( array(
            'search'       => $search,
            'replace'      => $replace,
            'dry_run'      => false,
            'include_cols' => array( 'url' )
        ), static::TESTDB ) );

        // results from sample data

        // no errors
        $this->assertEmpty( $srdb->errors['results'],
            "Search replace script errors were found: \n" . implode( "\n", $srdb->errors['results'] ) );
        $this->assertEmpty( $srdb->errors['db'],
            "Search replace script database errors were found: \n" . implode( "\n", $srdb->errors['db'] ) );

        // update statements run
        $updates = $srdb->report['updates'];
        $this->assertEquals( 50, $updates, 'Wrong number of updates reported' );

        // cells changed
        $changes = $srdb->report['change'];
        $this->assertEquals( 50, $changes, 'Wrong number of changes reported' );


        // check unserialised values are what they should be
        $modified = self::$pdo->query( "SELECT content, url FROM `" . static::TESTDB['table'] . "` LIMIT 1;" )->fetchAll();
        $content  = $modified[0]['content'];
        $url      = $modified[0]['url'];

        $this->assertRegExp( "/$search/", $content, 'Content column was modified' );
        $this->assertRegExp( "/$replace/", $url, 'URL column was not modified' );

    }

    /*
     * @test exclude columns
     */
    public function testExcludeColumns() {

        // search replace strings
        $search  = 'example.com';
        $replace = 'example.org';

        // class instance with regex enabled
        $srdb = new icit_srdb( array_merge( array(
            'search'       => $search,
            'replace'      => $replace,
            'dry_run'      => false,
            'exclude_cols' => array( 'url' )
        ), static::TESTDB ) );

        // results from sample data

        // no errors
        $this->assertEmpty( $srdb->errors['results'],
            "Search replace script errors were found: \n" . implode( "\n", $srdb->errors['results'] ) );
        $this->assertEmpty( $srdb->errors['db'],
            "Search replace script database errors were found: \n" . implode( "\n", $srdb->errors['db'] ) );

        // update statements run
        $updates = $srdb->report['updates'];
        $this->assertEquals( 50, $updates, 'Wrong number of updates reported' );

        // cells changed
        $changes = $srdb->report['change'];
        $this->assertEquals( 100, $changes, 'Wrong number of changes reported' );

        // check unserialised values are what they should be
        $modified = self::$pdo->query( "SELECT content, url FROM `" . static::TESTDB['table'] . "` LIMIT 1;" )->fetchAll();
        $content  = $modified[0]['content'];
        $url      = $modified[0]['url'];

        $this->assertRegExp( "/$replace/", $content, 'Content column was not modified' );
        $this->assertRegExp( "/$search/", $url, 'URL column was modified' );

    }

    /**
     * @test multibyte string replacement method
     */
    public function testMultibyteStrReplace() {

        $subject  = 'föö ❤ ☀ ☆ ☂ ☻ ♞ ☯';
        $result   = 'föö ❤ ☻ ♞ ☯ ☻ ♞ ☯';
        $replaced = icit_srdb::mb_str_replace( '☀ ☆ ☂', '☻ ♞ ☯', $subject );

        $this->assertEquals( $result, $replaced );

    }

}

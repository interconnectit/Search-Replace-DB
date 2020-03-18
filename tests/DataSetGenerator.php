<?php
/*
 * This file is part of Search-Replace-DB.
 * Copyright © 2020  Interconnect IT Limited
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

$content = '<p>Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium,
	totam rem aperiam, <a href="http://example.com/~~~/">eaque ipsa quae</a> ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt
	explicabo.</p>
	<p>Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur
	magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor
	sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore
	magnam aliquam quaerat voluptatem.</p>
	<div class="image">
		<img src="http://example.com/assets/image.jpg" alt="Image" />
	</div>
	<p>Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis
	suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea
	voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla
	pariatur?</p>
	<div class="image">
		<img src="http://site-^^^.example.com/assets/image.jpg" alt="Image" />
	</div>
	<p>Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis
	suscipit laboriosam, <a href="http://site-^^^.example.com/blog/~~~/">nisi ut aliquid ex ea commodi consequatur?</a> Quis autem vel eum iure reprehenderit qui in ea
	voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla
	pariatur?</p>';

$serialised = array(
				'number' => 123,
				'float' => 12.345,
				'string' => 'serialised string',
				'accented' => 'föó ßåŗ',
				'unicode' => '❤ ☀ ☆ ☂ ☻ ♞ ☯ 😸 😹',
				'url' => 'http://example.com/'
			);

$serialised[ 'nested' ] = $serialised;

$numbers = range( 1, 100 );
$letters = range( 'a', 'z' );
//var_dump( $letters );

//mb_internal_encoding( 'UTF-8' );

header( 'Content-type: text/xml' );
header( 'Charset: UTF-8' );

//var_dump( unserialize( serialize( $serialised ) ) );

echo '<?xml version="1.0" encoding="UTF-8" ?>
<dataset>
    <table name="posts">
        <column>id</column>
        <column>content</column>
        <column>url</column>
		<column>serialised</column>';

for( $i = 1; $i < 51; $i++ ) {

	$s = $serialised;
	$s[ 'url' ] .= $numbers[ array_rand( $numbers, 1 ) ] . '/';
	$row_content = str_replace( '~~~', $numbers[ array_rand( $numbers, 1 ) ], $content );
	$row_content = str_replace( '^^^', $letters[ array_rand( $letters, 1 ) ], $row_content );

	echo '
		<row>
			<value>' . $i . '</value>
			<value>
				<![CDATA[
				' . $row_content . '
				]]>
			</value>
			<value>http://example.com/' . $i . '/</value>
			<value>' . serialize( $s ) . '</value>
		</row>
	';

	//var_dump( unserialize( serialize( $s ) ) );

}

echo '
	</table>
</dataset>';

// patch console free browsers
window.console = window.console || { log: function(){} };

;(function($){

	var srdb;

	srdb = function() {

		var t = this,
			dom = $( 'html' );

		$.extend( t, {

			errors: {},
			report: {},
			info: {},
			prev_data: {},
			tables: 0,
			rows: 0,
			changes: 0,
			updates: 0,
			time: 0.0,

			// constructor
			init: function() {

				console.log( $( '.row-db'  ) );

				// search replace ui
				if ( $( '.row-db' ).length ) {

					// show/hide tables
					dom.on( 'click', '[name="use_tables"]', t.toggle_tables );
					dom.find( '[name="use_tables"][checked]' ).click();

					// toggle regex mode
					dom.on( 'click', '[name="regex"]', t.toggle_regex );
					dom.find( '[name="regex"][checked]' ).click();

					// ajax form
					dom.on( 'submit', 'form', t.submit_proxy );
					dom.on( 'click', '[type="submit"]', t.submit );

				// deleted ui
				} else {

					// mailchimp
					dom.on( 'submit', 'form[action*="list-manage.com"]', t.mailchimp );

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
				</p>',
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

			toggle_tables: function() {
				if ( this.id == 'all_tables' ) {
					dom.find( '.table-select' ).slideUp( 400 );
				} else {
					dom.find( '.table-select' ).slideDown( 400 );
				}
			},

			toggle_regex: function() {
				if ( $( this ).is( ':checked' ) )
					dom.removeClass( 'regex-off' ).addClass( 'regex-on' );
				else
					dom.removeClass( 'regex-on' ).addClass( 'regex-off' );
			},

			reset: function() {
				t.errors = {};
				t.report = {};
				t.tables = 0;
				t.rows = 0;
				t.changes = 0;
				t.updates = 0;
				t.time = 0.0;
			},

			map_form_data: function( $form ) {
				var data_temp = $form.serializeArray(),
					data = {};
				$.map( data_temp, function( field, i ) {
					if ( data[ field.name ] ) {
						if ( ! $.isArray( data[ field.name ] ) )
							data[ field.name ] = [ data[ field.name ] ];
						data[ field.name ].push( field.value );
					}
					else {
						if ( field.value === '1' )
							field.value = true;
						data[ field.name ] = field.value;
					}
				} );
				return data;
			},

			submit_proxy: function( e ) {
				$( '[type="submit"][value="update"]' ).click();
				console.log( 'submit proxy' );
				return false;
			},

			submit: function( e ) {

				// avoid submission not coming from a button click
				if ( $( this ).is( 'form' ) )
					return false;

				var $button = $( this ),
					$form = $button.parents( 'form' ),
					tables = [],
					submit = $button.attr( 'name' ),
					$feedback = $( '.errors, .report' ),
					feedback_length = $feedback.length;

				if ( submit == 'submit[delete]' )
					return true;

				if ( submit == 'submit[liverun]' && ! window.confirm( 'Are you absolutely ready to run search/replace? Make sure you have backed up your database!' ) )
					return false;

				if ( ( submit == 'submit[innodb]' || submit == 'submit[utf8]' ) && ! window.confirm( 'Are you absolutely ready to modify the tables? Make sure you have backed up your database!' ) )
					return false;

				// stop normal submission
				e.preventDefault();

				// disable buttons & add spinner
				$button.addClass( 'active' );
				$( '[type="submit"]' ).attr( 'disabled', 'disabled' );

				// reset reports
				t.reset();

				// get form data as an object
				data = t.map_form_data( $form );

				// use all tables if none selected
				if ( ! data[ 'tables[]' ] || ! data[ 'tables[]' ].length )
					data[ 'tables[]' ] = $.map( $( 'select[name^="tables"] option' ), function( el, i ) { return $( el ).attr( 'value' ); } );

				// check we don't just have one table selected as we get a string not array
				if ( ! $.isArray( data[ 'tables[]' ] ) )
					data[ 'tables[]' ] = [ data[ 'tables[]' ] ];

				// add in ajax and submit params
				data = $.extend( {
					ajax: true,
					submit: submit
				}, data );

				// clear previous errors
				if ( feedback_length ) {
					$feedback.each( function( i ) {
						$( this ).fadeOut( 200, function() {
							$( this ).remove();

							// start recursive table post
							if ( i+1 == feedback_length )
								t.recursive_fetch_json( data, 0 );
						} );
					} );
				} else {
					// start recursive table post
					t.recursive_fetch_json( data, 0 );
				}

				return false;
			},

			complete: function() {
				// remove spinner
				$( '[type="submit"]' )
					.removeClass( 'active' )
					.not( '.db-required' )
					.removeAttr( 'disabled' );
				if ( typeof t.errors.db != 'undefined' && ! t.errors.db.length )
					$( '[type="submit"].db-required' ).removeAttr( 'disabled' );
			},

			recursive_fetch_json: function( data, i ) {

				// break from loop
				if ( data[ 'tables[]' ].length && typeof data[ 'tables[]' ][ i ] == 'undefined' ) {
					t.complete();
					return false;
				}

				// clone data
				var post_data = $.extend( true, {}, data ),
					dry_run = data.submit != 'submit[liverun]',
					strings = dry_run ? t.strings_dry : t.strings_live,
					result = true,
					start = Date.now() / 1000,
					end = start;

				// remap values so we just do one table at a time
				post_data[ 'tables[]' ] = [ data[ 'tables[]' ][ i ] ];
				post_data.use_tables = 'subset';

				return $.post( window.location.href, post_data, function( response ) {

					if ( response ) {

						var errors = response.errors,
							report = response.report,
							info   = response.info;

						// append errors
						$.each( errors, function( type, error_list ) {

							if ( ! error_list.length ) {
								if ( type == 'db' ) {
									$( '[name="use_tables"]' ).removeAttr( 'disabled' );
									if ( $( '.table-select' ).html() == '' || ( t.prev_data.name && t.prev_data.name !== data.name ) )
										$( '.table-select' ).html( info.table_select );
									if ( $.inArray( 'InnoDB', info.engines ) >= 0 && ! $( '[name="submit\[innodb\]"]' ).length )
										$( '[name="submit\[utf8\]"]' ).before( '<input type="submit" name="submit[innodb]" value="convert to innodb" class="db-required secondary field-advanced" />' );
								}
								return;
							}

							var $row = $( '.row-' + type ),
								$errors = $row.find( '.errors' );

							if ( ! $errors.length ) {
								$errors = $( '<div class="errors"></div>' ).hide().insertAfter( $( 'legend,h1', $row ) );
								$errors.fadeIn( 200 );
							}

							$.each( error_list, function( i, error ) {
								if ( ! t.errors[ type ] || $.inArray( error, t.errors[ type ] ) < 0 )
									$( '<p>' + error + '</p>' ).hide().appendTo( $errors ).fadeIn( 200 );
							} );

							if ( type == 'db' ) {
								$( '[name="use_tables"]' ).eq(0).click().end().attr( 'disabled', 'disabled' );
								$( '.table-select' ).html( '' );
								$( '[name="submit\[innodb\]"]' ).remove();
							}

						} );

						// scroll back to top most errors block
						if ( $( '.errors' ).length && $( '.errors' ).eq( 0 ).offset().top < $( 'body' ).scrollTop() )
							$( 'html,body' ).animate( { scrollTop: $( '.errors' ).eq(0).offset().top }, 300 );

						// track errors
						$.extend( true, t.errors, errors );

						// track info
						$.extend( true, t.info, info );

						// append reports
						if ( report.tables ) {

							var $row = $( '.row-results' ),
								$report = $row.find( '.report' ),
								$table_reports = $row.find( '.table-reports' );

							if ( ! $report.length )
								$report = $( '<div class="report"></div>' ).appendTo( $row );

							end = Date.now() / 1000;

							t.tables += report.tables;
							t.rows += report.rows;
							t.changes += report.change;
							t.updates += report.updates;
							t.time += t.get_time( start, end );

							if ( ! $report.find( '.main-report' ).length ) {
								$( t.report_tpl )
									.find( '[data-report="search_replace"]' ).html( strings.search_replace ).end()
									.find( '[data-report="search"]' ).html( data.search ).end()
									.find( '[data-report="replace"]' ).html( data.replace ).end()
									.find( '[data-report="dry_run"]' ).html( strings.updates ).end()
									.prependTo( $report );
							}

							$( '.main-report' )
								.find( '[data-report="tables"]' ).html( t.tables ).end()
								.find( '[data-report="rows"]' ).html( t.rows ).end()
								.find( '[data-report="changes"]' ).html( t.changes ).end()
								.find( '[data-report="updates"]' ).html( t.updates ).end()
								.find( '[data-report="time"]' ).html( t.time.toFixed( 7 ) ).end();

							if ( ! $table_reports.length )
								$table_reports = $( '\
									<table class="table-reports">\
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
									</table>' ).appendTo( $report );

							$.each( report.table_reports, function( table, table_report ) {

								var $view_changes = '',
									changes_length = table_report.changes.length;

								if ( changes_length ) {
									$view_changes = $( '<a href="#" title="View the first ' + changes_length + ' modifications">view changes</a>' )
										.data( 'report', table_report )
										.data( 'table', table )
										.click( t.changes_overlay );
								}

								$( '<tr class="' + table + '">' + t.table_report_tpl + '</tr>' )
									.hide()
									.find( '[data-report="table"]' ).html( table ).end()
									.find( '[data-report="rows"]' ).html( table_report.rows ).end()
									.find( '[data-report="changes"]' ).html( table_report.change + ' ' ).append( $view_changes ).end()
									.find( '[data-report="updates"]' ).html( table_report.updates ).end()
									.find( '[data-report="time"]' ).html( t.get_time( start, end ).toFixed( 7 ) ).end()
									.prependTo( $table_reports.find( 'tbody' ) )
									.fadeIn( 150 );

							} );

							$.extend( true, t.report, report );

							// fetch next table
							t.recursive_fetch_json( data, ++i );

						} else if ( report.engine ) {

							var $row = $( '.row-results' ),
								$report = $row.find( '.report' ),
								$table_reports = $row.find( '.table-reports' );

							if ( ! $report.length )
								$report = $( '<div class="report"></div>' ).appendTo( $row );

							if ( ! $table_reports.length )
								$table_reports = $( '\
									<table class="table-reports">\
										<thead>\
											<tr>\
												<th>Table</th>\
												<th>Engine</th>\
											</tr>\
										</thead>\
										<tbody></tbody>\
									</table>' ).appendTo( $report );

							$.each( report.converted, function( i, table ) {

								$( '<tr class="' + table + '"><td>' + table + '</td><td>' + report.engine + '</td></tr>' )
									.hide()
									.prependTo( $table_reports.find( 'tbody' ) )
									.fadeIn( 150 );

							} );

							// fetch next table
							t.recursive_fetch_json( data, ++i );

						} else if ( report.collation ) {

							var $row = $( '.row-results' ),
								$report = $row.find( '.report' ),
								$table_reports = $row.find( '.table-reports' );

							if ( ! $report.length )
								$report = $( '<div class="report"></div>' ).appendTo( $row );

							if ( ! $table_reports.length )
								$table_reports = $( '\
									<table class="table-reports">\
										<thead>\
											<tr>\
												<th>Table</th>\
												<th>Charset</th>\
												<th>Collation</th>\
											</tr>\
										</thead>\
										<tbody></tbody>\
									</table>' ).appendTo( $report );

							$.each( report.converted, function( i, table ) {

								$( '\
											<tr class="' + table + '">\
												<td>' + table + '</td>\
												<td>' + report.collation.replace( /^([^_]+).*$/, '$1' ) + '</td>\
												<td>' + report.collation + '</td>\
											</tr>' )
									.hide()
									.prependTo( $table_reports.find( 'tbody' ) )
									.fadeIn( 150 );

							} );

							// fetch next table
							t.recursive_fetch_json( data, ++i );

						} else {

							console.log( 'no report' );
							t.complete();

						}

					} else {

						console.log( 'no response' );
						t.complete();

					}

					// remember previous request
					t.prev_data = $.extend( {}, data );

					return true;

				}, 'json' );

			},

			get_time: function( start, end ) {
				start 	= start || 0.0;
				end 	= end 	|| 0.0;
				start 	= parseFloat( start );
				end 	= parseFloat( end );
				var diff = end - start;
				return parseFloat( diff < 0.0 ? 0.0 : diff );
			},

			changes_overlay: function( e ) {
				e.preventDefault();

				var $overlay = $( '.changes-overlay' ),
					table = $( this ).data( 'table' ),
					report = $( this ).data( 'report' )
					changes = report.changes,
					search = $( '[name="search"]' ).val(),
					replace = $( '[name="replace"]' ).val(),
					regex = $( '[name="regex"]' ).is( ':checked' ),
					regex_i = $( '[name="regex_i"]' ).is( ':checked' ),
					regex_m = $( '[name="regex_m"]' ).is( ':checked' ),
					regex_search = new RegExp( search, 'g' + ( regex_i ? 'i' : '' ) + ( regex_m ? 'm' : '' ) );

				if ( ! $overlay.length ) {
					$overlay = $( '<div class="changes-overlay"><div class="overlay-header"><a class="close" href="#close">&times; Close</a><h1></h1></div><div class="changes"></div></div>' )
						.hide()
						.find( '.close' )
							.click( function( e ) {
								e.preventDefault();
								$overlay.fadeOut( 300 );
								$( 'body' ).css( { overflow: 'auto' } );
							} )
							.end()
						.appendTo( $( 'body' ) );
				}

				$( 'body' ).css( { overflow: 'hidden' } );

				$overlay
					.find( 'h1' ).html( table ).end()
					.find( '.changes' ).html( '' ).end()
					.fadeIn( 300 )
					.find( '.changes' ).html( function() {
							var $changes = $( this );
							$.each( changes, function( i, item ) {
								if ( i >= 20 )
									return false;
								var match_search,
									match_replace,
									text,
									$change = $( '\
										<div class="diff-wrap">\
											<h3>row ' + item.row + ', column `' + item.column + '`</h3>\
											<div class="diff">\
												<pre class="from"></pre>\
												<pre class="to"></pre>\
											</div>\
										</div>' )
									.find( '.from' ).text( item.from ).end()
									.find( '.to' ).text( item.to ).end()
									.appendTo( $changes );
								if ( regex ) {
									text = $change.find( '.from' ).html();
									match_search = text.match( regex_search );
									$.each( match_search, function( i, match ) {
										match_replace = match.replace( regex_search, replace );
										$change.html( $change.html().replace( new RegExp( match, 'g' ), '<span class="highlight">' + match + '</span>' ) );
										$change.html( $change.html().replace( new RegExp( match_replace, 'g' ), '<span class="highlight">' + match_replace + '</span>' ) );
									} );
								} else {
									$change.html( $change.html().replace( new RegExp( search, 'g' ), '<span class="highlight">' + search + '</span>' ) );
									$change.html( $change.html().replace( new RegExp( replace, 'g' ), '<span class="highlight">' + replace + '</span>' ) );
								}
								return true;
							} );
						} ).end();

			},

			fetch_products: function() {

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
				$.ajax( {
					type: 'GET',
					url: 'http://products.network.interconnectit.com/api/core/get_posts/',
					data: { order: 'ASC', orderby: 'menu_order title' },
					dataType: 'jsonp',
					jsonpCallback: 'show_products',
					contentType: 'application/json',
					success: function( products ) {
						$products = $( '.row-products .content' ).html( '' );
						$.each( products.posts, function( i, product ) {

							// run template replacement
							$products.append( tpl.replace( /{{([a-z\.\[\]0-9_]+)}}/g, function( match, p1, offset, search ) {
								return typeof eval( 'product.' + p1 ) != 'undefined' ? eval( 'product.' + p1 ) : '';
							} ) );

						} );
					},
					error: function(e) {

					}
				} );

			},

			fetch_blogs: function() {

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
				$.ajax( {
					type: 'GET',
					url: 'http://interconnectit.com/api/core/get_posts/',
					data: { count: 3, category__not_in: [ 216 ] },
					dataType: 'jsonp',
					jsonpCallback: 'show_blogs',
					contentType: 'application/json',
					success: function( blogs ) {
						$blogs = $( '.row-blog .content' ).html( '' );
						$.each( blogs.posts, function( i, blog ) {

							// run template replacement
							$blogs.append( tpl.replace( /{{([a-z\.\[\]0-9_]+)}}/g, function( match, p1, offset, search ) {
								var value = typeof eval( 'blog.' + p1 ) != 'undefined' ? eval( 'blog.' + p1 ) : '';
								if ( p1 == 'date' )
									value = new Date( value ).toDateString();
								if ( p1 == 'categories' )
									value = $.map( value, function( category, i ){ return category.title; } ).join( ', ' );
								return value;
							} ) );

						} );
					},
					error: function(e) {

					}
				} );

			},

			mailchimp: function( e ) {
				e.preventDefault();

				var $this = $( this ),
					$form = $this.is( 'form' ) ? $this : $this.parents( 'form' ),
					$button = $form.find( 'input[type="submit"]' ).addClass( 'active' ),
					action = $form.attr( 'action' ).replace( /subscribe\/post$/, 'subscribe/post-json' );

				// remove errors
				$( '.row-subscribe .errors' ).remove();

				// get response from mailchimp
				$.ajax( {
					type: 'GET',
					url: action,
					data: $form.serialize() + '&c=?',
					dataType: 'json',
					success: function( response ) {
						console.log( response );

						if ( response && response.result == 'success' ) {
							$form.find( '>*' ).fadeOut( 150, function() {
								$form.html( '' );
								$( '<div class="content"><p class="thanks">Success! We didn&rsquo;t think it was possible but now we like you even more!</p></div>' )
									.hide()
									.insertAfter( $form )
									.fadeIn( 300 );
								$form.remove();
							} );
						}

						if ( response && response.result != 'success' ) {

							$( '<div class="errors"><p>Computer says no&hellip; Can you check you&rsquo;ve filled in the email address field correctly?</p></div>' )
								.hide()
								.insertAfter( '.row-subscribe h1' )
								.fadeIn( 200 );

						}
					},
					complete: function() {
						$button.removeClass( 'active' );
					}
				} );

			}

		} );

		// constructor
		t.init();

		return t;
	}

	// load on ready
	$( document ).ready( srdb );

})(jQuery);

function containsSerialisedString(text) {
    // we can't display the highlight on objects with strings (manifest as "s:digit") because this might change the length
    return ( ( /s:\d/.exec(text) ) ? true : false );
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
            time: 0.0,
            button: false,
            running: false,
            countdown: null,
            escape: false,

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
                t.time = 0.0;
            },

            map_form_data: function ($form) {
                var data_temp = $form.serializeArray(),
                    data = {};
                $.map(data_temp, function (field, i) {
                    if (data[field.name]) {
                        if (!$.isArray(data[field.name]))
                            data[field.name] = [data[field.name]];
                        data[field.name].push(field.value);
                    }
                    else {
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

                if (( submit == 'submit[innodb]' || submit == 'submit[utf8]' || submit == 'submit[utf8mb4]' ) && !window.confirm(t.confirm_strings.modify)) {
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

                // clone data
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
                                    $('[name="use_tables"]').removeAttr('disabled');
                                    // update the table dropdown if we're changing db
                                    if ($('.table-select').html() == '' || ( t.prev_data.name && t.prev_data.name !== data.name ))
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

                        });

                        // scroll back to top most errors block
                        //if ( t.errors !== errors && $( '.errors' ).length && $( '.errors' ).eq( 0 ).offset().top < $( 'body' ).scrollTop() )
                        //	$( 'html,body' ).animate( { scrollTop: $( '.errors' ).eq(0).offset().top }, 300 );

                        // track errors
                        $.extend(true, t.errors, errors);

                        // track info
                        $.extend(true, t.info, info);

                        // append reports
                        if (report.tables) {

                            var $row = $('.row-results'),
                                $report = $row.find('.report'),
                                $table_reports = $row.find('.table-reports');

                            if (!$report.length)
                                $report = $('<div class="report"></div>').appendTo($row);

                            end = Date.now() / 1000;

                            t.tables += report.tables;
                            t.rows += report.rows;
                            t.changes += report.change;
                            t.updates += report.updates;
                            t.time += t.get_time(start, end);

                            if (!$report.find('.main-report').length) {
                                $(t.report_tpl)
                                    .find('[data-report="search_replace"]').html(strings.search_replace).end()
                                    .find('[data-report="search"]').text(data.search).end()
                                    .find('[data-report="replace"]').text(data.replace).end()
                                    .find('[data-report="dry_run"]').html(strings.updates).end()
                                    .prependTo($report);
                            }

                            $('.main-report')
                                .find('[data-report="tables"]').html(t.tables).end()
                                .find('[data-report="rows"]').html(t.rows).end()
                                .find('[data-report="changes"]').html(t.changes).end()
                                .find('[data-report="updates"]').html(t.updates).end()
                                .find('[data-report="time"]').html(t.time.toFixed(7)).end();

                            if (!$table_reports.length)
                                $table_reports = $('\
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
									</table>').appendTo($report);

                            $.each(report.table_reports, function (table, table_report) {

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
                                    .appendTo($table_reports.find('tbody'))
                                    .fadeIn(150);

                            });

                            $.extend(true, t.report, report);

                            // fetch next table
                            t.recursive_fetch_json(data, ++i);

                        } else if (report.engine) {

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
												<th>Engine</th>\
											</tr>\
										</thead>\
										<tbody></tbody>\
									</table>').appendTo($report);

                            $.each(report.converted, function (table, converted) {

                                $('<tr class="' + table + '"><td>' + table + '</td><td>' + report.engine + '</td></tr>')
                                    .hide()
                                    .prependTo($table_reports.find('tbody'))
                                    .fadeIn(150);

                                $('.table-select option[value="' + table + '"]').html(function () {
                                    return $(this).html().replace(new RegExp(table + ': [^,]+'), table + ': ' + report.engine);
                                });

                            });

                            // fetch next table
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
                    regex_search_iter = new RegExp(search, 'g' + ( regex_i ? 'i' : '' ) + ( regex_m ? 'm' : '' )),
                    regex_search = new RegExp(search, 'g' + ( regex_i ? 'i' : '' ) + ( regex_m ? 'm' : '' ));

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

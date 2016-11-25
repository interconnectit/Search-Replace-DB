<form action="" method="post">

    <!-- 1. search/replace -->
    <fieldset class="row row-search">

        <h1>search<span>/</span>replace</h1>

        <?php $this->get_errors('search'); ?>

        <div class="fields fields-large">
            <label for="search"><span class="label-text">replace</span> <span
                    class="hide-if-regex-off regex-left">/</span><input id="search" type="text"
                                                                        placeholder="search for&hellip;"
                                                                        value="<?php $this->esc_html_attr($this->search, true); ?>"
                                                                        name="search"/><span
                    class="hide-if-regex-off regex-right">/</span></label>
            <label for="replace"><span class="label-text">with</span> <input id="replace" type="text"
                                                                             placeholder="replace with&hellip;"
                                                                             value="<?php $this->esc_html_attr($this->replace, true); ?>"
                                                                             name="replace"/></label>
            <label for="regex" class="field-advanced"><input id="regex" type="checkbox" name="regex"
                                                             value="1" <?php $this->checked(true, $this->regex); ?> />
                use regex</label>
        </div>

        <div class="fields field-advanced hide-if-regex-off">
            <label for="regex_i" class="field field-advanced"><input type="checkbox" name="regex_i" id="regex_i"
                                                                     value="1" <?php $this->checked(true, $this->regex_i); ?> />
                <abbr title="case insensitive">Case insensitive</abbr></abbr></label>
            <label for="regex_m" class="field field-advanced"><input type="checkbox" name="regex_m" id="regex_m"
                                                                     value="1" <?php $this->checked(true, $this->regex_m); ?> />
                <abbr title="multiline">Multiline</abbr></label>
            <label for="regex_x" class="field field-advanced"><input type="checkbox" name="regex_x" id="regex_x"
                                                                     value="1" <?php $this->checked(true, $this->regex_x); ?> />
                <abbr title="extended mode">Extended mode</abbr></label>
            <label for="regex_s" class="field field-advanced"><input type="checkbox" name="regex_s" id="regex_s"
                                                                     value="1" <?php $this->checked(true, $this->regex_s); ?> />
                <abbr title="dot also matches newlines">Dot also matches newlines</abbr></label>
        </div>

    </fieldset>

    <!-- 2. db details -->
    <fieldset class="row row-db">

        <h1>db details</h1>

        <?php $this->get_errors('environment'); ?>

        <?php $this->get_errors('recoverable_db'); ?>

        <?php $this->get_errors('db'); ?>

        <?php $this->get_errors('compatibility'); ?>
        <?php $this->get_errors('connection'); ?>

        <div class="fields fields-small">

            <div class="field field-short">
                <label for="name">name</label>
                <input id="name" name="name" type="text"
                       value="<?php $this->esc_html_attr($this->name, true); ?>"/>
            </div>

            <div class="field field-short">
                <label for="user">user</label>
                <input id="user" name="user" type="text"
                       value="<?php $this->esc_html_attr($this->user, true); ?>"/>
            </div>

            <div class="field field-short">
                <label for="pass">pass</label>
                <input id="pass" name="pass" type="password"
                       value="<?php $this->esc_html_attr($this->pass, true); ?>"/>
            </div>

            <div class="field field-short">
                <label for="host">host</label>
                <input id="host" name="host" type="text"
                       value="<?php $this->esc_html_attr($this->host, true); ?>"/>
            </div>

            <div class="field field-short">
                <label for="port">port</label>
                <input id="port" name="port" type="text"
                       value="<?php $this->esc_html_attr($this->port, true); ?>"/>
            </div>

        </div>

    </fieldset>

    <!-- 3. tables -->
    <fieldset class="row row-tables">

        <h1>tables</h1>

        <?php $this->get_errors('tables'); ?>

        <div class="fields">

            <div class="field radio">
                <label for="all_tables">
                    <input id="all_tables" name="use_tables" value="all"
                           type="radio" <?php if (!$this->db_valid()) echo 'disabled="disabled"'; ?> <?php $this->checked(true, empty($this->tables)); ?> />
                    all tables
                </label>
            </div>

            <div class="field radio">
                <label for="subset_tables">
                    <input id="subset_tables" name="use_tables" value="subset"
                           type="radio" <?php if (!$this->db_valid()) echo 'disabled="disabled"'; ?> <?php $this->checked(false, empty($this->tables)); ?> />
                    select tables
                </label>
            </div>

            <div class="field table-select hide-if-js"><?php $this->table_select(); ?></div>

        </div>

        <div class="fields field-advanced">

            <div class="field field-advanced field-medium">
                <label for="exclude_cols">columns to exclude (optional, comma separated)</label>
                <input id="exclude_cols" type="text" name="exclude_cols"
                       value="<?php $this->esc_html_attr(implode(',', $this->get('exclude_cols'))) ?>"
                       placeholder="eg. guid"/>
            </div>
            <div class="field field-advanced field-medium">
                <label for="include_cols">columns to include only (optional, comma separated)</label>
                <input id="include_cols" type="text" name="include_cols"
                       value="<?php $this->esc_html_attr(implode(',', $this->get('include_cols'))) ?>"
                       placeholder="eg. post_content, post_excerpt"/>
            </div>

        </div>

    </fieldset>

    <!-- 4. results -->
    <fieldset class="row row-results">

        <h1>actions</h1>

        <?php $this->get_errors('results'); ?>

        <div class="fields">

					<span class="submit-group">
						<input type="submit" name="submit[update]" value="update details"/>

						<input type="submit" name="submit[dryrun]"
                               value="dry run" <?php if (!$this->db_valid()) echo 'disabled="disabled"'; ?>
                               class="db-required"/>

						<input type="submit" name="submit[liverun]"
                               value="live run" <?php if (!$this->db_valid()) echo 'disabled="disabled"'; ?>
                               class="db-required"/>

						<span class="separator">/</span>
					</span>

            <span class="submit-group">
						<?php if (in_array('InnoDB', $this->get('engines'))) { ?>
                            <input type="submit" name="submit[innodb]"
                                   value="convert to innodb" <?php if (!$this->db_valid()) echo 'disabled="disabled"'; ?>
                                   class="db-required secondary field-advanced"/>
                        <?php } ?>

                <input type="submit" name="submit[utf8]"
                       value="convert to utf8 unicode" <?php if (!$this->db_valid()) echo 'disabled="disabled"'; ?>
                       class="db-required secondary field-advanced"/>

						<input type="submit" name="submit[utf8mb4]"
                               value="convert to utf8mb4 unicode" <?php if (!$this->db_valid()) echo 'disabled="disabled"'; ?>
                               class="db-required secondary field-advanced"/>

					</span>

        </div>

        <?php $this->get_report(); ?>

    </fieldset>


    <!-- 5. branding -->
    <section class="row row-delete">

        <h1>delete</h1>

        <div class="fields">
            <p>
                <input type="submit" name="submit[delete]" value="delete me"/>
                Once you&rsquo;re done click the <strong>delete me</strong> button to secure your server
            </p>
        </div>

    </section>

</form>

<section class="help">

    <h1 class="branding">interconnect/it</h1>

    <h2>Safe Search and Replace on Database with Serialized Data v3.1.0</h2>

    <p>This developer/sysadmin tool carries out search/replace functions on MySQL DBs and can handle serialised
        PHP Arrays and Objects.</p>

    <p><strong class="red">WARNINGS!</strong>
        Ensure data is backed up.
        We take no responsibility for any damage caused by this script or its misuse.
        DB Connection Settings are auto-filled when WordPress or Drupal is detected but can be confused by
        commented out settings so CHECK!
        There is NO UNDO!
        Be careful running this script on a production server.</p>

    <h3>Don't Forget to Remove Me!</h3>

    <p>Delete this utility from your
        server after use by clicking the 'delete me' button. It represents a major security threat to your
        database if
        maliciously used.</p>

    <p>If you have feedback or want to contribute to this script click the delete button to find out how.</p>

    <p><em>We don't put links on the search replace UI itself to avoid seeing URLs for the script in our access
            logs.</em></p>

    <h3>Again, use Of This Script Is Entirely At Your Own Risk</h3>

    <p>The easiest and safest way to use this script is to copy your site's files and DB to a new location.
        You then, if required, fix up your .htaccess and wp-config.php appropriately. Once
        done, run this script, select your tables (in most cases all of them) and then
        enter the search replace strings. You can press back in your browser to do
        this several times, as may be required in some cases.</p>

</section>

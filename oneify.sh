#!/bin/bash
#
# Oneify Search Replace DB.
#

echo "Oneify favicon ..."
sed -i -e "/<meta charset/ {a <link rel=\"icon\" \"data:image/x-icon;base64,$(base64 -w 0 favicon.ico)\">" -e "}" \
    templates/html.php

echo "Oneify images ..."
sed -i -e "/^\(.*\)url(..\/img\/branding\.png)\(.*\)$/ {H;s//\1/p;i url('data:image/png;base64,$(base64 -w 0 assets/img/branding.png)')" \
    -e "g;s//\2/}" \
    assets/css/style.css
sed -i -e "/^\(.*\)url(..\/img\/rotate\.png)\(.*\)$/ {H;s//\1/p;i url('data:image/png;base64,$(base64 -w 0 assets/img/rotate.png)')" \
    -e "g;s//\2/}" \
    assets/css/style.css

echo "Oneify CSS ..."
sed -i -e "/<link\s*href=\"assets\/css\/style\.css\"/ {a <style type=\"text/css\">" \
    -e "r assets/css/style.css" -e "a </style>" -e "d}" \
    templates/html.php

echo "Oneify JS ..."
sed -i -e "/<script\s*src=\"assets\/js\/scripts\.js\"/ {a <script>" \
    -e "r assets/js/scripts.js" -e "a </script>" -e "d}" \
    templates/html.php

# Oneify PHP includes
PHP_SCRIPTS="src/ui.php
index.php"
while read -r PHP; do

    # Get list of included files
    INCLUDES="$(sed -n -e "s/\b\(include\|require\)\(_once\)\?\s*(\?\s*['\"]\(\S\+\)['\"]\s*)\?\s*;/@@\3@@/p" "$PHP")" #"
    if [ -z "$INCLUDES" ]; then
        continue
    fi
    # Add special markers
    sed -i -e "s/\b\(include\|require\)\(_once\)\?\s*(\?\s*['\"]\(\S\+\)['\"]\s*)\?\s*;/@@\3@@/" "$PHP"

    while read -r INC; do
        if ! [ -f "${INC//@@/}" ]; then
            continue
        fi

        # Replace included filename with its content
        echo "Including ${INC//@@/} in ${PHP} ..."
        # Test whether PHP need reopening
        PHP_REOPEN=""
        if { cat "${INC//@@/}"; echo "<?php"; } | php --syntax-check &> /dev/null; then
            PHP_REOPEN="a <?php"
        fi

        sed -i -e "/${INC//\//\\/}/ {a // ${INC//@@/}" -e "a ?>" \
            -e "r ${INC//@@/}" -e "$PHP_REOPEN" -e "d}" "$PHP"
    done <<< "$INCLUDES"

done <<< "$PHP_SCRIPTS"

# Double check result
php --syntax-check index.php

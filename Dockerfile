FROM php:7.4-cli

RUN docker-php-ext-install mysqli 
RUN docker-php-ext-enable mysqli

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"


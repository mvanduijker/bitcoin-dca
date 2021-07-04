##################################################################################################################
# Base Stage
##################################################################################################################
FROM php:7.4-cli-alpine3.12 as base_image

RUN apk --no-cache update \
    && apk --no-cache add gmp-dev python3 py3-pip \
    && docker-php-ext-install -j$(nproc) gmp bcmath

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

COPY . /app/

WORKDIR /app/

RUN composer install \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --classmap-authoritative \
    --no-ansi \
    --no-dev

RUN composer require bitwasp/bitcoin:^1.0 \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --classmap-authoritative \
    --no-ansi || echo "skipping bitwasp/bitcoin, failed system requirements"

WORKDIR /app/resources/xpub_derive

RUN pip3 install --no-cache -r requirements.txt

WORKDIR /app/

##################################################################################################################
# Test Stage
##################################################################################################################
FROM base_image as test

WORKDIR /app/

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --from=vendor /usr/bin/composer /usr/bin/composer

# php code coverage
RUN apk --no-cache update \
    && apk --no-cache add autoconf g++ make \
    && pecl install pcov \
    && docker-php-ext-enable pcov

# run the test script(s) from composer, this validates the application before allowing the build to succeed
# this does make the tests run multiple times, but with different architectures
RUN composer install --no-interaction --no-plugins --no-scripts --prefer-dist --no-ansi --ignore-platform-reqs
RUN vendor/bin/phpunit --testdox --coverage-clover /tmp/tests_coverage.xml --log-junit /tmp/tests_log.xml

##################################################################################################################
# Production Stage
##################################################################################################################
FROM base_image as production_build

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

# compile the container for performance reasons
RUN /app/bin/bitcoin-dca

ENTRYPOINT ["docker-entrypoint"]

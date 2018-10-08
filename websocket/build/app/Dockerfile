FROM php:cli-alpine3.7
MAINTAINER phuongb <ghetrung1@gmail.com>

RUN apk add --no-cache bash

RUN	set -x \
&&	apk add --no-cache --virtual .build-deps \
	postgresql-dev \
	sqlite-dev \
&&	docker-php-ext-install bcmath pdo_mysql pdo_sqlite \
&&	apk del .build-deps \
&&  apk  --update add autoconf make gcc musl-dev python3-dev libffi-dev openssl-dev \
&&  pecl channel-update pecl.php.net \
&&  pecl install trader \
&&  docker-php-ext-enable trader

RUN echo "memory_limit = 512 M" >> /usr/local/etc/php/php.ini \
&& echo "max_execution_time = 0"  >> /usr/local/etc/php/php.ini

WORKDIR /app

EXPOSE 81

CMD ["php", "-S", "0.0.0.0:80", "-t", "/app/public"]

# Cron job. Link https://www.ekito.fr/people/run-a-cron-job-with-docker/
# Add crontab file in the cron directory
ADD crontab /etc/cron.d/hello-cron

# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/hello-cron

# Create the log file to be able to run tail
RUN touch /var/log/cron.log

RUN touch /app/test1.php
RUN echo "phuong" >> /app/test1.php

RUN echo "phuong" >> /app/test.php

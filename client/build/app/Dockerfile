FROM php:cli-alpine3.7
MAINTAINER phuongb <ghetrung1@gmail.com>

RUN apk add --no-cache bash

RUN	set -x \
&&	apk add --no-cache --virtual .build-deps \
	postgresql-dev \
	sqlite-dev \
&&	docker-php-ext-install bcmath pdo_mysql pdo_sqlite \
&&	apk del .build-deps


WORKDIR /app

EXPOSE 81

CMD ["php", "-S", "0.0.0.0:80", "-t", "/app/public"]

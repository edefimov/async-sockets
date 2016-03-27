FROM debian:jessie

RUN apt-get update && apt-get install -y apache2-dev apache2-mpm-worker

# phpize deps
RUN apt-get update && apt-get install -y \
		autoconf \
		file \
		g++ \
		gcc \
		libc-dev \
		make \
		pkg-config \
		re2c \
	--no-install-recommends && rm -r /var/lib/apt/lists/*

# persistent / runtime deps
RUN apt-get update && apt-get install -y \
		ca-certificates \
		curl \
		libedit2 \
		libsqlite3-0 \
		libxml2 \
	--no-install-recommends && rm -r /var/lib/apt/lists/*

ENV PHP_INI_DIR /usr/local/etc/php
RUN mkdir -p $PHP_INI_DIR/conf.d

ENV PHP_EXTRA_CONFIGURE_ARGS --enable-maintainer-zts --with-apxs2

ENV GPG_KEYS 0BD78B5F97500D450838F95DFE857D9A90D90EC1 6E4F6AB321FDC07F2C332E3AC2BF0BC433CFC8B3

ENV PHP_VERSION 5.6.19
ENV PHP_FILENAME php-5.6.19.tar.xz
ENV PHP_SHA256 bb32337f93a00b71789f116bddafa8848139120e7fb6f4f98a84f52dbcb8329f

ENV PHP_XDEBUG_VERSION 2.4.0
ENV PHP_XDEBUG_FILENAME xdebug.${PHP_XDEBUG_VERSION}.tgz

RUN set -xe \
	&& buildDeps=" \
		$PHP_EXTRA_BUILD_DEPS \
		libcurl4-openssl-dev \
		libedit-dev \
		libsqlite3-dev \
		libssl-dev \
		libxml2-dev \
		xz-utils \
	" \
	&& apt-get update && apt-get install -y $buildDeps --no-install-recommends && rm -rf /var/lib/apt/lists/* \
	&& curl -fSL "http://php.net/get/$PHP_FILENAME/from/this/mirror" -o "$PHP_FILENAME" \
	&& echo "$PHP_SHA256 *$PHP_FILENAME" | sha256sum -c - \
	&& curl -fSL "http://php.net/get/$PHP_FILENAME.asc/from/this/mirror" -o "$PHP_FILENAME.asc" \
	&& export GNUPGHOME="$(mktemp -d)" \
	&& for key in $GPG_KEYS; do \
		gpg --keyserver ha.pool.sks-keyservers.net --recv-keys "$key"; \
	done \
	&& gpg --batch --verify "$PHP_FILENAME.asc" "$PHP_FILENAME" \
	&& rm -r "$GNUPGHOME" "$PHP_FILENAME.asc" \
	&& mkdir -p /usr/src/php \
	&& tar -xf "$PHP_FILENAME" -C /usr/src/php --strip-components=1 \
	&& rm "$PHP_FILENAME" \
	&& cd /usr/src/php \
	&& ./configure \
		--with-config-file-path="$PHP_INI_DIR" \
		--with-config-file-scan-dir="$PHP_INI_DIR/conf.d" \
		$PHP_EXTRA_CONFIGURE_ARGS \
		--disable-cgi \
# --enable-mysqlnd is included here because it's harder to compile after the fact than extensions are (since it's a plugin for several extensions, not an extension in itself)
		--enable-mysqlnd \
		--with-curl \
		--with-libedit \
		--with-openssl \
		--with-zlib \
	&& make -j"$(nproc)" \
	&& make install \
	&& { find /usr/local/bin /usr/local/sbin -type f -executable -exec strip --strip-all '{}' + || true; } \
	&& make clean \
	&& mkdir -p /usr/src/php-xdebug \
	&& cd /usr/src/php-xdebug \
	&& curl -fSL "https://xdebug.org/files/xdebug-${PHP_XDEBUG_VERSION}.tgz" -o "${PHP_XDEBUG_FILENAME}" \
	&& tar -xf "${PHP_XDEBUG_FILENAME}" -C /usr/src/php-xdebug --strip-components=1 \
    && rm "${PHP_XDEBUG_FILENAME}" \
    && /usr/local/bin/phpize \
    && ./configure --prefix=/usr \
       			--datadir=/data \
       			--docdir=/docs \
       			--enable-xdebug \
       			--with-php-config=/usr/local/bin/php-config \
    && make -j"$(nproc)" \
    && make install \
    && make clean \
    && cd ~ \
    && rm -rf /usr/src/php-xdebug \
    && rm -rf /usr/src/php \
	&& apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false -o APT::AutoRemove::SuggestsImportant=false $buildDeps

ENV APACHE_LOCK_DIR=/var/run
ENV APACHE_PID_FILE=${APACHE_LOCK_DIR}/apache2.pid
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV APACHE_LOG_DIR=/var/log

EXPOSE 80

ENV ASYNC_SOCKETS_ROOT="/async-sockets"

VOLUME ["${ASYNC_SOCKETS_ROOT}/src", "${ASYNC_SOCKETS_ROOT}/vendor", "${ASYNC_SOCKETS_ROOT}/demos"]
COPY ["conf/*", "/etc/apache2/conf-enabled/"]
COPY ["apache2.conf", "/etc/apache2/apache2.conf"]
COPY ["www/*", "/var/www/html/"]
COPY ["php.conf/*.ini", "${PHP_INI_DIR}/conf.d/"]

WORKDIR /var/www/html

##CMD ["ls", "-al", "/async-sockets/demos"]

CMD ["apache2", "-DFOREGROUND"]
#CMD ["find", "/", "-name", "libphp5.so"]

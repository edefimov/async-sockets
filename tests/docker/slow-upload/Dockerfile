FROM php:7.1-cli

ENV ASYNC_SOCKETS_ROOT="/async-sockets"

VOLUME ["${ASYNC_SOCKETS_ROOT}/src", "${ASYNC_SOCKETS_ROOT}/vendor", "${ASYNC_SOCKETS_ROOT}/app"]
COPY "./app" "${ASYNC_SOCKETS_ROOT}/app"

WORKDIR "${ASYNC_SOCKETS_ROOT}"

CMD ["php", "app/console.php"]

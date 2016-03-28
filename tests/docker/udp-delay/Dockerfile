FROM php:5.6-cli

ENV ASYNC_SOCKETS_ROOT="/async-sockets"

VOLUME ["${ASYNC_SOCKETS_ROOT}/src", "${ASYNC_SOCKETS_ROOT}/vendor", "${ASYNC_SOCKETS_ROOT}/app"]
COPY "./app" "${ASYNC_SOCKETS_ROOT}/app"

WORKDIR "${ASYNC_SOCKETS_ROOT}"

EXPOSE 10031
CMD ["php", "app/console.php", "--host=0.0.0.0"]

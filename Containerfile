FROM alpine:3.22.1

RUN apk update && \
    apk add --no-cache \
      s6-overlay \
      caddy \
      php84 \
      php84-fpm \
      php84-openssl \
      php84-mysqli \
      php84-sqlite3 \
      php84-pgsql \
      php84-json \
      php84-session \
      php84-tokenizer \
      php84-curl \
      php84-phar \
      php84-xml \
      php84-mbstring && \
    rm -rf /var/cache/apk/*

RUN mkdir -p /run/php \
         /srv/web/inc/config && \
    chmod 0777 /srv/web/inc/config

COPY web       /srv/web
COPY container/Caddyfile /etc/caddy/Caddyfile
COPY container/php-fpm-pool.conf /etc/php84/php-fpm.d/www.conf

RUN date +%s > /srv/web/inc/config/timestamp.txt

RUN mkdir -p /etc/services.d/php-fpm /etc/services.d/caddy && \
    printf '%s\n' '#!/usr/bin/with-contenv sh' \
                  'exec php-fpm84 --nodaemonize -R' \
      > /etc/services.d/php-fpm/run && \
    chmod +x /etc/services.d/php-fpm/run && \
    printf '%s\n' '#!/usr/bin/env sh' \
                  'exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile' \
      > /etc/services.d/caddy/run && \
    chmod +x /etc/services.d/caddy/run

EXPOSE 8080

ENTRYPOINT ["/init"]

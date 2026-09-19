FROM alpine:3.24.2

RUN apk update && \
    apk add --no-cache \
      s6-overlay \
      caddy \
      php85 \
      php85-fpm \
      php85-openssl \
      php85-mysqli \
      php85-sqlite3 \
      php85-pgsql \
      php85-json \
      php85-session \
      php85-tokenizer \
      php85-curl \
      php85-phar \
      php85-xml \
      php85-mbstring && \
    rm -rf /var/cache/apk/*

RUN mkdir -p /run/php \
         /srv/web/inc/config && \
    chmod 0777 /srv/web/inc/config

COPY web       /srv/web
COPY container/Caddyfile /etc/caddy/Caddyfile
COPY container/php-fpm-pool.conf /etc/php85/php-fpm.d/www.conf

RUN date +%s > /srv/web/inc/config/timestamp.txt

RUN mkdir -p /etc/services.d/php-fpm /etc/services.d/caddy && \
    printf '%s\n' '#!/usr/bin/with-contenv sh' \
                  'exec php-fpm85 --nodaemonize -R' \
      > /etc/services.d/php-fpm/run && \
    chmod +x /etc/services.d/php-fpm/run && \
    printf '%s\n' '#!/usr/bin/env sh' \
                  'exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile' \
      > /etc/services.d/caddy/run && \
    chmod +x /etc/services.d/caddy/run

EXPOSE 8080

ENTRYPOINT ["/init"]

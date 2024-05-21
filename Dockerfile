FROM docker.io/bravecheng/php-nginx-sqlite:latest

USER nobody
RUN mkdir -p /var/www/html/inc/config
RUN chown nobody:nobody /var/www/html/inc/config
VOLUME /var/www/html/inc/config

USER root
WORKDIR /var/www
COPY cli cli
COPY web html

USER nobody

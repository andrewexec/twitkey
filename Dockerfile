FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    sqlite sqlite-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev \
    caddy \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql gd

WORKDIR /var/www/twitkey
COPY . .

RUN mkdir -p /data/avatars /data/uploads /data/cache && chmod -R 777 /data

EXPOSE 80 443
CMD ["sh", "-c", "php-fpm -D && caddy run --config /var/www/twitkey/Caddyfile --adapter caddyfile"]

# --- Stage 1: build frontend assets ---
FROM node:20 AS node-build

WORKDIR /var/www

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build

# --- Stage 2: PHP app ---
FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

# Bring in the compiled frontend assets from the node-build stage
COPY --from=node-build /var/www/public/build ./public/build

RUN chmod +x start.sh

EXPOSE 10000

CMD ["./start.sh"]
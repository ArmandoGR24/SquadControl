# ETAPA 1: Compilación de Frontend (Node 20 + PHP)
# Usamos Alpine para poder instalar PHP y Node juntos fácilmente
FROM alpine:3.20 AS build-stage

WORKDIR /app

# Instalar PHP 8.3 y Node.js 22 (requerido por Vite 7)
RUN apk add --no-cache \
    php83 \
    php83-phar \
    php83-mbstring \
    php83-openssl \
    nodejs \
    npm \
    git

# Crear un enlace simbólico para que el comando 'php' funcione
RUN ln -s /usr/bin/php83 /usr/bin/php

COPY . .

# Instalar dependencias y compilar
# Ahora 'php artisan' funcionará porque instalamos PHP arriba
RUN npm install && npm run build


# ETAPA 2: Servidor de Producción (PHP-FPM)
FROM php:8.2-fpm-alpine
WORKDIR /var/www/html

# Instalar extensiones de PHP necesarias para Laravel
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    git \
    unzip \
    oniguruma-dev \
    libxml2-dev

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Copiar el código del proyecto y los assets compilados de la Etapa 1
COPY --from=build-stage /app /var/www/html

# Permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
# ETAPA 1: Compilar assets (Necesitamos PHP + Node por los plugins de Vite)
FROM php:8.2-alpine AS build-stage

# Instalar Node.js, npm y dependencias necesarias para compilar
RUN apk add --no-cache nodejs npm libpng-dev libjpeg-turbo-dev freetype-dev zip git unzip

WORKDIR /app
COPY . .

# Instalar dependencias y compilar
# (Ahora 'php artisan' funcionará porque estamos en una base de PHP)
RUN npm install && npm run build

# ETAPA 2: Servidor de Aplicación Final
FROM php:8.2-fpm-alpine
WORKDIR /var/www/html

# Instalar extensiones de PHP para producción
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

# Copiar el código y los assets ya compilados
COPY --from=build-stage /app /var/www/html

# Permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
# syntax=docker/dockerfile:1

# ---- Composer dependencies (PHPMailer only; no dev packages) ---------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction

# ---- Runtime: the same Apache + .htaccess setup as XAMPP -------------------
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers expires deflate \
    && echo "ServerTokens Prod\nServerSignature Off" > /etc/apache2/conf-available/security-hardening.conf \
    && a2enconf security-hardening

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-portfolio.ini"

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN mkdir -p storage/cache storage/logs storage/cv storage/content public/assets/img/projects \
    && chown -R www-data:www-data storage public/assets/img/projects

HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://localhost/healthz || exit 1

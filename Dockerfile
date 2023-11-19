FROM php:8.2-cli
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions mysqli sockets
COPY . /app
WORKDIR /app
# Install composer and install dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-interaction --no-progress --optimize-autoloader
EXPOSE 8082
CMD ["php", "/app/standalone-server.php"]
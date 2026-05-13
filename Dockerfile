FROM php:8.2-apache

# Install PDO MySQL extension — this fixes "could not find driver"
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite for .htaccess URL routing
RUN a2enmod rewrite

# Configure Apache to allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Copy all API files to the web root
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]

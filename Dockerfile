FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable mod_rewrite for .htaccess routing
RUN a2enmod rewrite

# Allow .htaccess overrides using a separate config file (avoids MPM conflict)
RUN { \
    echo '<Directory /var/www/html>'; \
    echo '    AllowOverride All'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
} > /etc/apache2/conf-available/bibliotheca.conf \
&& a2enconf bibliotheca

# Copy all API files to Apache web root
COPY . /var/www/html/

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

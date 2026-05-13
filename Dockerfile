FROM php:8.2-fpm-alpine

# Install PDO MySQL and Nginx
RUN docker-php-ext-install pdo pdo_mysql \
    && apk add --no-cache nginx

# Nginx config — handles URL rewriting (replaces .htaccess)
RUN mkdir -p /etc/nginx/http.d && \
    printf 'server {\n    listen 80;\n    root /var/www/html;\n    index index.php;\n\n    location / {\n        try_files $uri $uri/ /index.php?$query_string;\n    }\n\n    location ~ \\.php$ {\n        fastcgi_pass 127.0.0.1:9000;\n        fastcgi_index index.php;\n        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n        include fastcgi_params;\n    }\n\n    location ~ /\\. { deny all; }\n}\n' > /etc/nginx/http.d/default.conf

# Copy API files to web root
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Startup: run PHP-FPM in background, then Nginx in foreground
RUN printf '#!/bin/sh\nphp-fpm -D\nnginx -g "daemon off;"\n' > /start.sh && chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]

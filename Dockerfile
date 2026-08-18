FROM php:8.2-apache

# Enable mysqli extension (required by Database.php)
RUN docker-php-ext-install mysqli

# Enable rewrite module (MPM is already correctly configured by the
# base image itself — it runs `a2dismod mpm_event && a2enmod mpm_prefork`
# during its own build, so we must NOT touch MPM modules again here).
RUN a2enmod rewrite

# Copy project files into the Apache web root
COPY . /var/www/html/

# Give the web server write access to the uploads folder
RUN chown -R www-data:www-data /var/www/html/Frontend/uploads \
    && chmod -R 755 /var/www/html/Frontend/uploads

# Use a placeholder port in the Apache config; the entrypoint script
# swaps it for Railway's real $PORT value at container start time
# (Railway only assigns PORT at runtime, not at build time).
RUN sed -i 's/80/__PORT__/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]
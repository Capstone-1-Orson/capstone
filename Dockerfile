FROM php:8.2-apache

# Enable mysqli extension (required by Database.php)
RUN docker-php-ext-install mysqli

# Enable Apache mod_rewrite (harmless even if unused)
RUN a2enmod rewrite

# Copy project files into the Apache web root
COPY . /var/www/html/

# Give the web server write access to the uploads folder
RUN chown -R www-data:www-data /var/www/html/Frontend/uploads \
    && chmod -R 755 /var/www/html/Frontend/uploads

# Railway provides the PORT env var; Apache needs to listen on it
ENV PORT=8080
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 8080

CMD ["apache2-foreground"]
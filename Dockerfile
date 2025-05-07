# Use official PHP image with Apache
FROM php:8.1-apache

# Copy all files to the web root
COPY . /var/www/html/

# Give write permission to the apache user (optional, if using logging)
RUN chown -R www-data:www-data /var/www/html

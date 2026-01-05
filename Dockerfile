FROM php:8.2-apache

# Enable necessary Apache modules
RUN a2enmod rewrite

# Install MySQLi extension
RUN docker-php-ext-install mysqli

# Set working directory
WORKDIR /var/www/html

# Copy files from public folder into container
COPY public/ .

# Expose port 80
EXPOSE 80

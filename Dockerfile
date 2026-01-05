FROM php:8.2-apache

# Enable necessary Apache modules
RUN a2enmod rewrite

# Install MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set working directory
WORKDIR /var/www/html

# Copy files from public folder into container
COPY public/ .

# Expose port 80
EXPOSE 80

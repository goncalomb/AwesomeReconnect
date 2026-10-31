FROM php:8.5-apache

EXPOSE 80
RUN a2enmod rewrite
COPY ./www /var/www/html

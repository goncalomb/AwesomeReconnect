FROM php:7.2-apache

EXPOSE 80
RUN a2enmod rewrite
COPY ./www /var/www/html

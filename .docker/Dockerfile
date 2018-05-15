FROM php:7.1.8-apache

MAINTAINER Vladislav!

COPY . /srv/app
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

COPY .docker/httpd.conf /etc/apache2/httpd.conf
RUN   echo "Include /etc/apache2/httpd.conf" >> /etc/apache2/apache2.conf

WORKDIR /srv/app


RUN docker-php-ext-install mbstring pdo pdo_mysql \
&& chown -R www-data:www-data /srv/app  && a2enmod rewrite && a2enmod proxy && a2enmod proxy_http && a2enmod proxy_wstunnel
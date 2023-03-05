FROM php:8-apache

RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN a2enmod rewrite headers

RUN apt-get update && apt-get install -y msmtp && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql

COPY ./msmtprc /etc/msmtprc
RUN chmod 640 /etc/msmtprc
RUN touch /var/log/msmtp.log
RUN chown root:msmtp /etc/msmtprc
RUN chown root:msmtp /var/log/msmtp.log
RUN echo "sendmail_path = /usr/bin/msmtp -t" >> /usr/local/etc/php/conf.d/php-sendmail.ini

COPY init.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/init.sh

COPY . /var/www/html

ENV PUID=2000
ENV PGID=2000
CMD ["/usr/local/bin/init.sh"]

FROM ubuntu:16.04
MAINTAINER Fer Uria <fauria@gmail.com>
LABEL Description="XYZ" \
	License="Apache License 2.0" \
	Usage="XYZ" \
	Version="1.0"

RUN apt-get update
RUN apt-get upgrade -y

RUN apt-get install -y php5.6 php5.6-mysql apache2 libapache2-mod-php5.6 wget unzip

RUN wget https://github.com/fauria/Search-Replace-DB/archive/master.zip -P /var/www
RUN rm -rf /var/www/html
RUN unzip /var/www/master.zip -d /var/www
RUN mv /var/www/Search-Replace-DB-master /var/www/html
RUN chown -R www-data:www-data /var/www/html

VOLUME /var/www/html
EXPOSE 80

ENTRYPOINT ["/usr/sbin/apachectl", "-k", "start"]
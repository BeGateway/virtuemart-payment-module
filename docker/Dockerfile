FROM trafex/alpine-nginx-php7:latest

USER root

RUN apk --no-cache add php7-simplexml php7-zip postfix awall su-exec

ARG virtuemart_version

ADD src/$virtuemart_version.zip /joomla-vm.zip
ADD scripts/start.sh /start.sh
ADD config/configuration.php /var/www/html/
COPY config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R nobody.nobody /var/www

VOLUME ["/var/www"]

CMD ["/bin/sh", "/start.sh"]

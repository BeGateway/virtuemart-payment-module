#!/bin/bash

# Install Joomla + VM
if [ ! -e /var/www/html/.first-run-complete ]; then
  rm -f /var/www/html/*
  unzip /joomla-vm.zip -d /var/www/html

  chown -R nobody.nobody /var/www
fi

addgroup nobody tty
chmod o+w /dev/pts/0

su-exec nobody /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

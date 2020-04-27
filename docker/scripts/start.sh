#!/bin/bash

# Install Joomla + VM
if [ ! -e /var/www/html/.first-run-complete ]; then
  unzip /joomla-vm.zip -d /var/www/html
  echo "Do not remove this file." > /var/www/html/.first-run-complete

  chown -R nobody.nobody /var/www
fi

addgroup nobody tty
chmod o+w /dev/pts/0

su-exec nobody /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

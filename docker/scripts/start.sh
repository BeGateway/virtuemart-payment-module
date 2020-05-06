#!/bin/bash

# Install Joomla + VM
if [ ! -e /var/www/html/.first-run-complete ]; then
  unzip /joomla-vm.zip -d /var/www/html -o
  echo "Do not remove this file." > /var/www/html/.first-run-complete
  rm -rf /var/www/html/installation

  mkdir -p /var/www/html/images/virtuemart
  mkdir -p /var/www/html/images/virtuemart/category
  mkdir -p /var/www/html/images/virtuemart/category/resized
  mkdir -p /var/www/html/images/virtuemart/product
  mkdir -p /var/www/html/images/virtuemart/product/resized
  mkdir -p /var/www/html/images/virtuemart/manufacturer
  mkdir -p /var/www/html/images/virtuemart/manufacturer/resized
  
  chown -R nobody.nobody /var/www
fi

addgroup nobody tty
chmod o+w /dev/pts/0

su-exec nobody /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

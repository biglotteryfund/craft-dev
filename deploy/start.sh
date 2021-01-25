#!/bin/bash
set -e
set -x
#################################################
# ApplicationStart script
#################################################

#################################################
# Permissions
#################################################
# Override default owner set in deploy artefact to web user
# Because php-fpm installs some things as apache:apache
sudo chown -R nginx:nginx /var/www/craft/
cd /var/www/craft
sudo chmod -R 777 composer.json composer.lock storage vendor web/cpresources config config/license.key

# Run any migrations to the CMS and sync the project config
sudo /bin/bash -c 'cd /var/www/craft/ && ./craft migrate/all && ./craft project-config/sync' nginx

# Set permissions again
# @TODO work out why we need this twice
# (craft migration runs as php-fpm user, apache, which changes ownership?)
sudo chown -R nginx:nginx /var/www/craft/
cd /var/www/craft && sudo chmod -R 777 composer.json composer.lock storage vendor web/cpresources config config/license.key
set +x
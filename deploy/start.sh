#!/bin/bash
set -e
#################################################
# ApplicationStart script
#################################################

# Run any migrations to the CMS and sync the project config
su -s /bin/bash -c 'cd /var/www/craft/ && ./craft migrate/all && ./craft project-config/sync' nginx

#################################################
# Permissions
#################################################
# Override default owner set in deploy artefact to web user
# Because php-fpm installs some things as apache:apache
chown -R nginx:nginx /var/www/craft/
cd /var/www/craft
chmod -R 777 composer.json composer.lock storage vendor web/cpresources config
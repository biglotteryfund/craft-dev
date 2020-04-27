#!/bin/bash
set -e
#################################################
# ApplicationStart script
#################################################

# Run any migrations to the CMS and sync the project config
su -s /bin/bash -c 'cd /var/www/craft/ && ./craft migrate/all && ./craft project-config/sync' nginx
#!/bin/bash
set -e
#################################################
# ApplicationStart script
#################################################

# Run any migrations to the CMS and sync the project config
su -s /bin/bash -c './craft migrate/all && ./craft project-config/sync' webapp
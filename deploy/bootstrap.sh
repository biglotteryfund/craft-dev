#!/bin/bash
set -e

#################################################
# AfterInstall / Bootstrap script
#################################################
# Run during the "AfterInstall" CodeDeploy phase

#################################################
# Permissions
#################################################
# Override default owner set in deploy artefact to web user

chown -R www-data:www-data /var/www/craft/

#################################################
# App environment
#################################################
# Set app environment based on CodeDeploy group

TEST_FLEET="Test_Fleet";
TEST_IN_PLACE="Test_In_Place";
LIVE_FLEET="Live_Fleet";
LIVE_IN_PLACE="Live_In_Place";

# Check if the deployment group name contains one of the above strings
APP_ENV="development"
if [[ $DEPLOYMENT_GROUP_NAME =~ $TEST_FLEET ]] ||
   [[ $DEPLOYMENT_GROUP_NAME =~ $TEST_IN_PLACE ]];
then
    APP_ENV="test"
elif [[ $DEPLOYMENT_GROUP_NAME =~ $LIVE_FLEET ]] ||
     [[ $DEPLOYMENT_GROUP_NAME =~ $LIVE_IN_PLACE ]];
then
    APP_ENV="production"
fi

#################################################
# App secrets
#################################################
# Written to /etc/blf/parameters.json to
# be later loaded by the app.

/var/www/craft/bin/get-secrets --environment=$APP_ENV

#copy license file
# Craft license file
#  /etc/blf/config/license.key:
#    mode: "000644"
#    owner: webapp
#    group: webapp
#    authentication: "S3Auth"
#    source: https://s3-eu-west-1.amazonaws.com/blf-craft-license/license.key
#command: "mv /etc/blf/config/license.key config/license.key"


#################################################
# Configure NGINX
#################################################

# Copy nginx config files to correct place
nginx_config=/var/www/craft/deploy/nginx.conf
server_config=/var/www/craft/deploy/server.conf

# Configure nginx

# copy uploads.ini
#files:
#  "/etc/php.d/uploads.ini" :
#    mode: "000644"
#    owner: root
#    group: root

rm -f /etc/nginx/sites-enabled/default
cp $nginx_config /etc/nginx/conf.d
cp $server_config /etc/nginx/sites-enabled

service nginx restart
service php-fpm restart


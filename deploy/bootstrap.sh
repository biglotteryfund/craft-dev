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

chown -R nginx:nginx /var/www/craft/
cd /var/www/craft
chmod -R 777 composer.json composer.lock storage vendor web/cpresources


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

# Define placeholder string to be replaced
# with dynamic $APP_ENV value.
APP_ENV_PLACEHOLDER="APP_ENV"

#################################################
# App secrets
#################################################
# Written to /etc/blf/parameters.json to
# be later loaded by the app.

/var/www/craft/bin/get-secrets --environment=$APP_ENV

# Craft license file
license_dest=config/license.key
aws s3 cp s3://blf-craft-license/license.key $license_dest
chmod 744 $license_dest
chown root:root $license_dest

#################################################
# Configure PHP
#################################################

cp /var/www/craft/deploy/cms.ini /etc/php.d
chmod 644 /etc/php.d/cms.ini
chown root:root /etc/php.d/cms.ini

#################################################
# Configure CloudWatch agent
#################################################

# Configure/start Cloudwatch agent with config file
cloudwatch_config_src=/var/www/craft/deploy/cloudwatch-agent.json
sed -i "s|$APP_ENV_PLACEHOLDER|$APP_ENV|g" $cloudwatch_config_src

cloudwatch_config_dest=/opt/aws/amazon-cloudwatch-agent/bin/cms.json
cp $cloudwatch_config_src $cloudwatch_config_dest
amazon-cloudwatch-agent-ctl -a fetch-config -c file:$cloudwatch_config_dest -s
amazon-cloudwatch-agent-ctl -a start

#################################################
# Configure NGINX
#################################################
# Copy nginx config files to correct place

nginx_config=/var/www/craft/deploy/nginx.conf
server_config=/var/www/craft/deploy/server.conf

cp $nginx_config /etc/nginx/nginx.conf

mkdir -p /etc/nginx/sites-enabled
cp $server_config /etc/nginx/sites-enabled

# Create nginx cache directory
nginx_cache=/var/tmp/nginx-cache
mkdir -p $nginx_cache
chmod -R 777 $nginx_cache
chown -R nginx:nginx $nginx_cache

service nginx restart
service php-fpm restart


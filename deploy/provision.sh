#!/bin/bash
set -e

#################################################
# AfterInstall / Provision script
#################################################
# Run during the "AfterInstall" CodeDeploy phase

#################################################
# Install NGINX and PHP
#################################################
yum update -y

amazon-linux-extras install nginx1.12 php7.3 -y

# todo: Craft prefers ImageMagick to php-gd but struggled to install this on Amazon Linux 2
yum install -y php-mbstring php-dom php-gd php-intl

#################################################
# Install MySQL
#################################################
# We don't use mysql on the server itself (RDS handles this)
# but we need access to the `mysqldump` function for database backups.
wget https://dev.mysql.com/get/mysql57-community-release-el7-11.noarch.rpm
yum localinstall -y mysql57-community-release-el7-11.noarch.rpm
yum install -y mysql-community-server

#################################################
# Install Node.js
#################################################
yum install -y gcc-c++ make
curl -sL https://rpm.nodesource.com/setup_12.x | sudo -E bash -
yum install -y nodejs

#################################################
# Install the AWS CLI
#################################################
# Used to fetch secrets from parameter store
rm -rf awscli-bundle.zip awscli-bundle
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
./aws/install -i /usr/local/aws -b /usr/local/bin/aws

#################################################
# CloudWatch agent
#################################################
# Used for log aggregation and server metrics
# See cloudwatch-agent.json for the config we use
wget https://s3.amazonaws.com/amazoncloudwatch-agent/amazon_linux/amd64/latest/amazon-cloudwatch-agent.rpm
rpm -U ./amazon-cloudwatch-agent.rpm

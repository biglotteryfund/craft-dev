#!/bin/bash
set -e

#################################################
# AfterInstall / Provision script
#################################################
# Run during the "AfterInstall" CodeDeploy phase

#################################################
# Install NGINX and PHP
#################################################
yum update
amazon-linux-extras install nginx1.12 php7.2

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
curl "https://s3.amazonaws.com/aws-cli/awscli-bundle.zip" -o "awscli-bundle.zip"
unzip awscli-bundle.zip
./awscli-bundle/install -i /usr/local/aws -b /usr/local/bin/aws

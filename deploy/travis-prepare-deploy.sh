#!/bin/bash
set -e
#################################################
# Prepare Deploy
#################################################
# This step creates a .zip file which is uploaded to S3
# and used by CodeDeploy as its deploy artefact

# Bundle deploy artefact, excluding any unneeded files
zip -qr latest ./* -x .\*

# Store artefact locally for later use in Travis deploy step
mkdir -p cms_deploy
mv latest.zip cms_deploy/build-"$TRAVIS_BUILD_NUMBER".zip

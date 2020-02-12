#!/bin/bash

# Apply the patch that fixes performing database updates when a module
# introduces a new service that depends on a service from another module. This
# bug prevents the `testUpdateModuleWithServiceDependency()` test from passing.
#
# Ref. https://www.drupal.org/project/drupal/issues/2863986

cd $HOME/drush/sut

# This patch has been applied to 8.7.x and later.
if [ "$1" == "8.6.x" ]; then
  PATCH=https://www.drupal.org/files/issues/2863986-62.patch
  curl -S $PATCH | patch -p1
fi


#!/bin/bash

# Apply the patch that fixes performing database updates when a module
# introduces a new service that depends on a service from another module. This
# bug prevents the `testUpdateModuleWithServiceDependency()` test from passing.
#
# Ref. https://www.drupal.org/project/drupal/issues/2863986

cd $HOME/drush/sut

# There are two versions of the patch, for 8.7.x and for 8.6.x and below.
PATCH=https://www.drupal.org/files/issues/2863986-62.patch

if [ "$1" == "8.7.x" ]; then
  PATCH=https://www.drupal.org/files/issues/2018-11-15/2863986-77-8.7.x.patch
fi

curl -S $PATCH | patch -p1

# Apply patch for schema support in PostreSQL.
PATCH=https://www.drupal.org/files/issues/drupal-1060476-85.patch


curl -S $PATCH | patch -p1


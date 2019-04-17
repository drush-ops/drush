#!/bin/bash

# Apply the patch that fixes performing database updates when a module
# introduces a new service that depends on a service from another module. This
# bug prevents the `testUpdateModuleWithServiceDependency()` test from passing.
#
# Ref. https://www.drupal.org/project/drupal/issues/2863986

cd $HOME/drush/sut

PATCH=https://www.drupal.org/files/issues/2018-11-15/2863986-77-8.7.x.patch

curl -S $PATCH | patch -p1

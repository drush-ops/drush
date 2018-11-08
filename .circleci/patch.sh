#!/bin/bash

# Apply the patch that fixes performing database updates when a module
# introduces a new service that depends on a service from another module. This
# bug prevents the `testUpdateModuleWithServiceDependency()` test from passing.
#
# Ref. https://www.drupal.org/project/drupal/issues/2863986

cd $HOME/drush/sut
curl -S https://www.drupal.org/files/issues/2863986-62.patch | patch -p1

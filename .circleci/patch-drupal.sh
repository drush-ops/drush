#!/usr/bin/env bash

# Apply patch for schema support in PostreSQL.
#
# Ref. https://www.drupal.org/project/drupal/issues/1060476
if [ "$1" == "8.8.x" ]; then
  PATCH=https://www.drupal.org/files/issues/2020-01-07/1060476-105.patch
fi
if [ "$1" == "8.9.x" ]; then
  # TODO: Update when proper patch version would be available.
  PATCH=https://www.drupal.org/files/issues/2020-01-07/1060476-105.patch
fi
if [ "$1" == "9.1.x" ]; then
  # TODO: Update when proper patch version would be available.
  PATCH=https://www.drupal.org/files/issues/2020-01-07/1060476-105.patch
fi

curl -S $PATCH | patch -d sut -p1

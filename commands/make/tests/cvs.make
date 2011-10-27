core = 6.x
api = 2

; Test that an alternative CVSROOT specification works.
projects[drupal][type] = core
projects[drupal][download][type] = cvs
projects[drupal][download][root] = :pserver:anonymous:anonymous@cvs.drupal.org:/cvs/drupal
projects[drupal][download][module] = drupal
projects[drupal][download][revision] = DRUPAL-6-17

; Test that no CVSROOT specification falls back to Drupal contrib.
projects[votingapi][type] = module
projects[votingapi][download][type] = cvs
projects[votingapi][download][module] = contributions/modules/votingapi
projects[votingapi][download][revision] = DRUPAL-6--2-0

; Test that a revision pinned to a date works.
projects[token][type] = module
projects[token][download][type] = cvs
projects[token][download][module] = contributions/modules/token
projects[token][download][revision] = DRUPAL-6--1:2010-02-17

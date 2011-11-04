core = 6.x
api = 2

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

core = 6.x
api = 2

; Test that a specific tag can be pulled.
projects[tao][type] = theme
projects[tao][download][type] = git
projects[tao][download][url] = git://github.com/developmentseed/tao.git
projects[tao][download][tag] = drupal-6--1-9

; Test that a branch can be pulled. We use a super-old "stale" branch in the
; Aegir project that we expect not to change.
projects[hostmaster][type] = profile
projects[hostmaster][download][type] = git
projects[hostmaster][download][url] = git://git.aegirproject.org/hostmaster.git
projects[hostmaster][download][branch] = DRUPAL-5

; Test that a specific revision can be pulled. Note that provision is not
; actually a module.
projects[provision][type] = module
projects[provision][download][type] = git
projects[provision][download][url] = git://git.aegirproject.org/provision.git
projects[provision][download][revision] = 017345defebaa6214a8962522e0e9a94889d0020

; Test projects git defaulting to drupal.org.
projects[drush_make][download][type] = git
projects[drush_make][download][revision] = bc5cd3da42200a23015b1727f2729b70480c91b3

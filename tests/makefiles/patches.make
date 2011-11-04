core = 7.x
api = 2

; Test that patches work
projects[wysiwyg][version] = "2.1"
; http://drupal.org/node/624018#comment-5098162
projects[wysiwyg][patch][] = "http://drupal.org/files/0001-feature.inc-from-624018-211.patch"

; http://drupal.org/node/1152908#comment-5010536
projects[features][version] = "1.0-beta4"
projects[features][patch][] = "http://drupal.org/files/issues/features-drush-backend-invoke-25.patch"

projects[context][version] = "3.0-beta2"
; http://drupal.org/node/1251406#comment-5020012
projects[context][patch][] = "http://drupal.org/files/issues/custom_blocks_arent_editable-make.patch"
; http://drupal.org/node/661094#comment-4735064
projects[context][patch][] = "http://drupal.org/files/issues/661094-context-permissions.patch"

core = 6.x
api = 2

; Test that patches work
projects[token][version] = 1.13
projects[token][patch][] = http://drupal.org/files/issues/new_role_email_action5.patch
projects[token][patch][] = http://drupal.org/files/issues/token_module_array_resolution_587148_01.patch
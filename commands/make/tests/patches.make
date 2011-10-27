core = 6.x
api = 2

; Test that patches work
projects[token][version] = 1.13
projects[token][patch][] = http://drupal.org/files/issues/new_role_email_action5.patch
projects[token][patch][] = http://drupal.org/files/issues/587148-token-check-duplicate-tokens.patch

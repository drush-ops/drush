core = 6.x

; Test an HG repo with revision flag.
projects[test_drupal_module][type] = module
projects[test_drupal_module][download][type] = hg
projects[test_drupal_module][download][url] = http://bitbucket.org/mattfarina/test_drupal_module
projects[test_drupal_module][download][revision] = c45793a9dcc5
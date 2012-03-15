core = "7.x"
api = 2

projects[boxes][version] = "1.0-beta7"

projects[admin_menu][version] = "3.0-rc1"

; Use drupal.org as a nice stable source of libraries.
libraries[drush_make][download][type] = "file"
libraries[drush_make][download][url] = "http://ftp.drupal.org/files/projects/drush_make-6.x-2.3.tar.gz"

; Use drupal.org as a nice stable source of libraries.
libraries[token][download][type] = "file"
libraries[token][download][url] = "http://ftp.drupal.org/files/projects/token-7.x-1.0-rc1.tar.gz"


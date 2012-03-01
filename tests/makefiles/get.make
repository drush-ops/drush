core = 6.x
api = 2

; Tarball file download
libraries[drush_make][download][type] = file
libraries[drush_make][download][url] = http://ftp.drupal.org/files/projects/drush_make-6.x-2.0-beta8.tar.gz
libraries[drush_make][directory_name] = drush_make
libraries[drush_make][destination] = libraries
libraries[drush_make][lock] = Locked

; Single file download
libraries[cufon][download][type] = get
libraries[cufon][download][url] = http://cufon.shoqolate.com/js/cufon-yui.js
libraries[cufon][directory_name] = cufon
libraries[cufon][destination] = libraries

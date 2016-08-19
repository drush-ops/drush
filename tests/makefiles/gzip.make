api = 2
core = 6.x

; getid3 doesn't contain a wrapper folder. All files are in the root of the archive.
libraries[getid3][destination] = libraries
libraries[getid3][download][type] = get
libraries[getid3][download][url] = "https://github.com/JamesHeinrich/getID3/archive/v1.9.8.zip"
libraries[getid3][directory_name] = getid3
; http://drupal.org/node/1336886
libraries[getid3][patch][] = "https://www.drupal.org/files/issues/1336886-11.patch"

api = 2
core = 6.x

; GeSHi-1.0.8.10.tar.gz contains wrapper folder "geshi/".
; This should move that wrapper folder to sites/all/libraries/geshi/ .
libraries[geshi][destination] = libraries
libraries[geshi][download][type] = get
libraries[geshi][download][url] = http://downloads.sourceforge.net/project/geshi/geshi/GeSHi%201.0.8.10/GeSHi-1.0.8.10.tar.gz


; getid3 doesn't contain a wrapper folder. All files are in the root of the archive.
libraries[getid3][destination] = libraries
libraries[getid3][download][type] = get
libraries[getid3][download][url] = "http://downloads.sourceforge.net/project/getid3/getID3%28%29%201.x/1.9.1/getid3-1.9.1-20110810.zip?r=http%3A%2F%2Fsourceforge.net%2Fprojects%2Fgetid3%2Ffiles%2FgetID3%2528%2529%25201.x%2F1.9.1%2F&ts=1320871534"
libraries[getid3][directory_name] = getid3
; http://drupal.org/node/1336886
libraries[getid3][patch][] = http://drupal.org/files/getid3-remove-demos-1.9.1.patch

core = 6.x
api = 2

; bison-1.30.tar.bz2 contains wrapper folder "bison-1.30/".
; This should move that wrapper folder to sites/all/libraries/bison/ .
libraries[bison][destination] = libraries
libraries[bison][download][type] = get
libraries[bison][download][url] = http://ftp.gnu.org/gnu/bison/bison-1.30.tar.bz2

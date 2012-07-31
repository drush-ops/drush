api = 2
core = 6.x

; GeSHi-1.0.8.10.tar.gz contains wrapper folder "geshi/".
; This should move that wrapper folder to sites/all/libraries/geshi/ .
libraries[geshi][destination] = libraries
libraries[geshi][download][type] = get
libraries[geshi][download][url] = http://downloads.sourceforge.net/project/geshi/geshi/GeSHi%201.0.8.10/GeSHi-1.0.8.10.tar.gz

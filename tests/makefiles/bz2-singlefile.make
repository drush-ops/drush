api = 2
core = 6.x

; wkhtmltopdf-0.11.0_rc1-static-amd64.tar.bz2 contains the single file "wkhtmltopdf-amd64".
; This should move that single file to sites/all/libraries/wkhtmltopdf .
libraries[wkhtmltopdf][destination] = libraries
libraries[wkhtmltopdf][download][type] = get
libraries[wkhtmltopdf][download][url] = http://download.gna.org/wkhtmltopdf/obsolete/linux/wkhtmltopdf-0.11.0_rc1-static-amd64.tar.bz2

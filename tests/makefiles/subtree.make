core = 6.x
api = 2

; Tell Drush Make we only want the "fullcalendar-1.5.3/fullcalendar/" folder.
libraries[fullcalendar][download][type] = get
libraries[fullcalendar][download][url] = https://github.com/arshaw/fullcalendar/releases/download/v1.5.3/fullcalendar-1.5.3.zip
libraries[fullcalendar][download][sha1] = c7219b1ddd2b11ccdbf83ebd116872affbc45d7a
libraries[fullcalendar][download][subtree] = fullcalendar-1.5.3/fullcalendar

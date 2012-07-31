core = 6.x
api = 2

; nivo-slider2.7.1.zip contains Mac OS X metadata (a "__MACOSX/" folder) in addition to the desired content in the "nivo-slider/" folder.
; Using the "subtree" directive, we tell Drush Make we only want the "nivo-slider/" folder.
libraries[nivo-slider][download][type] = get
libraries[nivo-slider][download][url] = https://github.com/downloads/gilbitron/Nivo-Slider/nivo-slider2.7.1.zip
libraries[nivo-slider][download][sha1] = bd8e14b82f5b9c6f533a4e1aa26a790cd66c3cb9
libraries[nivo-slider][download][subtree] = nivo-slider

; Tell Drush Make we only want the "fullcalendar-1.5.3/fullcalendar/" folder.
libraries[fullcalendar][download][type] = get
libraries[fullcalendar][download][url] = http://arshaw.com/fullcalendar/downloads/fullcalendar-1.5.3.zip
libraries[fullcalendar][download][sha1] = c7219b1ddd2b11ccdbf83ebd116872affbc45d7a
libraries[fullcalendar][download][subtree] = fullcalendar-1.5.3/fullcalendar

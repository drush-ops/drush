core = 6.x

libraries[jquery_ui][download][type] = get
libraries[jquery_ui][download][url] = http://jquery-ui.googlecode.com/files/jquery.ui-1.6.zip
libraries[jquery_ui][directory_name] = jquery.ui
libraries[jquery_ui][destination] = libraries

libraries[shadowbox][download][type] = post
libraries[shadowbox][download][post_data] = format=tar&adapter=jquery&players[]=img&players[]=iframe&players[]=html&players[]=swf&players[]=flv&players[]=qt&players[]=wmp&language=en&css_support=on
libraries[shadowbox][download][url] = http://www.shadowbox-js.com/download
libraries[shadowbox][download][file_type] = ".tar.gz"
libraries[shadowbox][directory_name] = shadowbox
libraries[shadowbox][destination] = libraries

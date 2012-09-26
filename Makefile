all : drushcompile

drushcompile: 
	hphp -o hphpout -k 1 -t cpp drushcompile.php

cdrush :
	hphp -o hphpout -k 1 -t cpp drush.php

bootstrap:
	hphp -o hphpout -k 1 -t cpp includes/bootstrap.inc


constants :
	hphp -o hphpout -k 1 -t cpp tests/compiler/constants.php
#       phc -c tests/compiler/constants.php 

dt :
	hphp -o hphpout -k 1 -t cpp tests/compiler/dt.php

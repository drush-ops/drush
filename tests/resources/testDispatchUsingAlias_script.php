<?php

$valuesUsingAlias = drush_invoke_process("@dev", "unit-return-argv", array(), array(), array("dispatch-using-alias" => TRUE));
$valuesWithoutAlias = drush_invoke_process("@dev", "unit-return-argv", array(), array(), array());
return array('with' => $valuesUsingAlias['object'], 'without' => $valuesWithoutAlias['object']);


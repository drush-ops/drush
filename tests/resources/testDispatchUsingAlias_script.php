<?php

$valuesUsingAlias = drush_invoke_process("@unish.dev", "unit-return-argv", [], [], ["dispatch-using-alias" => true]);
$valuesWithoutAlias = drush_invoke_process("@unish.dev", "unit-return-argv", [], [], []);
return ['with' => $valuesUsingAlias['object'], 'without' => $valuesWithoutAlias['object']];


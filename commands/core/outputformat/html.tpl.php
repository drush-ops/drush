<html>
<head><title>Drush help</title><style>dt {font-size: 110%; font-weight: bold}</style></head>
<body>
  <h3>Global Options (see `drush topic core-global-options` for the full list)</h3>
  <table><?php
    foreach ($global_options_rows as $key => $row) {
      drush_print('<tr>');
      foreach ($row as $value) {
        drush_print("<td>" . htmlspecialchars($value) . "</td>\n");
      }
      drush_print("</tr>\n");
    } ?>
  </table>
  <h3>Command list</h3>
  <table><?php
    foreach ($input as $key => $command) {
      drush_print("  <tr><td><a href=\"#$key\">$key</a></td><td>" . $command['description'] . "</td></tr>\n");
    } ?>
  </table>
<h3>Command detail</h3>
<dl><?php
      foreach ($input as $key => $command) {
        drush_print("\n<a name=\"$key\"></a><dt>$key</dt><dd><pre>\n");
        drush_core_helpsingle($key);
        drush_print("</pre></dd>\n");
      }
    ?>
</body>
</html>

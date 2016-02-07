<html>
<head><title>Drush help</title><style>dt {font-size: 110%; font-weight: bold}</style></head>
<body>
  <h3>Global Options (see `drush topic core-global-options` for the full list)</h3>
  <table><?php
    foreach ($global_options_rows as $key => $row) {
      print '<tr>';
      foreach ($row as $value) {
        print  "<td>" . htmlspecialchars($value) . "</td>\n";
      }
      print "</tr>\n";
    } ?>
  </table>
  <h3>Command list</h3>
  <table><?php
    foreach ($input as $key => $command) {
      print "  <tr><td><a href=\"#$key\">$key</a></td><td>" . $command['description'] . "</td></tr>\n";
    } ?>
  </table>
<h3>Command detail</h3>
<dl><?php
      foreach ($input as $key => $command) {
        print "\n<a name=\"$key\"></a><dt>$key</dt><dd><pre>\n";
        drush_core_helpsingle($key);
        print "</pre></dd>\n";
      }
    ?>
</body>
</html>

Install/Upgrade a global Drush
---------------
```bash    
# Browse to https://github.com/drush-ops/drush/releases and download the drush.phar attached to the latest 8.x release.

# Test your install.
php drush.phar core-status

# Rename to `drush` instead of `php drush.phar`. Destination can be anywhere on $PATH. 
chmod +x drush.phar
sudo mv drush.phar /usr/local/bin/drush

# Optional. Enrich the bash startup file with completion and aliases.
drush init
```
    
* MAMP users, and anyone wishing to launch a non-default PHP, needs to [edit ~/.bashrc so that the right PHP is in your $PATH](http://stackoverflow.com/questions/4145667/how-to-override-the-path-of-php-to-use-the-mamp-path/10653443#10653443).
* We have documented [alternative ways to install](http://docs.drush.org/en/8.x/install-alternative/), including [Windows](http://docs.drush.org/en/8.x/install-alternative/#windows).
* If you need to pass custom php.ini values, run `php -d foo=bar drush.phar --php-options=foo=bar`
* Your shell now has [useful bash aliases and tab completion for command names, site aliases, options, and arguments](https://raw.githubusercontent.com/drush-ops/drush/8.x/examples/example.bashrc).
* A [drushrc.php](https://raw.githubusercontent.com/drush-ops/drush/8.x/examples/example.drushrc.php) has been copied to ~/.drush above. Customize it to save typing and standardize options for commands.
* Upgrade using this same procedure.

Install a site-local Drush
-----------------
In addition to the global Drush, it is recommended that Drupal 8 sites be [built using Composer, with Drush listed as a dependency](https://github.com/drupal-composer/drupal-project).

1. When you run `drush`, the global Drush is called first and then hands execution to the site-local Drush. This gives you the convenience of running `drush` without specifying the full path to the executable, without sacrificing the safety provided by a site-local Drush.
2. Optional: Copy the [examples/drush.wrapper](https://github.com/drush-ops/drush/blob/8.x/examples/drush.wrapper) file to your project root and modify to taste. This is a handy launcher script; add --local here to turn off all global configuration locations, and maintain consistency over configuration/aliases/commandfiles for your team.
3. Note that if you have multiple Drupal sites on your system, it is possible to use a different version of Drush with each one.

Drupal Compatibility
-----------------
<table>
  <tr>
    <th> Drush Version </th> 
    <th> Drush Branch </th>
    <th> PHP </th>
    <th> Compatible Drupal versions </th>
    <th> Code Style </th>
    <th> Isolation Tests </th>
    <th> Functional Tests </th>
  </tr>
  <tr>
    <td> Drush 9 </td>
    <td> <a href="https://travis-ci.org/drush-ops/drush">master</a> </td>
    <td> 5.6+ </td>
    <td> D8.4+ </td>
    <td align="center">
      <img src="https://api.shippable.com/projects/5507addd5ab6cc1352a213b5/badge?branch=master" />
    </td>
    <td align="center">
      <img src="https://travis-ci.org/drush-ops/drush.svg?branch=master" />
    </td>
    <td align="center">
      <img src="https://circleci.com/gh/drush-ops/drush.svg?style=shield" />
    </td>
  </tr>
  <tr>
    <td> Drush 8 </td>
    <td> <a href="https://travis-ci.org/drush-ops/drush">8.x</a> </td>
    <td> 5.4.5+ </td>
    <td> D6, D7, D8.3- </td>
    <td align="center">
      <img src="https://circleci.com/gh/drush-ops/drush.svg?branch=8.x&style=shield" />
    </td>
    <td align="center">
      -
    </td>
    <td align="center">
      <img src="https://travis-ci.org/drush-ops/drush.svg?branch=8.x" />
    </td>
  </tr>
  <tr>
    <td> Drush 7 </td>
    <td> <a href="https://travis-ci.org/drush-ops/drush">7.x</a> </td>
    <td> 5.3.0+ </td>
    <td> D6, D7 </td>
    <td colspan="3" align="center"> Unsupported </td>
  </tr>
  <tr>
    <td> Drush 6 </td>
    <td> <a href="https://travis-ci.org/drush-ops/drush">6.x</a> </td>
    <td> 5.3.0+ </td>
    <td> D6, D7 </td>
    <td colspan="3" align="center"> Unsupported </td>
  </tr>
  <tr>
    <td> Drush 5 </td>
    <td> <a href="https://travis-ci.org/drush-ops/drush">5.x</a> </td>
    <td> 5.2.0+ </td>
    <td> D6, D7 </td>
    <td colspan="3" align="center"> Unsupported </td>
  </tr>
</table>

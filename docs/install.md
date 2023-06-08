!!! tip

    Drush only supports one install method. It requires that your Drupal site be built with Composer and Drush be listed as a dependency. 
    
    See the [Drush 8](http://docs.drush.org/en/8.x) or [Drush 9](https://docs.drush.org/en/9.x) docs for installing prior versions of Drush.

1. **Composer**. It is required that Drupal sites be built using Composer, with Drush listed as a dependency. See [recommended-project](https://www.drupal.org/docs/develop/using-composer/using-composer-to-install-drupal-and-manage-dependencies) (Drush must be added). If your Composer project doesn't yet depend on Drush, run `composer require drush/drush` to add it.
1. **Execution**. Change your working directory to your project root, and call Drush via `vendor/bin/drush`. To make this easier, append `./vendor/bin` to the end of your `$PATH`; this allows you to call Drush via `drush` once your working directory is set. If you only have only one Drupal codebase on your system, you may put `/path/to/vendor/bin/drush` in your `$PATH`; if you do this, then it is not necessary to set your working directory before calling Drush.
1. **Completion**. Optional. Append to .bashrc or equivalent for ZSH or [Fish shell](https://fishshell.com/). Run `drush completion --help` for more details.  
1. **Multiple Codebases**. Optional. If using the bash shell, consider installing the [fd](https://github.com/g1a/fd) project, a small set of scripts that make it easier to switch between different project directories quickly, with type completion.


!!! note
    - See [Usage](usage.md) for details on using Drush. 
    - To use a non-default PHP, [edit ~/.bashrc so that the desired PHP is in front of your $PATH](http://stackoverflow.com/questions/4145667/how-to-override-the-path-of-php-to-use-the-mamp-path/10653443#10653443). If that is not desirable, you can change your PATH for just one request: `PATH=/path/to/php:$PATH` drush status ...`
    - To use a custom php.ini for Drush requests, [see this comment](https://github.com/drush-ops/drush/issues/3294#issuecomment-370201342). 
    - See our [guide on porting commandfiles](https://weitzman.github.io/blog/port-to-drush9) from Drush 8 to later versions. Also note that alias and config files use a new .yml format in Drush 10+.

Drupal Compatibility
-----------------
<table>
  <tr>
    <th rowspan="2"> Drush Version </th> 
    <th rowspan="2"> PHP Version</th>
    <th rowspan="2"> End Of Life </th>
    <th colspan="4"> Drupal versions </th>
  </tr>
    <th>7</th> <th>8</th> <th>9</th> <th>10</th>
  </tr>
  <tr>
    <td> Drush 12 </td>
    <td> 8.1+ </td>
    <!-- Released Jun 2023 -->
    <td> TBD </td>
    <td></td> <td></td> <td></td> <td><b>✅</b></td>
  </tr>
  <tr>
    <td> Drush 11 </td>
    <td> 7.4+ </td>
    <!-- TBD -->
    <td> Nov 2023 </td>
    <td></td> <td></td> <td><b>✅</b></td> <td><b>✓</b></td>
  </tr>
  <tr>
    <td> Drush 10 </td>
    <td> 7.1+ (not 8) </td>
    <!-- Released Oct 2019 -->
    <td> Jan 2022 </td>
    <td></td> <td>✓</td> <td><b>✓</b></td> <td></td>
  </tr>
  <tr>
    <td> Drush 9 </td>
    <td> 5.6+ </td>
    <!-- Released Jan 2018 -->
    <td> May 2020 </td>
    <td></td> <td>✓</td> <td></td> <td></td>
  </tr>
  <tr>
    <td> Drush 8 </td>
    <td> 5.4.5+ </td>
    <!-- Released Nov 2015 -->
    <td> Jan 2025 </td>
    <td>✅</td> <td><b>✓️</b></td> <td></td> <td></td>
  </tr>
  <tr>
    <td> Drush 7 </td>
    <td> 5.3.0+ </td>
    <!-- Released May 2015 -->
    <td> Jul 2017 </td>
    <td>✓</td> <td></td> <td></td> <td></td>
  </tr>
  <tr>
    <td> Drush 6 </td>
    <td> 5.3.0+ </td>
    <!-- Released Aug 2013 -->
    <td> Dec 2015 </td>
    <td>✓</td> <td></td> <td></td> <td></td>
  </tr>
  <tr>
    <td> Drush 5 </td>
    <td> 5.2.0+ </td>
    <!-- Released March 2012 -->
    <td> May 2015 </td>
    <td>✓</td> <td></td> <td></td> <td></td>
  </tr>
</table>

<table>
    <tr>
        <th colspan="2">Legend</th>
    </tr>
    <tr>
        <td>✅</td> <td>Supported and recommended</td>
    </tr>
    <tr>
        <td>✓</td> <td>Compatible but no longer supported</td>
    </tr>
    
</table>


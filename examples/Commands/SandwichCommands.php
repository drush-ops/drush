<?php
namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Utils\StringUtils;

/**
 * Run these commands using the --include option - e.g. `drush --include=/path/to/drush/examples mmas`
 *
 * For an example of a Drupal module implementing commands, see
 * - http://cgit.drupalcode.org/devel/tree/devel_generate/src/Commands
 * - http://cgit.drupalcode.org/devel/tree/devel_generate/drush.services.yml
 *
 * This file is a good example of the first of those bullets (a commandfile) but
 * since it isn't part of a module, it does not implement drush.services.yml.
 */

class SandwichCommands extends DrushCommands {

  /**
   * Makes a delicious sandwich.
   *
   * @command make-me-a-sandwich
   * @param $filling The type of the sandwich (turkey, cheese, etc.). Defaults to ascii.
   * @option spreads A comma delimited list of spreads.
   * @usage drush mmas turkey --spreads=ketchup,mustard
   *   Make a terrible-tasting sandwich that is lacking in pickles.
   * @aliases mmas
   */
  public function makeSandwich($filling, $options = ['spreads' => NULL]) {
    if ($spreads = StringUtils::csvToArray('spreads')) {
      $list = implode(' and ', $spreads);
      $str_spreads = ' with just a dash of ' . $list;
    }
    $msg = dt('Okay. Enjoy this !filling sandwich!str_spreads.',
      array('!filling' => $filling, '!str_spreads' => $str_spreads)
    );
    $this->output()->writeln("\n" . $msg . "\n");
    $this->printFile(__DIR__ . '/sandwich-nocolor.txt');
  }

  /**
   * Show a table of information about available spreads.
   *
   * @command mmas-spreads
   * @aliases mspreads
   * @field-labels
   *   name: Name
   *   description: Description
   *   available: Num
   *   taste: Taste
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function spread($options = ['format' => 'table']) {
    $data = array(
      'ketchup' => array(
        'name' => 'Ketchup',
        'description' => 'Some say its a vegetable, but we know its a sweet spread.',
        'available' => '7',
        'taste' => 'sweet',
      ),
      'mayonnaise' => array(
        'name' => 'Mayonnaise',
        'description' => 'A nice dairy-free spread.',
        'available' => '12',
        'taste' => 'creamy',
      ),
      'mustard' => array(
        'name' => 'Mustard',
        'description' => 'Pardon me, but could you please pass that plastic yellow bottle?',
        'available' => '8',
        'taste' => 'tangy',
      ),
      'pickles' => array(
        'name' => 'Pickles',
        'description' => 'A necessary part of any sandwich that does not taste terrible.',
        'available' => '63',
        'taste' => 'tasty',
      ),
    );
    return new RowsOfFields($data);
  }

  /**
   * Commandfiles may also add topics.  These will appear in
   * the list of topics when `drush topic` is executed.
   * To view the topic below, run `drush --include=/full/path/to/examples topic`
   */

  /**
   * Ruminations on the true meaning and philosophy of sandwiches.
   *
   * @command sandwich-exposition
   * @hidden
   * @topic
   */
  public function ruminate() {
    self::printFile(__DIR__ . '/sandwich-topic.md');
  }

  /**
   * @hook validate make-me-a-sandwich
   */
  public function sandwichValidate(CommandData $commandData) {
    $name = posix_getpwuid(posix_geteuid());
    if ($name['name'] !== 'root') {
      throw new \Exception(dt('What? Make your own sandwich.'));
    }
  }
}

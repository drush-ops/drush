<?php

namespace Drupal\devel_generate_example\Plugin\DevelGenerate;

use Drupal\devel_generate\DevelGenerateBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a ExampleDevelGenerate plugin.
 *
 * @DevelGenerate(
 *   id = "devel_generate_example",
 *   label = "Example",
 *   description = "Generate a given number of examples. Optionally delete current examples.",
 *   url = "devel_generate_example",
 *   permission = "administer devel_generate",
 *   settings = {
 *     "num" = 50,
 *     "kill" = FALSE
 *   }
 * )
 */
class ExampleDevelGenerate extends DevelGenerateBase {

  public function settingsForm(array $form, FormStateInterface $form_state) {

    $form['num'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('How many examples would you like to generate?'),
      '#default_value' => $this->getSetting('num'),
      '#size' => 10,
    );

    $form['kill'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Delete all examples before generating new examples.'),
      '#default_value' => $this->getSetting('kill'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateElements(array $values) {
    $num = $values['num'];
    $kill = $values['kill'];

    if ($kill) {
        $this->setMessage($this->t('Old examples have been deleted.'));
    }

    //Creating user in order to demonstrate
    // how to override default business login generation.
    $edit = array(
      'uid'     => NULL,
      'name'    => 'example_devel_generate',
      'pass'    => '',
      'mail'    => 'example_devel_generate@example.com',
      'status'  => 1,
      'created' => REQUEST_TIME,
      'roles' => '',
      'devel_generate' => TRUE // A flag to let hook_user_* know that this is a generated user.
    );

    $account = user_load_by_name('example_devel_generate');
    if (!$account) {
      $account = entity_create('user', $edit);
    }

    // Populate all fields with sample values.
    $this->populateFields($node);

    $account->save();

    $this->setMessage($this->t('@num_examples created.', array('@num_examples' => $this->formatPlural($num, '1 example', '@count examples'))));
  }

  public function validateDrushParams($args) {
    $values = array(
      'num' => array_shift($args),
      'kill' => drush_get_option('kill'),
    );
    return $values;
  }

}

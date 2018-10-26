<?php

namespace Drupal\devel_generate\Plugin\DevelGenerate;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\devel_generate\DevelGenerateBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a VocabularyDevelGenerate plugin.
 *
 * @DevelGenerate(
 *   id = "vocabulary",
 *   label = @Translation("vocabularies"),
 *   description = @Translation("Generate a given number of vocabularies. Optionally delete current vocabularies."),
 *   url = "vocabs",
 *   permission = "administer devel_generate",
 *   settings = {
 *     "num" = 1,
 *     "title_length" = 12,
 *     "kill" = FALSE
 *   }
 * )
 */
class VocabularyDevelGenerate extends DevelGenerateBase implements ContainerFactoryPluginInterface {

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $vocabularyStorage;

  /**
   * Constructs a new VocabularyDevelGenerate object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The vocabulary storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $entity_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->vocabularyStorage = $entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('entity.manager')->getStorage('taxonomy_vocabulary')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['num'] = array(
      '#type' => 'number',
      '#title' => $this->t('Number of vocabularies?'),
      '#default_value' => $this->getSetting('num'),
      '#required' => TRUE,
      '#min' => 0,
    );
    $form['title_length'] = array(
      '#type' => 'number',
      '#title' => $this->t('Maximum number of characters in vocabulary names'),
      '#default_value' => $this->getSetting('title_length'),
      '#required' => TRUE,
      '#min' => 2,
      '#max' => 255,
    );
    $form['kill'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Delete existing vocabularies before generating new ones.'),
      '#default_value' => $this->getSetting('kill'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function generateElements(array $values) {
    if ($values['kill']) {
      $this->deleteVocabularies();
      $this->setMessage($this->t('Deleted existing vocabularies.'));
    }

    $new_vocs = $this->generateVocabularies($values['num'], $values['title_length']);
    if (!empty($new_vocs)) {
      $this->setMessage($this->t('Created the following new vocabularies: @vocs', array('@vocs' => implode(', ', $new_vocs))));
    }
  }

  /**
   * Deletes all vocabularies.
   */
  protected function deleteVocabularies() {
    $vocabularies = $this->vocabularyStorage->loadMultiple();
    $this->vocabularyStorage->delete($vocabularies);
  }

  /**
   * Generates vocabularies.
   *
   * @param int $records
   *   Number of vocabularies to create.
   * @param int $maxlength
   *   (optional) Maximum length for vocabulary name.
   *
   * @return array
   *   Array containing the generated vocabularies id.
   */
  protected function generateVocabularies($records, $maxlength = 12) {
    $vocabularies = array();

    // Insert new data:
    for ($i = 1; $i <= $records; $i++) {
      $name = $this->getRandom()->word(mt_rand(2, $maxlength));

      $vocabulary = $this->vocabularyStorage->create(array(
        'name' => $name,
        'vid' => Unicode::strtolower($name),
        'langcode' => Language::LANGCODE_NOT_SPECIFIED,
        'description' => "Description of $name",
        'hierarchy' => 1,
        'weight' => mt_rand(0, 10),
        'multiple' => 1,
        'required' => 0,
        'relations' => 1,
      ));

      // Populate all fields with sample values.
      $this->populateFields($vocabulary);
      $vocabulary->save();

      $vocabularies[] = $vocabulary->id();
      unset($vocabulary);
    }

    return $vocabularies;
  }

  /**
   * {@inheritdoc}
   */
  public function validateDrushParams($args, $options = []) {
    $values = array(
      'num' => array_shift($args),
      'kill' => $this->isDrush8() ? drush_get_option('kill') : $options['kill'],
      'title_length' => 12,
    );

    if ($this->isNumber($values['num']) == FALSE) {
      throw new \Exception(dt('Invalid number of vocabularies: @num.', array('@num' => $values['num'])));
    }

    return $values;
  }

}

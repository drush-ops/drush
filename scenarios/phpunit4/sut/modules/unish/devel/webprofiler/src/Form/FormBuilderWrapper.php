<?php

namespace Drupal\webprofiler\Form;

use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class FormBuilderWrapper
 */
class FormBuilderWrapper extends FormBuilder {

  /**
   * @var array
   */
  private $buildForms;

  /**
   * @return array
   */
  public function getBuildForm() {
    return $this->buildForms;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareForm($form_id, &$form, FormStateInterface &$form_state) {
    parent::prepareForm($form_id, $form, $form_state);

    if (!$this->buildForms) {
      $this->buildForms = [];
    }

    $elements = [];
    foreach ($form as $key => $value) {
      if (strpos($key, '#') !== 0) {
        $elements[$key]['#title'] = isset($value['#title']) ? $value['#title'] : NULL;
        $elements[$key]['#access'] = isset($value['#access']) ? $value['#access'] : NULL;
        $elements[$key]['#type'] = isset($value['#type']) ? $value['#type'] : NULL;
      }
    }

    $buildInfo = $form_state->getBuildInfo();

    $class = get_class($buildInfo['callback_object']);
    $method = new \ReflectionMethod($class, 'buildForm');

    $this->buildForms[$buildInfo['form_id']] = [
      'class' => [
        'class' => $class,
        'method' => 'buildForm',
        'file' => $method->getFilename(),
        'line' => $method->getStartLine(),
      ],
      'form' => $elements,
    ];

    return $form;
  }
}

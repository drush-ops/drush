<?php

function example_profile_details() {
  return array(
    'name' => 'Example',
    'description' => 'Example profile with a couple of basic added configuration options.',
  );
}

function example_profile_modules() {
  return array();
}

function example_form_alter(&$form, $form_state, $form_id) {
  if ($form_id == 'install_configure') {
    $form['my_options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Example options'),
    );
    $form['my_options']['myopt1'] = array(
      '#type' => 'textfield',
      '#title' => 'Example option 1'
    );
    $form['my_options']['myopt2'] = array(
      '#type' => 'select',
      '#title' => t('Example option 2'),
      '#options' => array(
        0 => t('Something'),
        1 => t('Something else'),
        2 => t('Something completely different'),
      ),
    );

    // Make sure we don't clobber the original auto-detected submit func
    $form['#submit'] = array('install_configure_form_submit', 'example_install_configure_form_submit');
  }
}

function example_install_configure_form_submit($form, &$form_state) {
  variable_set('myopt1', $form_state['values']['myopt1']);
  variable_set('myopt2', $form_state['values']['myopt2']);
}
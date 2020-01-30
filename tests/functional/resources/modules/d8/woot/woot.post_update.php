<?php

/**
 * Successful post-update.
 */
function woot_post_update_a()
{
    // Note that this is called 'a' so that it will run first. The post-updates
    // are executed in alphabetical order.
    return t('This is the update message from woot_post_update_a');
}

/**
 * Failing post-update.
 */
function woot_post_update_failing()
{
    throw new \Exception('This is the exception message thrown in woot_post_update_failing');
}

/**
 * Install the Devel module.
 */
function woot_post_update_install_drush_empty_module()
{
    \Drupal::service('module_installer')->install(['drush_empty_module']);
}

/**
 * Renders some content.
 */
function woot_post_update_render()
{
    // This post-update function allows us to test that all Drupal modules are
    // fully loaded when the updates are being performed. The renderer will
    // throw an exception if this is not the case.
    $render_array = [
        '#theme' => 'item_list',
        '#items' => ['a', 'b'],
    ];
    \Drupal::service('renderer')->renderPlain($render_array);
}

/**
 * Batch post update.
 */
function woot_post_update_batch(array &$sandbox)
{
    return woot_update_8105($sandbox);
}

/**
 * Install taxonomy.module
 */
function woot_post_update_install_taxonomy()
{
    \Drupal::service('module_installer')->install(['taxonomy']);
    return \Drupal::entityTypeManager()->getDefinition('taxonomy_term')->id();
}

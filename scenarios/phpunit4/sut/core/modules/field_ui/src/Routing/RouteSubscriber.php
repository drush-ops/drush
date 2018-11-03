<?php

namespace Drupal\field_ui\Routing;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for Field UI routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $manager;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $manager
   *   The entity type manager.
   */
  public function __construct(EntityManagerInterface $manager) {
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->manager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($route_name = $entity_type->get('field_ui_base_route')) {
        // Try to get the route from the current collection.
        if (!$entity_route = $collection->get($route_name)) {
          continue;
        }
        $path = $entity_route->getPath();

        $options = $entity_route->getOptions();
        if ($bundle_entity_type = $entity_type->getBundleEntityType()) {
          $options['parameters'][$bundle_entity_type] = [
            'type' => 'entity:' . $bundle_entity_type,
          ];
        }
        // Special parameter used to easily recognize all Field UI routes.
        $options['_field_ui'] = TRUE;

        $defaults = [
          'entity_type_id' => $entity_type_id,
        ];
        // If the entity type has no bundles and it doesn't use {bundle} in its
        // admin path, use the entity type.
        if (strpos($path, '{bundle}') === FALSE) {
          $defaults['bundle'] = !$entity_type->hasKey('bundle') ? $entity_type_id : '';
        }

        $route = new Route(
          "$path/fields/{field_config}",
          [
            '_entity_form' => 'field_config.edit',
            '_title_callback' => '\Drupal\field_ui\Form\FieldConfigEditForm::getTitle',
          ] + $defaults,
          ['_entity_access' => 'field_config.update'],
          $options
        );
        $collection->add("entity.field_config.{$entity_type_id}_field_edit_form", $route);

        $route = new Route(
          "$path/fields/{field_config}/storage",
          ['_entity_form' => 'field_storage_config.edit'] + $defaults,
          ['_permission' => 'administer ' . $entity_type_id . ' fields'],
          $options
        );
        $collection->add("entity.field_config.{$entity_type_id}_storage_edit_form", $route);

        $route = new Route(
          "$path/fields/{field_config}/delete",
          ['_entity_form' => 'field_config.delete'] + $defaults,
          ['_entity_access' => 'field_config.delete'],
          $options
        );
        $collection->add("entity.field_config.{$entity_type_id}_field_delete_form", $route);

        $route = new Route(
          "$path/fields",
          [
            '_controller' => '\Drupal\field_ui\Controller\FieldConfigListController::listing',
            '_title' => 'Manage fields',
          ] + $defaults,
          ['_permission' => 'administer ' . $entity_type_id . ' fields'],
          $options
        );
        $collection->add("entity.{$entity_type_id}.field_ui_fields", $route);

        $route = new Route(
          "$path/fields/add-field",
          [
            '_form' => '\Drupal\field_ui\Form\FieldStorageAddForm',
            '_title' => 'Add field',
          ] + $defaults,
          ['_permission' => 'administer ' . $entity_type_id . ' fields'],
          $options
        );
        $collection->add("field_ui.field_storage_config_add_$entity_type_id", $route);

        $route = new Route(
          "$path/form-display",
          [
            '_entity_form' => 'entity_form_display.edit',
            '_title' => 'Manage form display',
            'form_mode_name' => 'default',
          ] + $defaults,
          ['_field_ui_form_mode_access' => 'administer ' . $entity_type_id . ' form display'],
          $options
        );
        $collection->add("entity.entity_form_display.{$entity_type_id}.default", $route);

        $route = new Route(
          "$path/form-display/{form_mode_name}",
          [
            '_entity_form' => 'entity_form_display.edit',
            '_title' => 'Manage form display',
          ] + $defaults,
          ['_field_ui_form_mode_access' => 'administer ' . $entity_type_id . ' form display'],
          $options
        );
        $collection->add("entity.entity_form_display.{$entity_type_id}.form_mode", $route);

        $route = new Route(
          "$path/display",
          [
            '_entity_form' => 'entity_view_display.edit',
            '_title' => 'Manage display',
            'view_mode_name' => 'default',
          ] + $defaults,
          ['_field_ui_view_mode_access' => 'administer ' . $entity_type_id . ' display'],
          $options
        );
        $collection->add("entity.entity_view_display.{$entity_type_id}.default", $route);

        $route = new Route(
          "$path/display/{view_mode_name}",
          [
            '_entity_form' => 'entity_view_display.edit',
            '_title' => 'Manage display',
          ] + $defaults,
          ['_field_ui_view_mode_access' => 'administer ' . $entity_type_id . ' display'],
          $options
        );
        $collection->add("entity.entity_view_display.{$entity_type_id}.view_mode", $route);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -100];
    return $events;
  }

}

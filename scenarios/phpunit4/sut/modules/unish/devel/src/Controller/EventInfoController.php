<?php

namespace Drupal\devel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides route responses for the event info page.
 */
class EventInfoController extends ControllerBase {

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * EventInfoController constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_dispatcher')
    );
  }

  /**
   * Builds the events overview page.
   *
   * @return array
   *   A render array as expected by the renderer.
   */
  public function eventList() {
    $headers = [
      'name' => [
        'data' => $this->t('Event Name'),
        'class' => 'visually-hidden',
      ],
      'callable' => $this->t('Callable'),
      'priority' => $this->t('Priority'),
    ];

    $event_listeners = $this->eventDispatcher->getListeners();
    ksort($event_listeners);

    $rows = [];

    foreach ($event_listeners as $event_name => $listeners) {

      $rows[][] = [
        'data' => $event_name,
        'class' => 'table-filter-text-source devel-event-name-header',
        'colspan' => '3',
        'header' => TRUE,
      ];

      foreach ($listeners as $priority => $listener) {
        $row['name'] = [
          'data' => $event_name,
          'class' => 'table-filter-text-source visually-hidden',
        ];
        $row['class'] = [
          'data' => $this->resolveCallableName($listener),
        ];
        $row['priority'] = [
          'data' => $priority,
        ];
        $rows[] = $row;
      }
    }

    $output['#attached']['library'][] = 'system/drupal.system.modules';

    $output['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['table-filter', 'js-show'],
      ],
    ];
    $output['filters']['name'] = [
      '#type' => 'search',
      '#title' => $this->t('Search'),
      '#size' => 30,
      '#placeholder' => $this->t('Enter event name'),
      '#attributes' => [
        'class' => ['table-filter-text'],
        'data-table' => '.devel-filter-text',
        'autocomplete' => 'off',
        'title' => $this->t('Enter a part of the event name to filter by.'),
      ],
    ];
    $output['events'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No events found.'),
      '#attributes' => [
        'class' => ['devel-event-list', 'devel-filter-text'],
      ],
    ];

    return $output;
  }

  /**
   * Helper function for resolve callable name.
   *
   * @param mixed $callable
   *   The for which resolve the name. Can be either the name of a function
   *   stored in a string variable, or an object and the name of a method
   *   within the object.
   *
   * @return string
   *   The resolved callable name or an empty string.
   */
  protected function resolveCallableName($callable) {
    if (is_callable($callable, TRUE, $callable_name)) {
      return $callable_name;
    }
    return '';
  }

}

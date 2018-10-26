<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\webprofiler\EventDispatcher\EventDispatcherTraceableInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * Class EventsDataCollector
 */
class EventsDataCollector extends DataCollector implements DrupalDataCollectorInterface, LateDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * EventsDataCollector constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    $this->data = [
      'called_listeners' => [],
      'called_listeners_count' => 0,
      'not_called_listeners' => [],
      'not_called_listeners_count' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function lateCollect() {
    if ($this->eventDispatcher instanceof EventDispatcherTraceableInterface) {
      $countCalled = 0;
      $calledListeners = $this->eventDispatcher->getCalledListeners();
      foreach ($calledListeners as &$events) {
        foreach ($events as &$priority) {
          foreach ($priority as &$listener) {
            $countCalled++;
            $listener['clazz'] = $this->getMethodData($listener['class'], $listener['method']);
          }
        }
      }

      $countNotCalled = 0;
      $notCalledListeners = $this->eventDispatcher->getNotCalledListeners();
      foreach ($notCalledListeners as $events) {
        foreach ($events as $priority) {
          foreach ($priority as $listener) {
            $countNotCalled++;
          }
        }
      }

      $this->data = [
        'called_listeners' => $calledListeners,
        'called_listeners_count' => $countCalled,
        'not_called_listeners' => $notCalledListeners,
        'not_called_listeners_count' => $countNotCalled,
      ];
    }
  }

  /**
   * @return array
   */
  public function getCalledListeners() {
    return $this->data['called_listeners'];
  }

  /**
   * @return array
   */
  public function getNotCalledListeners() {
    return $this->data['not_called_listeners'];
  }

  /**
   * @return int
   */
  public function getCalledListenersCount() {
    return $this->data['called_listeners_count'];
  }

  /**
   * @return int
   */
  public function getNotCalledListenersCount() {
    return $this->data['not_called_listeners_count'];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'events';
  }

  /**
   * @return mixed
   */
  public function getData() {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Events');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Called listeners: @listeners', ['@listeners' => $this->getCalledListenersCount()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABUAAAAcCAYAAACOGPReAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABFJJREFUeNrkVVlIY2cY/RMTE81NMkkajUs1OBqkiVsjjAtStGrtSGyFjOjAQNVCKRb66ot9KrjgQx+FUgTBKkURbIfighWl4r6h44pajcZEo3ESTeKS9PzB2AyNZaD1qRcOem+S83/f+c53Lsvj8ZD/+mKTB7gehJTj+2d9fZ1MTk6S0NBQSW9vb97e3t7jmpqaXzIzM185HA7vd4KDg8nGxoaysbGxVCwWm/V6/aDL5TKlpKSQpKSkv5NyuVxyc3Mj7e7u/jw2NjYxJyfnMDIykmGz2UQgEBAWi0XcbjeRSqWhZWVl4v39fXVXV5cqNzf3exxmCNj+9fU1MzQ09JVWq32sUqmMu7u7QhwiDwoKIoeHh2R7e5twOByCwcrQhUShUJjz8vJkw8PDX5+fn8sDkvb3938YHR39rlAoNBoMBgGqtWxubnJRKbu9vZ20trZSQoJnvKioKMvZ2Rn/6urKmpqayvT19ekCks7NzaUnJyeboK0kPj7+cGZmJprH4zGnp6duEBFUTg4ODqjmIfPz87GQxoRnori4ODOKUPuTsnw+RRvPGIYJMZvNDNplYmJiLvPz839oamoSj4yMfAJNuRqN5mV9ff0fOPDF1NSUAt85lclkDkjnys7O/vGOlZLeQgjIgUggnmqHqmMqKip+z8jI8MAFnpKSkpXZ2dn38JkIUAFRQNjt/R2Xv09twBFwAGwClunp6efLy8tZdFgUW1tbiaOjo1/is9fUhcA+YL69fzvzSyQSEQZHfBJBT4J2Bf9qo9Rq9bxcLndeXl4STJrA8B4Mc/atN4pesAk5OTkh1PB0exYXF/kWi4UTFhZG+Hw+wZQJ5BDR7fEPIroYASu9uLggJpOJYO2I0+kkqI47Njb2MdzAKS4uXisvL5/FurIGBgaeYoDS1dVVsrKyQpaWlghsF7hS2IJERER4T4U/qckT4ccP6BYplco+rOcxqn0fZFqj0fgkLS3tV18m0EICktJV9F101xcWFj5Cu+HQ1YGNoeSXWGErpv8IwVOSlZXVh7xw0zy4V1MY3/uXWgetMzB8EZUHw7lKSEjgQ0MONLei2kcTExN5R0dHMehshw7x3umLRKI7YDhaDOSJ18hstq2qquobLMG30DKYkuzs7KggTa5Pf4p/rJReSCud1WplEBYuSMGrra39FG1ywsPDgwsLC+0YFoMAKi0qKupA5c57K0V1XjsdHx+/g6mXUksVFBS8wmF23FeMj48/w7PXiLsxePcG65qPDNCsra15XRCQFNP1AgRPMaA4aOvp6OjQ2O12cVtb20vE389YAHFLS0sO2vbYbLYQHKRHShEEy5ul+kIAe02Q5vy6urouTNyDV8VNT0/PBGzzxW1wRIHsM7T+W3V1tROvEE9lZeUCKlVgSfyD6S9SGsKdnZ1pOp3OkJ6efj04OPgTnmsAlv8PACXa/Q4L4UByuZqbm/UNDQ1vkLL+3+/9ByH9U4ABADscgvUMKuLiAAAAAElFTkSuQmCC';
  }
}

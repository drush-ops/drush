<?php

namespace Drupal\datetime\Plugin\views\argument;

/**
 * Argument handler for a month.
 *
 * @ViewsArgument("datetime_month")
 */
class MonthDate extends Date {

  /**
   * {@inheritdoc}
   */
  protected $argFormat = 'm';

}

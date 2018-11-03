/**
 * @file
 * Add aria attribute handling for details and summary elements.
 */

(function($, Drupal) {
  /**
   * Handles `aria-expanded` and `aria-pressed` attributes on details elements.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.detailsAria = {
    attach() {
      $('body')
        .once('detailsAria')
        .on('click.detailsAria', 'summary', event => {
          const $summary = $(event.currentTarget);
          const open =
            $(event.currentTarget.parentNode).attr('open') === 'open'
              ? 'false'
              : 'true';

          $summary.attr({
            'aria-expanded': open,
            'aria-pressed': open,
          });
        });
    },
  };
})(jQuery, Drupal);

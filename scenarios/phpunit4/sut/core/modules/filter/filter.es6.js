/**
 * @file
 * Attaches behavior for the Filter module.
 */

(function($, Drupal) {
  /**
   * Displays the guidelines of the selected text format automatically.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior for updating filter guidelines.
   */
  Drupal.behaviors.filterGuidelines = {
    attach(context) {
      function updateFilterGuidelines(event) {
        const $this = $(event.target);
        const value = $this.val();
        $this
          .closest('.filter-wrapper')
          .find('.filter-guidelines-item')
          .hide()
          .filter(`.filter-guidelines-${value}`)
          .show();
      }

      $(context)
        .find('.filter-guidelines')
        .once('filter-guidelines')
        .find(':header')
        .hide()
        .closest('.filter-wrapper')
        .find('select.filter-list')
        .on('change.filterGuidelines', updateFilterGuidelines)
        // Need to trigger the namespaced event to avoid triggering formUpdated
        // when initializing the select.
        .trigger('change.filterGuidelines');
    },
  };
})(jQuery, Drupal);

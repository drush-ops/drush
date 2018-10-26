(function ($, Drupal, Backbone) {

  "use strict";

  Drupal.webprofiler.views.CollectorsList = Backbone.View.extend({
    tagName: 'section',

    /**
     *
     * @returns {Drupal.webprofiler.views.CollectorsList}
     */
    render: function () {
      var collectorsView = this.collection.map(function (collector) {
        return (new Drupal.webprofiler.views.CollectorView({model: collector})).render().el;
      });
      this.$el.html(collectorsView);
      return this;
    }
  });

}(jQuery, Drupal, Backbone));

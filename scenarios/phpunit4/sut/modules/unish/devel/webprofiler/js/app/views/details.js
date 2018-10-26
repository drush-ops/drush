(function ($, Drupal, Backbone) {

  "use strict";

  Drupal.webprofiler.views.DetailsView = Backbone.View.extend({
    el: '#details',

    /**
     *
     * @returns {Drupal.webprofiler.views.DetailsView}
     */
    render: function () {
      var template = _.template($("script#" + this.model.get('name')).html());

      this.$el.html(template(this.model.toJSON()));
      return this;
    }
  });

}(jQuery, Drupal, Backbone));

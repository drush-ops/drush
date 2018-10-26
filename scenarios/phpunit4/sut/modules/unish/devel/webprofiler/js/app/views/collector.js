(function ($, Drupal, Backbone) {

  "use strict";

  Drupal.webprofiler.views.CollectorView = Backbone.View.extend({
    tagName: 'li',
    template: _.template($("script#collector").html()),

    /**
     *
     */
    initialize: function () {
      _.bindAll(this, "render");
      this.listenTo(this.model, 'change:selected', this.render);
    },

    /**
     *
     * @returns {Drupal.webprofiler.views.CollectorView}
     */
    render: function () {
      this.$el.html(this.template(this.model.toJSON()));
      this.$el.toggleClass('is--selected', this.model.get('selected'));
      return this;
    }

  });

}(jQuery, Drupal, Backbone));

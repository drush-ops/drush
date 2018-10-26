(function ($, Drupal, drupalSettings, Backbone) {

  "use strict";

  Drupal.webprofiler.models.Collector = Backbone.Model.extend({
    idAttribute: 'name',
    urlRoot: Drupal.url('admin/reports/profiler/view/' + drupalSettings.webprofiler.token + '/collectors'),
    defaults: {
      name: "default",
      data: [],
      selected: false
    }
  });

}(jQuery, Drupal, drupalSettings, Backbone));

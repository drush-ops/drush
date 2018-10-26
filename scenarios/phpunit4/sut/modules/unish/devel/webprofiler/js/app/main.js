(function ($, Drupal, Backbone) {

    "use strict";

    /**
     * Define namespaces.
     */
    Drupal.webprofiler = {
        views: {},
        models: {},
        collectors: {},
        routers: {}
    };

    Drupal.behaviors.webprofiler = {
        attach: function (context) {
            var el,
                elz,
                key,
                sel,
                value,
                select,
                selector,
                unselected,
                filter = [],

                livefilter = function (e) {
                    el = $(e).attr('id').replace('edit-', '');
                    value = $(e).val();
                    filter[el] = value.replace('/', '\/');
                    selector = [];
                    unselected = [];

                    for (key in filter) {
                        if (filter[key].length > 0 && filter[key] != ' ') {
                            select = filter[key].split(' ').filter(Boolean);
                            for (sel in select) {
                                selector.push('[data-wp-' + key + ' *= ' + select[sel].toLowerCase() + ']');
                                unselected.push('[data-wp-' + key + ']:not([data-wp-' + key + ' *= ' + select[sel].toLowerCase() + '])');
                            }
                        }
                        else {
                            selector.push('[data-wp-' + key + ']');
                        }
                    }
                    for (elz in unselected) {
                        $(unselected[elz]).addClass('is--hidden');
                    }
                    $(selector.join('')).removeClass('is--hidden');
                },

                modalFill = function(t,c){
                    $('.modal__title').html(t);
                    $('.modal__main-data').html(c);
                },

                clipboard = function (e, t) {
                    var clip = e.parent().find(t).get(0),
                        title = 'Original Code',
                        content = '<textarea readonly >' +
                            clip.textContent +
                            '</textarea>';

                    modalFill(title,content);
                    $('.modal').show();
                };

            $(context).find('#collectors').once('webprofiler').each(function () {
                new Drupal.webprofiler.routers.CollectorsRouter({el: $('#collectors')});
                Backbone.history.start({
                    pushState: false
                });
            });

            $(context).find('.js--modal-close').each(function () {
                $(this).on('click', function () {
                    $('.js--modal').hide();
                });
            });

            $(context).find('.js--live-filter').each(function () {
                $(this).on('keyup', function () {
                    livefilter($(this));
                });
                $(this).on('change', function () {
                    livefilter($(this));
                });
            });

            $(context).find('.js--panel-toggle').once('js--panel-toggle').each(function () {
                $(this).on('click', function () {
                    $(this).parent().parent().toggleClass('is--open');
                });
            });

            $(context).find('.js--clipboard-trigger').once('js--clipboard-trigger').each(function () {
                $(this).on('click', function () {
                        clipboard($(this), '.js--clipboard-target')
                    }
                );
            });
        }
    };

}(jQuery, Drupal, Backbone));

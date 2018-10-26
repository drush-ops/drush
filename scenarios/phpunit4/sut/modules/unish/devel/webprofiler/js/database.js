/**
 * @file
 * Database panel app.
 */
(function ($, Drupal, drupalSettings) {

    "use strict";

    Drupal.behaviors.webprofiler_database = {
        attach: function (context) {
            $(context).find('.js--explain-trigger').once('js--explain-trigger').each(function () {

                $(this).on('click', function () {
                    var position = $(this).attr('data-wp-queryPosition'),
                        wrapper = $(this).parent().parent().find('.js--explain-target'),
                        loader = $(this).parent().parent().find('.js--loader');

                    if (wrapper.html().length === 0) {

                        var url = Drupal.url('admin/reports/profiler/database_explain/' + drupalSettings.webprofiler.token + '/' + position);

                        loader.show();

                        $.getJSON(url, function (data) {
                            _.templateSettings.variable = 'rc';
                            var template = _.template(
                                $("#wp-query-explain-template").html()
                            );
                            wrapper.html(template(data));
                            loader.hide();
                            delete _.templateSettings.variable;
                        });
                    }
                    wrapper.toggle();

                });
            });

            $(context).find('.js--code-toggle').once('js--code-toggle').each(function () {
                $(this).on('click', function () {
                    $(this).parent().find('.js--code-target').find('code').toggleClass('is--hidden');
                });
            });

            $(context).find('.js--code-toggle--global').once('js--code-toggle--global').each(function () {
                $(this).on('click', function () {

                    if($(this).hasClass('js--placeholder-visible')){
                        $('.js--placeholder-query').addClass('is--hidden');
                        $('.js--original-query').removeClass('is--hidden');

                    }else{
                        $('.js--placeholder-query').removeClass('is--hidden');
                        $('.js--original-query').addClass('is--hidden');
                    }
                    $(this).toggleClass('js--placeholder-visible');
                });
            });

            if (typeof hljs != "undefined") {
                $('code.sql').each(function (i, block) {
                    hljs.highlightBlock(block);
                });
            }
        }
    }
})
(jQuery, Drupal, drupalSettings);

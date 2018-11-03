/**
 * @file
 * Defines the behavior of the Drupal administration toolbar.
 */

(function($, Drupal, drupalSettings) {
  // Merge run-time settings with the defaults.
  const options = $.extend(
    {
      breakpoints: {
        'toolbar.narrow': '',
        'toolbar.standard': '',
        'toolbar.wide': '',
      },
    },
    drupalSettings.toolbar,
    // Merge strings on top of drupalSettings so that they are not mutable.
    {
      strings: {
        horizontal: Drupal.t('Horizontal orientation'),
        vertical: Drupal.t('Vertical orientation'),
      },
    },
  );

  /**
   * Registers tabs with the toolbar.
   *
   * The Drupal toolbar allows modules to register top-level tabs. These may
   * point directly to a resource or toggle the visibility of a tray.
   *
   * Modules register tabs with hook_toolbar().
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the toolbar rendering functionality to the toolbar element.
   */
  Drupal.behaviors.toolbar = {
    attach(context) {
      // Verify that the user agent understands media queries. Complex admin
      // toolbar layouts require media query support.
      if (!window.matchMedia('only screen').matches) {
        return;
      }
      // Process the administrative toolbar.
      $(context)
        .find('#toolbar-administration')
        .once('toolbar')
        .each(function() {
          // Establish the toolbar models and views.
          const model = new Drupal.toolbar.ToolbarModel({
            locked: JSON.parse(
              localStorage.getItem('Drupal.toolbar.trayVerticalLocked'),
            ),
            activeTab: document.getElementById(
              JSON.parse(localStorage.getItem('Drupal.toolbar.activeTabID')),
            ),
            height: $('#toolbar-administration').outerHeight(),
          });

          Drupal.toolbar.models.toolbarModel = model;

          // Attach a listener to the configured media query breakpoints.
          // Executes it before Drupal.toolbar.views to avoid extra rendering.
          Object.keys(options.breakpoints).forEach(label => {
            const mq = options.breakpoints[label];
            const mql = window.matchMedia(mq);
            Drupal.toolbar.mql[label] = mql;
            // Curry the model and the label of the media query breakpoint to
            // the mediaQueryChangeHandler function.
            mql.addListener(
              Drupal.toolbar.mediaQueryChangeHandler.bind(null, model, label),
            );
            // Fire the mediaQueryChangeHandler for each configured breakpoint
            // so that they process once.
            Drupal.toolbar.mediaQueryChangeHandler.call(
              null,
              model,
              label,
              mql,
            );
          });

          Drupal.toolbar.views.toolbarVisualView = new Drupal.toolbar.ToolbarVisualView(
            {
              el: this,
              model,
              strings: options.strings,
            },
          );
          Drupal.toolbar.views.toolbarAuralView = new Drupal.toolbar.ToolbarAuralView(
            {
              el: this,
              model,
              strings: options.strings,
            },
          );
          Drupal.toolbar.views.bodyVisualView = new Drupal.toolbar.BodyVisualView(
            {
              el: this,
              model,
            },
          );

          // Force layout render to fix mobile view. Only needed on load, not
          // for every media query match.
          model.trigger('change:isFixed', model, model.get('isFixed'));
          model.trigger('change:activeTray', model, model.get('activeTray'));

          // Render collapsible menus.
          const menuModel = new Drupal.toolbar.MenuModel();
          Drupal.toolbar.models.menuModel = menuModel;
          Drupal.toolbar.views.menuVisualView = new Drupal.toolbar.MenuVisualView(
            {
              el: $(this)
                .find('.toolbar-menu-administration')
                .get(0),
              model: menuModel,
              strings: options.strings,
            },
          );

          // Handle the resolution of Drupal.toolbar.setSubtrees.
          // This is handled with a deferred so that the function may be invoked
          // asynchronously.
          Drupal.toolbar.setSubtrees.done(subtrees => {
            menuModel.set('subtrees', subtrees);
            const theme = drupalSettings.ajaxPageState.theme;
            localStorage.setItem(
              `Drupal.toolbar.subtrees.${theme}`,
              JSON.stringify(subtrees),
            );
            // Indicate on the toolbarModel that subtrees are now loaded.
            model.set('areSubtreesLoaded', true);
          });

          // Trigger an initial attempt to load menu subitems. This first attempt
          // is made after the media query handlers have had an opportunity to
          // process. The toolbar starts in the vertical orientation by default,
          // unless the viewport is wide enough to accommodate a horizontal
          // orientation. Thus we give the Toolbar a chance to determine if it
          // should be set to horizontal orientation before attempting to load
          // menu subtrees.
          Drupal.toolbar.views.toolbarVisualView.loadSubtrees();

          $(document)
            // Update the model when the viewport offset changes.
            .on('drupalViewportOffsetChange.toolbar', (event, offsets) => {
              model.set('offsets', offsets);
            });

          // Broadcast model changes to other modules.
          model
            .on('change:orientation', (model, orientation) => {
              $(document).trigger(
                'drupalToolbarOrientationChange',
                orientation,
              );
            })
            .on('change:activeTab', (model, tab) => {
              $(document).trigger('drupalToolbarTabChange', tab);
            })
            .on('change:activeTray', (model, tray) => {
              $(document).trigger('drupalToolbarTrayChange', tray);
            });

          // If the toolbar's orientation is horizontal and no active tab is
          // defined then show the tray of the first toolbar tab by default (but
          // not the first 'Home' toolbar tab).
          if (
            Drupal.toolbar.models.toolbarModel.get('orientation') ===
              'horizontal' &&
            Drupal.toolbar.models.toolbarModel.get('activeTab') === null
          ) {
            Drupal.toolbar.models.toolbarModel.set({
              activeTab: $(
                '.toolbar-bar .toolbar-tab:not(.home-toolbar-tab) a',
              ).get(0),
            });
          }

          $(window).on({
            'dialog:aftercreate': (event, dialog, $element, settings) => {
              const $toolbar = $('#toolbar-bar');
              $toolbar.css('margin-top', '0');

              // When off-canvas is positioned in top, toolbar has to be moved down.
              if (settings.drupalOffCanvasPosition === 'top') {
                const height = Drupal.offCanvas
                  .getContainer($element)
                  .outerHeight();
                $toolbar.css('margin-top', `${height}px`);

                $element.on('dialogContentResize.off-canvas', () => {
                  const newHeight = Drupal.offCanvas
                    .getContainer($element)
                    .outerHeight();
                  $toolbar.css('margin-top', `${newHeight}px`);
                });
              }
            },
            'dialog:beforeclose': () => {
              $('#toolbar-bar').css('margin-top', '0');
            },
          });
        });
    },
  };

  /**
   * Toolbar methods of Backbone objects.
   *
   * @namespace
   */
  Drupal.toolbar = {
    /**
     * A hash of View instances.
     *
     * @type {object.<string, Backbone.View>}
     */
    views: {},

    /**
     * A hash of Model instances.
     *
     * @type {object.<string, Backbone.Model>}
     */
    models: {},

    /**
     * A hash of MediaQueryList objects tracked by the toolbar.
     *
     * @type {object.<string, object>}
     */
    mql: {},

    /**
     * Accepts a list of subtree menu elements.
     *
     * A deferred object that is resolved by an inlined JavaScript callback.
     *
     * @type {jQuery.Deferred}
     *
     * @see toolbar_subtrees_jsonp().
     */
    setSubtrees: new $.Deferred(),

    /**
     * Respond to configured narrow media query changes.
     *
     * @param {Drupal.toolbar.ToolbarModel} model
     *   A toolbar model
     * @param {string} label
     *   Media query label.
     * @param {object} mql
     *   A MediaQueryList object.
     */
    mediaQueryChangeHandler(model, label, mql) {
      switch (label) {
        case 'toolbar.narrow':
          model.set({
            isOriented: mql.matches,
            isTrayToggleVisible: false,
          });
          // If the toolbar doesn't have an explicit orientation yet, or if the
          // narrow media query doesn't match then set the orientation to
          // vertical.
          if (!mql.matches || !model.get('orientation')) {
            model.set({ orientation: 'vertical' }, { validate: true });
          }
          break;

        case 'toolbar.standard':
          model.set({
            isFixed: mql.matches,
          });
          break;

        case 'toolbar.wide':
          model.set(
            {
              orientation:
                mql.matches && !model.get('locked') ? 'horizontal' : 'vertical',
            },
            { validate: true },
          );
          // The tray orientation toggle visibility does not need to be
          // validated.
          model.set({
            isTrayToggleVisible: mql.matches,
          });
          break;

        default:
          break;
      }
    },
  };

  /**
   * A toggle is an interactive element often bound to a click handler.
   *
   * @return {string}
   *   A string representing a DOM fragment.
   */
  Drupal.theme.toolbarOrientationToggle = function() {
    return (
      '<div class="toolbar-toggle-orientation"><div class="toolbar-lining">' +
      '<button class="toolbar-icon" type="button"></button>' +
      '</div></div>'
    );
  };

  /**
   * Ajax command to set the toolbar subtrees.
   *
   * @param {Drupal.Ajax} ajax
   *   {@link Drupal.Ajax} object created by {@link Drupal.ajax}.
   * @param {object} response
   *   JSON response from the Ajax request.
   * @param {number} [status]
   *   XMLHttpRequest status.
   */
  Drupal.AjaxCommands.prototype.setToolbarSubtrees = function(
    ajax,
    response,
    status,
  ) {
    Drupal.toolbar.setSubtrees.resolve(response.subtrees);
  };
})(jQuery, Drupal, drupalSettings);

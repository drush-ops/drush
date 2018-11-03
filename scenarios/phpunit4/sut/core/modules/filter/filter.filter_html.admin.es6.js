/**
 * @file
 * Attaches behavior for updating filter_html's settings automatically.
 */

(function($, Drupal, _, document) {
  if (Drupal.filterConfiguration) {
    /**
     * Implement a live setting parser to prevent text editors from automatically
     * enabling buttons that are not allowed by this filter's configuration.
     *
     * @namespace
     */
    Drupal.filterConfiguration.liveSettingParsers.filter_html = {
      /**
       * @return {Array}
       *   An array of filter rules.
       */
      getRules() {
        const currentValue = $(
          '#edit-filters-filter-html-settings-allowed-html',
        ).val();
        const rules = Drupal.behaviors.filterFilterHtmlUpdating._parseSetting(
          currentValue,
        );

        // Build a FilterHTMLRule that reflects the hard-coded behavior that
        // strips all "style" attribute and all "on*" attributes.
        const rule = new Drupal.FilterHTMLRule();
        rule.restrictedTags.tags = ['*'];
        rule.restrictedTags.forbidden.attributes = ['style', 'on*'];
        rules.push(rule);

        return rules;
      },
    };
  }

  /**
   * Displays and updates what HTML tags are allowed to use in a filter.
   *
   * @type {Drupal~behavior}
   *
   * @todo Remove everything but 'attach' and 'detach' and make a proper object.
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior for updating allowed HTML tags.
   */
  Drupal.behaviors.filterFilterHtmlUpdating = {
    // The form item contains the "Allowed HTML tags" setting.
    $allowedHTMLFormItem: null,

    // The description for the "Allowed HTML tags" field.
    $allowedHTMLDescription: null,

    /**
     * The parsed, user-entered tag list of $allowedHTMLFormItem
     *
     * @var {Object.<string, Drupal.FilterHTMLRule>}
     */
    userTags: {},

    // The auto-created tag list thus far added.
    autoTags: null,

    // Track which new features have been added to the text editor.
    newFeatures: {},

    attach(context, settings) {
      const that = this;
      $(context)
        .find('[name="filters[filter_html][settings][allowed_html]"]')
        .once('filter-filter_html-updating')
        .each(function() {
          that.$allowedHTMLFormItem = $(this);
          that.$allowedHTMLDescription = that.$allowedHTMLFormItem
            .closest('.js-form-item')
            .find('.description');
          that.userTags = that._parseSetting(this.value);

          // Update the new allowed tags based on added text editor features.
          $(document)
            .on('drupalEditorFeatureAdded', (e, feature) => {
              that.newFeatures[feature.name] = feature.rules;
              that._updateAllowedTags();
            })
            .on('drupalEditorFeatureModified', (e, feature) => {
              if (that.newFeatures.hasOwnProperty(feature.name)) {
                that.newFeatures[feature.name] = feature.rules;
                that._updateAllowedTags();
              }
            })
            .on('drupalEditorFeatureRemoved', (e, feature) => {
              if (that.newFeatures.hasOwnProperty(feature.name)) {
                delete that.newFeatures[feature.name];
                that._updateAllowedTags();
              }
            });

          // When the allowed tags list is manually changed, update userTags.
          that.$allowedHTMLFormItem.on('change.updateUserTags', function() {
            that.userTags = _.difference(
              that._parseSetting(this.value),
              that.autoTags,
            );
          });
        });
    },

    /**
     * Updates the "Allowed HTML tags" setting and shows an informative message.
     */
    _updateAllowedTags() {
      // Update the list of auto-created tags.
      this.autoTags = this._calculateAutoAllowedTags(
        this.userTags,
        this.newFeatures,
      );

      // Remove any previous auto-created tag message.
      this.$allowedHTMLDescription.find('.editor-update-message').remove();

      // If any auto-created tags: insert message and update form item.
      if (!_.isEmpty(this.autoTags)) {
        this.$allowedHTMLDescription.append(
          Drupal.theme('filterFilterHTMLUpdateMessage', this.autoTags),
        );
        const userTagsWithoutOverrides = _.omit(
          this.userTags,
          _.keys(this.autoTags),
        );
        this.$allowedHTMLFormItem.val(
          `${this._generateSetting(
            userTagsWithoutOverrides,
          )} ${this._generateSetting(this.autoTags)}`,
        );
      }
      // Restore to original state.
      else {
        this.$allowedHTMLFormItem.val(this._generateSetting(this.userTags));
      }
    },

    /**
     * Calculates which HTML tags the added text editor buttons need to work.
     *
     * The filter_html filter is only concerned with the required tags, not with
     * any properties, nor with each feature's "allowed" tags.
     *
     * @param {Array} userAllowedTags
     *   The list of user-defined allowed tags.
     * @param {object} newFeatures
     *   A list of {@link Drupal.EditorFeature} objects' rules, keyed by
     *   their name.
     *
     * @return {Array}
     *   A list of new allowed tags.
     */
    _calculateAutoAllowedTags(userAllowedTags, newFeatures) {
      const editorRequiredTags = {};

      // Map the newly added Text Editor features to Drupal.FilterHtmlRule
      // objects (to allow comparing userTags with autoTags).
      Object.keys(newFeatures || {}).forEach(featureName => {
        const feature = newFeatures[featureName];
        let featureRule;
        let filterRule;
        let tag;

        for (let f = 0; f < feature.length; f++) {
          featureRule = feature[f];
          for (let t = 0; t < featureRule.required.tags.length; t++) {
            tag = featureRule.required.tags[t];
            if (!_.has(editorRequiredTags, tag)) {
              filterRule = new Drupal.FilterHTMLRule();
              filterRule.restrictedTags.tags = [tag];
              // @todo Neither Drupal.FilterHtmlRule nor
              //   Drupal.EditorFeatureHTMLRule allow for generic attribute
              //   value restrictions, only for the "class" and "style"
              //   attribute's values to be restricted. The filter_html filter
              //   always disallows the "style" attribute, so we only need to
              //   support "class" attribute value restrictions. Fix once
              //   https://www.drupal.org/node/2567801 lands.
              filterRule.restrictedTags.allowed.attributes = featureRule.required.attributes.slice(
                0,
              );
              filterRule.restrictedTags.allowed.classes = featureRule.required.classes.slice(
                0,
              );
              editorRequiredTags[tag] = filterRule;
            }
            // The tag is already allowed, add any additionally allowed
            // attributes.
            else {
              filterRule = editorRequiredTags[tag];
              filterRule.restrictedTags.allowed.attributes = _.union(
                filterRule.restrictedTags.allowed.attributes,
                featureRule.required.attributes,
              );
              filterRule.restrictedTags.allowed.classes = _.union(
                filterRule.restrictedTags.allowed.classes,
                featureRule.required.classes,
              );
            }
          }
        }
      });

      // Now compare userAllowedTags with editorRequiredTags, and build
      // autoAllowedTags, which contains:
      // - any tags in editorRequiredTags but not in userAllowedTags (i.e. tags
      //   that are additionally going to be allowed)
      // - any tags in editorRequiredTags that already exists in userAllowedTags
      //   but does not allow all attributes or attribute values
      const autoAllowedTags = {};
      Object.keys(editorRequiredTags).forEach(tag => {
        // If userAllowedTags does not contain a rule for this editor-required
        // tag, then add it to the list of automatically allowed tags.
        if (!_.has(userAllowedTags, tag)) {
          autoAllowedTags[tag] = editorRequiredTags[tag];
        }
        // Otherwise, if userAllowedTags already allows this tag, then check if
        // additional attributes and classes on this tag are required by the
        // editor.
        else {
          const requiredAttributes =
            editorRequiredTags[tag].restrictedTags.allowed.attributes;
          const allowedAttributes =
            userAllowedTags[tag].restrictedTags.allowed.attributes;
          const needsAdditionalAttributes =
            requiredAttributes.length &&
            _.difference(requiredAttributes, allowedAttributes).length;
          const requiredClasses =
            editorRequiredTags[tag].restrictedTags.allowed.classes;
          const allowedClasses =
            userAllowedTags[tag].restrictedTags.allowed.classes;
          const needsAdditionalClasses =
            requiredClasses.length &&
            _.difference(requiredClasses, allowedClasses).length;
          if (needsAdditionalAttributes || needsAdditionalClasses) {
            autoAllowedTags[tag] = userAllowedTags[tag].clone();
          }
          if (needsAdditionalAttributes) {
            autoAllowedTags[tag].restrictedTags.allowed.attributes = _.union(
              allowedAttributes,
              requiredAttributes,
            );
          }
          if (needsAdditionalClasses) {
            autoAllowedTags[tag].restrictedTags.allowed.classes = _.union(
              allowedClasses,
              requiredClasses,
            );
          }
        }
      });

      return autoAllowedTags;
    },

    /**
     * Parses the value of this.$allowedHTMLFormItem.
     *
     * @param {string} setting
     *   The string representation of the setting. For example:
     *     <p class="callout"> <br> <a href hreflang>
     *
     * @return {Object.<string, Drupal.FilterHTMLRule>}
     *   The corresponding text filter HTML rule objects, one per tag, keyed by
     *   tag name.
     */
    _parseSetting(setting) {
      let node;
      let tag;
      let rule;
      let attributes;
      let attribute;
      const allowedTags = setting.match(/(<[^>]+>)/g);
      const sandbox = document.createElement('div');
      const rules = {};
      for (let t = 0; t < allowedTags.length; t++) {
        // Let the browser do the parsing work for us.
        sandbox.innerHTML = allowedTags[t];
        node = sandbox.firstChild;
        tag = node.tagName.toLowerCase();

        // Build the Drupal.FilterHtmlRule object.
        rule = new Drupal.FilterHTMLRule();
        // We create one rule per allowed tag, so always one tag.
        rule.restrictedTags.tags = [tag];
        // Add the attribute restrictions.
        attributes = node.attributes;
        for (let i = 0; i < attributes.length; i++) {
          attribute = attributes.item(i);
          const attributeName = attribute.nodeName;
          // @todo Drupal.FilterHtmlRule does not allow for generic attribute
          //   value restrictions, only for the "class" and "style" attribute's
          //   values. The filter_html filter always disallows the "style"
          //   attribute, so we only need to support "class" attribute value
          //   restrictions. Fix once https://www.drupal.org/node/2567801 lands.
          if (attributeName === 'class') {
            const attributeValue = attribute.textContent;
            rule.restrictedTags.allowed.classes = attributeValue.split(' ');
          } else {
            rule.restrictedTags.allowed.attributes.push(attributeName);
          }
        }

        rules[tag] = rule;
      }
      return rules;
    },

    /**
     * Generates the value of this.$allowedHTMLFormItem.
     *
     * @param {Object.<string, Drupal.FilterHTMLRule>} tags
     *   The parsed representation of the setting.
     *
     * @return {Array}
     *   The string representation of the setting. e.g. "<p> <br> <a>"
     */
    _generateSetting(tags) {
      return _.reduce(
        tags,
        (setting, rule, tag) => {
          if (setting.length) {
            setting += ' ';
          }

          setting += `<${tag}`;
          if (rule.restrictedTags.allowed.attributes.length) {
            setting += ` ${rule.restrictedTags.allowed.attributes.join(' ')}`;
          }
          // @todo Drupal.FilterHtmlRule does not allow for generic attribute
          //   value restrictions, only for the "class" and "style" attribute's
          //   values. The filter_html filter always disallows the "style"
          //   attribute, so we only need to support "class" attribute value
          //   restrictions. Fix once https://www.drupal.org/node/2567801 lands.
          if (rule.restrictedTags.allowed.classes.length) {
            setting += ` class="${rule.restrictedTags.allowed.classes.join(
              ' ',
            )}"`;
          }

          setting += '>';
          return setting;
        },
        '',
      );
    },
  };

  /**
   * Theme function for the filter_html update message.
   *
   * @param {Array} tags
   *   An array of the new tags that are to be allowed.
   *
   * @return {string}
   *   The corresponding HTML.
   */
  Drupal.theme.filterFilterHTMLUpdateMessage = function(tags) {
    let html = '';
    const tagList = Drupal.behaviors.filterFilterHtmlUpdating._generateSetting(
      tags,
    );
    html += '<p class="editor-update-message">';
    html += Drupal.t(
      'Based on the text editor configuration, these tags have automatically been added: <strong>@tag-list</strong>.',
      { '@tag-list': tagList },
    );
    html += '</p>';
    return html;
  };
})(jQuery, Drupal, _, document);

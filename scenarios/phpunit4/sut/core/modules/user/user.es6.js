/**
 * @file
 * User behaviors.
 */

(function($, Drupal, drupalSettings) {
  /**
   * Attach handlers to evaluate the strength of any password fields and to
   * check that its confirmation is correct.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches password strength indicator and other relevant validation to
   *   password fields.
   */
  Drupal.behaviors.password = {
    attach(context, settings) {
      const $passwordInput = $(context)
        .find('input.js-password-field')
        .once('password');

      if ($passwordInput.length) {
        const translate = settings.password;

        const $passwordInputParent = $passwordInput.parent();
        const $passwordInputParentWrapper = $passwordInputParent.parent();
        let $passwordSuggestions;

        // Add identifying class to password element parent.
        $passwordInputParent.addClass('password-parent');

        // Add the password confirmation layer.
        $passwordInputParentWrapper
          .find('input.js-password-confirm')
          .parent()
          .append(
            `<div aria-live="polite" aria-atomic="true" class="password-confirm js-password-confirm">${
              translate.confirmTitle
            } <span></span></div>`,
          )
          .addClass('confirm-parent');

        const $confirmInput = $passwordInputParentWrapper.find(
          'input.js-password-confirm',
        );
        const $confirmResult = $passwordInputParentWrapper.find(
          'div.js-password-confirm',
        );
        const $confirmChild = $confirmResult.find('span');

        // If the password strength indicator is enabled, add its markup.
        if (settings.password.showStrengthIndicator) {
          const passwordMeter = `<div class="password-strength"><div class="password-strength__meter"><div class="password-strength__indicator js-password-strength__indicator"></div></div><div aria-live="polite" aria-atomic="true" class="password-strength__title">${
            translate.strengthTitle
          } <span class="password-strength__text js-password-strength__text"></span></div></div>`;
          $confirmInput
            .parent()
            .after('<div class="password-suggestions description"></div>');
          $passwordInputParent.append(passwordMeter);
          $passwordSuggestions = $passwordInputParentWrapper
            .find('div.password-suggestions')
            .hide();
        }

        // Check that password and confirmation inputs match.
        const passwordCheckMatch = function(confirmInputVal) {
          const success = $passwordInput.val() === confirmInputVal;
          const confirmClass = success ? 'ok' : 'error';

          // Fill in the success message and set the class accordingly.
          $confirmChild
            .html(translate[`confirm${success ? 'Success' : 'Failure'}`])
            .removeClass('ok error')
            .addClass(confirmClass);
        };

        // Check the password strength.
        const passwordCheck = function() {
          if (settings.password.showStrengthIndicator) {
            // Evaluate the password strength.
            const result = Drupal.evaluatePasswordStrength(
              $passwordInput.val(),
              settings.password,
            );

            // Update the suggestions for how to improve the password.
            if ($passwordSuggestions.html() !== result.message) {
              $passwordSuggestions.html(result.message);
            }

            // Only show the description box if a weakness exists in the
            // password.
            $passwordSuggestions.toggle(result.strength !== 100);

            // Adjust the length of the strength indicator.
            $passwordInputParent
              .find('.js-password-strength__indicator')
              .css('width', `${result.strength}%`)
              .removeClass('is-weak is-fair is-good is-strong')
              .addClass(result.indicatorClass);

            // Update the strength indication text.
            $passwordInputParent
              .find('.js-password-strength__text')
              .html(result.indicatorText);
          }

          // Check the value in the confirm input and show results.
          if ($confirmInput.val()) {
            passwordCheckMatch($confirmInput.val());
            $confirmResult.css({ visibility: 'visible' });
          } else {
            $confirmResult.css({ visibility: 'hidden' });
          }
        };

        // Monitor input events.
        $passwordInput.on('input', passwordCheck);
        $confirmInput.on('input', passwordCheck);
      }
    },
  };

  /**
   * Evaluate the strength of a user's password.
   *
   * Returns the estimated strength and the relevant output message.
   *
   * @param {string} password
   *   The password to evaluate.
   * @param {object} translate
   *   An object containing the text to display for each strength level.
   *
   * @return {object}
   *   An object containing strength, message, indicatorText and indicatorClass.
   */
  Drupal.evaluatePasswordStrength = function(password, translate) {
    password = password.trim();
    let indicatorText;
    let indicatorClass;
    let weaknesses = 0;
    let strength = 100;
    let msg = [];

    const hasLowercase = /[a-z]/.test(password);
    const hasUppercase = /[A-Z]/.test(password);
    const hasNumbers = /[0-9]/.test(password);
    const hasPunctuation = /[^a-zA-Z0-9]/.test(password);

    // If there is a username edit box on the page, compare password to that,
    // otherwise use value from the database.
    const $usernameBox = $('input.username');
    const username =
      $usernameBox.length > 0 ? $usernameBox.val() : translate.username;

    // Lose 5 points for every character less than 12, plus a 30 point penalty.
    if (password.length < 12) {
      msg.push(translate.tooShort);
      strength -= (12 - password.length) * 5 + 30;
    }

    // Count weaknesses.
    if (!hasLowercase) {
      msg.push(translate.addLowerCase);
      weaknesses++;
    }
    if (!hasUppercase) {
      msg.push(translate.addUpperCase);
      weaknesses++;
    }
    if (!hasNumbers) {
      msg.push(translate.addNumbers);
      weaknesses++;
    }
    if (!hasPunctuation) {
      msg.push(translate.addPunctuation);
      weaknesses++;
    }

    // Apply penalty for each weakness (balanced against length penalty).
    switch (weaknesses) {
      case 1:
        strength -= 12.5;
        break;

      case 2:
        strength -= 25;
        break;

      case 3:
        strength -= 40;
        break;

      case 4:
        strength -= 40;
        break;
    }

    // Check if password is the same as the username.
    if (password !== '' && password.toLowerCase() === username.toLowerCase()) {
      msg.push(translate.sameAsUsername);
      // Passwords the same as username are always very weak.
      strength = 5;
    }

    // Based on the strength, work out what text should be shown by the
    // password strength meter.
    if (strength < 60) {
      indicatorText = translate.weak;
      indicatorClass = 'is-weak';
    } else if (strength < 70) {
      indicatorText = translate.fair;
      indicatorClass = 'is-fair';
    } else if (strength < 80) {
      indicatorText = translate.good;
      indicatorClass = 'is-good';
    } else if (strength <= 100) {
      indicatorText = translate.strong;
      indicatorClass = 'is-strong';
    }

    // Assemble the final message.
    msg = `${translate.hasWeaknesses}<ul><li>${msg.join(
      '</li><li>',
    )}</li></ul>`;

    return {
      strength,
      message: msg,
      indicatorText,
      indicatorClass,
    };
  };
})(jQuery, Drupal, drupalSettings);

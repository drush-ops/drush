<?php

namespace Drupal\FunctionalJavascriptTests;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementHtmlException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Drupal\Tests\WebAssert;

/**
 * Defines a class with methods for asserting presence of elements during tests.
 */
class JSWebAssert extends WebAssert {

  /**
   * Waits for AJAX request to be completed.
   *
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   * @param string $message
   *   (optional) A message for exception.
   *
   * @throws \RuntimeException
   *   When the request is not completed. If left blank, a default message will
   *   be displayed.
   */
  public function assertWaitOnAjaxRequest($timeout = 10000, $message = 'Unable to complete AJAX request.') {
    $condition = <<<JS
      (function() {
        function isAjaxing(instance) {
          return instance && instance.ajaxing === true;
        }
        return (
          // Assert no AJAX request is running (via jQuery or Drupal) and no
          // animation is running.
          (typeof jQuery === 'undefined' || (jQuery.active === 0 && jQuery(':animated').length === 0)) &&
          (typeof Drupal === 'undefined' || typeof Drupal.ajax === 'undefined' || !Drupal.ajax.instances.some(isAjaxing))
        );
      }());
JS;
    $result = $this->session->wait($timeout, $condition);
    if (!$result) {
      throw new \RuntimeException($message);
    }
  }

  /**
   * Waits for the specified selector and returns it when available.
   *
   * @param string $selector
   *   The selector engine name. See ElementInterface::findAll() for the
   *   supported selectors.
   * @param string|array $locator
   *   The selector locator.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The page element node if found, NULL if not.
   *
   * @see \Behat\Mink\Element\ElementInterface::findAll()
   */
  public function waitForElement($selector, $locator, $timeout = 10000) {
    $page = $this->session->getPage();

    $result = $page->waitFor($timeout / 1000, function () use ($page, $selector, $locator) {
      return $page->find($selector, $locator);
    });

    return $result;
  }

  /**
   * Waits for the specified selector and returns it when available and visible.
   *
   * @param string $selector
   *   The selector engine name. See ElementInterface::findAll() for the
   *   supported selectors.
   * @param string|array $locator
   *   The selector locator.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The page element node if found and visible, NULL if not.
   *
   * @see \Behat\Mink\Element\ElementInterface::findAll()
   */
  public function waitForElementVisible($selector, $locator, $timeout = 10000) {
    $page = $this->session->getPage();

    $result = $page->waitFor($timeout / 1000, function () use ($page, $selector, $locator) {
      $element = $page->find($selector, $locator);
      if (!empty($element) && $element->isVisible()) {
        return $element;
      }
      return NULL;
    });

    return $result;
  }

  /**
   * Waits for a button (input[type=submit|image|button|reset], button) with
   * specified locator and returns it.
   *
   * @param string $locator
   *   The button ID, value or alt string.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The page element node if found, NULL if not.
   */
  public function waitForButton($locator, $timeout = 10000) {
    return $this->waitForElement('named', ['button', $locator], $timeout);
  }

  /**
   * Waits for a link with specified locator and returns it when available.
   *
   * @param string $locator
   *   The link ID, title, text or image alt.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The page element node if found, NULL if not.
   */
  public function waitForLink($locator, $timeout = 10000) {
    return $this->waitForElement('named', ['link', $locator], $timeout);
  }

  /**
   * Waits for a field with specified locator and returns it when available.
   *
   * @param string $locator
   *   The input ID, name or label for the field (input, textarea, select).
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The page element node if found, NULL if not.
   */
  public function waitForField($locator, $timeout = 10000) {
    return $this->waitForElement('named', ['field', $locator], $timeout);
  }

  /**
   * Waits for an element by its id and returns it when available.
   *
   * @param string $id
   *   The element ID.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The page element node if found, NULL if not.
   */
  public function waitForId($id, $timeout = 10000) {
    return $this->waitForElement('named', ['id', $id], $timeout);
  }

  /**
   * Waits for the jQuery autocomplete delay duration.
   *
   * @see https://api.jqueryui.com/autocomplete/#option-delay
   */
  public function waitOnAutocomplete() {
    // Wait for the autocomplete to be visible.
    return $this->waitForElementVisible('css', '.ui-autocomplete li');
  }

  /**
   * Test that a node, or its specific corner, is visible in the viewport.
   *
   * Note: Always set the viewport size. This can be done with a PhantomJS
   * startup parameter or in your test with \Behat\Mink\Session->resizeWindow().
   * Drupal CI Javascript tests by default use a viewport of 1024x768px.
   *
   * @param string $selector_type
   *   The element selector type (CSS, XPath).
   * @param string|array $selector
   *   The element selector. Note: the first found element is used.
   * @param bool|string $corner
   *   (Optional) The corner to test:
   *   topLeft, topRight, bottomRight, bottomLeft.
   *   Or FALSE to check the complete element (default).
   * @param string $message
   *   (optional) A message for the exception.
   *
   * @throws \Behat\Mink\Exception\ElementHtmlException
   *   When the element doesn't exist.
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the element is not visible in the viewport.
   */
  public function assertVisibleInViewport($selector_type, $selector, $corner = FALSE, $message = 'Element is not visible in the viewport.') {
    $node = $this->session->getPage()->find($selector_type, $selector);
    if ($node === NULL) {
      if (is_array($selector)) {
        $selector = implode(' ', $selector);
      }
      throw new ElementNotFoundException($this->session->getDriver(), 'element', $selector_type, $selector);
    }

    // Check if the node is visible on the page, which is a prerequisite of
    // being visible in the viewport.
    if (!$node->isVisible()) {
      throw new ElementHtmlException($message, $this->session->getDriver(), $node);
    }

    $result = $this->checkNodeVisibilityInViewport($node, $corner);

    if (!$result) {
      throw new ElementHtmlException($message, $this->session->getDriver(), $node);
    }
  }

  /**
   * Test that a node, or its specific corner, is not visible in the viewport.
   *
   * Note: the node should exist in the page, otherwise this assertion fails.
   *
   * @param string $selector_type
   *   The element selector type (CSS, XPath).
   * @param string|array $selector
   *   The element selector. Note: the first found element is used.
   * @param bool|string $corner
   *   (Optional) Corner to test: topLeft, topRight, bottomRight, bottomLeft.
   *   Or FALSE to check the complete element (default).
   * @param string $message
   *   (optional) A message for the exception.
   *
   * @throws \Behat\Mink\Exception\ElementHtmlException
   *   When the element doesn't exist.
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the element is not visible in the viewport.
   *
   * @see \Drupal\FunctionalJavascriptTests\JSWebAssert::assertVisibleInViewport()
   */
  public function assertNotVisibleInViewport($selector_type, $selector, $corner = FALSE, $message = 'Element is visible in the viewport.') {
    $node = $this->session->getPage()->find($selector_type, $selector);
    if ($node === NULL) {
      if (is_array($selector)) {
        $selector = implode(' ', $selector);
      }
      throw new ElementNotFoundException($this->session->getDriver(), 'element', $selector_type, $selector);
    }

    $result = $this->checkNodeVisibilityInViewport($node, $corner);

    if ($result) {
      throw new ElementHtmlException($message, $this->session->getDriver(), $node);
    }
  }

  /**
   * Check the visibility of a node, or its specific corner.
   *
   * @param \Behat\Mink\Element\NodeElement $node
   *   A valid node.
   * @param bool|string $corner
   *   (Optional) Corner to test: topLeft, topRight, bottomRight, bottomLeft.
   *   Or FALSE to check the complete element (default).
   *
   * @return bool
   *   Returns TRUE if the node is visible in the viewport, FALSE otherwise.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   When an invalid corner specification is given.
   */
  private function checkNodeVisibilityInViewport(NodeElement $node, $corner = FALSE) {
    $xpath = $node->getXpath();

    // Build the Javascript to test if the complete element or a specific corner
    // is in the viewport.
    switch ($corner) {
      case 'topLeft':
        $test_javascript_function = <<<JS
          function t(r, lx, ly) {
            return (
              r.top >= 0 &&
              r.top <= ly &&
              r.left >= 0 &&
              r.left <= lx
            )
          }
JS;
        break;

      case 'topRight':
        $test_javascript_function = <<<JS
          function t(r, lx, ly) {
            return (
              r.top >= 0 &&
              r.top <= ly &&
              r.right >= 0 &&
              r.right <= lx
            );
          }
JS;
        break;

      case 'bottomRight':
        $test_javascript_function = <<<JS
          function t(r, lx, ly) {
            return (
              r.bottom >= 0 &&
              r.bottom <= ly &&
              r.right >= 0 &&
              r.right <= lx
            );
          }
JS;
        break;

      case 'bottomLeft':
        $test_javascript_function = <<<JS
          function t(r, lx, ly) {
            return (
              r.bottom >= 0 &&
              r.bottom <= ly &&
              r.left >= 0 &&
              r.left <= lx
            );
          }
JS;
        break;

      case FALSE:
        $test_javascript_function = <<<JS
          function t(r, lx, ly) {
            return (
              r.top >= 0 &&
              r.left >= 0 &&
              r.bottom <= ly &&
              r.right <= lx
            );
          }
JS;
        break;

      // Throw an exception if an invalid corner parameter is given.
      default:
        throw new UnsupportedDriverActionException($corner, $this->session->getDriver());
    }

    // Build the full Javascript test. The shared logic gets the corner
    // specific test logic injected.
    $full_javascript_visibility_test = <<<JS
      (function(t){
        var w = window,
        d = document,
        e = d.documentElement,
        n = d.evaluate("$xpath", d, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue,
        r = n.getBoundingClientRect(),
        lx = (w.innerWidth || e.clientWidth),
        ly = (w.innerHeight || e.clientHeight);

        return t(r, lx, ly);
      }($test_javascript_function));
JS;

    // Check the visibility by injecting and executing the full Javascript test
    // script in the page.
    return $this->session->evaluateScript($full_javascript_visibility_test);
  }

}

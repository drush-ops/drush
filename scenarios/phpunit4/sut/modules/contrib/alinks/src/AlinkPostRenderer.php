<?php

namespace Drupal\alinks;

use Drupal\alinks\Entity\Keyword;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Wamania\Snowball\English;

/**
 * Class AlinkPostRenderer.
 */
class AlinkPostRenderer {

  protected $content;

  protected $keywords;

  protected $existingLinks;

  /**
   * Stemmer.
   *
   * @var \Wamania\Snowball\Stem
   */
  protected $stemmer;

  protected $stemmerCache = [];

  protected $xpathSelector = "//text()[not(ancestor::a) and not(ancestor::script) and not(ancestor::*[@data-alink-ignore])]";

  /**
   * AlinkPostRenderer constructor.
   *
   * @param \Drupal\Core\Render\Markup $content
   *    The content of the current page.
   * @param array $context
   *    The current page context.
   * @param null $xpathSelector
   *    The selector rule for the html.
   */
  public function __construct($content, $context = NULL, $xpathSelector = NULL) {

    if (!empty($context['#entity_type']) && !empty($context['#' . $context['#entity_type']])) {
      $entity = $context['#' . $context['#entity_type']];
      $class = 'Wamania\Snowball\\' . $entity->language()->getName();
      if (class_exists($class)) {
        $this->stemmer = new $class();
      }
      else {
        $this->stemmer = new English();
      }
    }

    $this->content = $content;
    if ($xpathSelector) {
      $this->xpathSelector = $xpathSelector;
    }
  }

  /**
   * Load alinks keywords.
   *
   * @return \Drupal\alinks\Entity\Keyword[]
   *   Returns a list of all of the alinks keywords.
   */
  protected function getKeywords() {
    if ($this->keywords === NULL) {
      $ids = \Drupal::entityQuery('alink_keyword')
        ->condition('status', 1)
        ->execute();
      $this->keywords = Keyword::loadMultiple($ids);

      $vocabularies = \Drupal::config('alinks.settings')->get('vocabularies');

      if ($vocabularies) {
        $terms = \Drupal::entityQuery('taxonomy_term')
          ->condition('vid', $vocabularies, 'IN')
          ->execute();

        $terms = Term::loadMultiple($terms);
        foreach ($terms as $term) {
          $this->keywords[] = Keyword::create([
            'name' => $term->label(),
            'link' => [
              'uri' => 'internal:/' . $term->toUrl()->getInternalPath(),
            ],
          ]);
        }
      }

      foreach ($this->keywords as &$keyword) {
        $keyword->stemmed_keyword = $this->stemmer->stem($keyword->getText());
      }
    }
    return $this->keywords;
  }

  /**
   * Set keywords and stemmed keywords.
   *
   * @param mixed $keywords
   *    A list of all of the keywords to set.
   */
  public function setKeywords($keywords) {
    $this->keywords = $keywords;
    foreach ($this->keywords as &$keyword) {
      $keyword->stemmed_keyword = $this->stemmer->stem($keyword->getText());
    }
  }

  /**
   * Load the content and replace the matched strings with automatic links.
   */
  public function replace() {
    $dom = Html::load($this->content);
    $xpath = new \DOMXPath($dom);

    $this->existingLinks = $this->extractExistingLinks($xpath);
    $this->keywords = array_filter($this->getKeywords(), function (Keyword $word) {
      return !isset($this->existingLinks[$word->getUrl()]);
    });

    foreach ($xpath->query($this->xpathSelector) as $node) {
      $text = $node->wholeText;
      $replace = FALSE;
      if (empty(trim($text))) {
        continue;
      }
      foreach ($this->keywords as $key => $word) {

        // @TODO: Make it configurable replaceAll vs. replaceFirst
        $text = $this->replaceFirst($word, '<a href="' . $word->getUrl() . '">' . $word->getText() . '</a>', $text, $count);
        if ($count) {
          $replace = TRUE;
          $this->addExistingLink($word);
          break;
        }
      }
      if ($replace) {
        $this->replaceNodeContent($node, $text);
      }
    }

    return Html::serialize($dom);
  }

  /**
   * Process the node list to replace links.
   */
  protected function processDomNodeList($element) {
    foreach ($element as $item) {
      if ($item instanceof \DOMElement) {
        if ($item->hasChildNodes()) {
          foreach ($item->childNodes as $childNode) {
            if ($childNode instanceof \DOMText) {
              foreach ($this->getKeywords() as $word) {

                // @TODO: Make it configurable replaceAll vs. replaceFirst
                $childNode->nodeValue = $this->replaceFirst($word, '<a href="' . $word->getUrl() . '">' . $word->getText() . '</a>', $childNode->nodeValue);
              }
            }
          }
        }
      }
    }

    return $element;
  }

  /**
   *
   */
  protected function replaceAll(Keyword $search, $replace, $subject, &$count = 0) {
    $subject = str_replace($search->getText(), $replace, $subject, $count);
    if ($count == 0) {
      // @todo: Try stemmer
    }
    return $subject;
  }

  /**
   * Uses regular expression to replace the first matched keyword in content.
   */
  protected function replaceFirst(Keyword $search, $replace, $subject, &$count = 0) {
    $search_escaped = preg_quote($search->getText(), '/');
    $subject = preg_replace('/\b' . $search_escaped . '\b/u', $replace, $subject, 1, $count);
    if ($count == 0) {

      // @TODO: Look at Search API Tokenizer & Highlighter
      $terms = str_replace(['.', ',', ';', '!', '?'], '', $subject);
      $terms = explode(' ', $terms);
      $terms = array_filter(array_map('trim', $terms));
      $terms = array_combine($terms, $terms);
      $terms = array_map(function ($term) {
        if (!isset($this->stemmerCache[$term])) {
          $this->stemmerCache[$term] = $this->stemmer->stem($term);
        }
        return $this->stemmerCache[$term];
      }, $terms);
      foreach ($terms as $original_term => $term) {
        if ($term === $search->stemmed_keyword) {
          $search_escaped = preg_quote($original_term, '/');
          $subject = preg_replace('/\b' . $search_escaped . '\b/u', '<a href="' . $search->getUrl() . '">' . $original_term . '</a>', $subject, 1, $count);
        }
      }
    }

    return $subject;
  }

  /**
   *
   */
  public static function postRender($content, $context) {
    $selector = \Drupal::config('alinks.settings')->get('xpathSelector');
    $renderer = new static($content, $context, $selector);

    return $renderer->replace();
  }

  /**
   * Normalize the URLs with front links and internal links.
   *
   * @param string $uri
   *   A url to be normalized.
   *
   * @return string
   *    The normalized URL.
   */
  protected function normalizeUri($uri) {

    // If we already have a scheme, we're fine.
    if (empty($uri) || !is_null(parse_url($uri, PHP_URL_SCHEME))) {

      return $uri;
    }

    // Remove the <front> component of the URL.
    if (strpos($uri, '<front>') === 0) {
      $uri = substr($uri, strlen('<front>'));
    }

    // Add the internal: scheme and ensure a leading slash.
    return 'internal:/' . ltrim($uri, '/');
  }

  /**
   * Extract all of the links in an xpath query.
   *
   * @param string $xpath
   *   An xpath match to parse for links.
   *
   * @return array
   *    Unique links from an xpath.
   */
  protected function extractExistingLinks($xpath) {

    // @TODO: Remove keywords with links which are already in the text
    $links = [];

    foreach ($xpath->query('//a') as $link) {
      try {
        $uri = $this->normalizeUri($link->getAttribute('href'));
        $links[] = Url::fromUri($uri)->toString();
      }
      catch (\Exception $exception) {
        // Do nothing.
      }
    }

    return array_flip(array_unique($links));
  }


  /**
   * Check to see if keywords on this object match the passed word.
   *
   * @param \Drupal\alinks\Entity\Keyword $word
   *   An individual keyword.
   */
  protected function addExistingLink(Keyword $word) {
    $this->existingLinks[$word->getUrl()] = TRUE;
    $this->keywords = array_filter($this->keywords, function ($keyword) use ($word) {
      if ($keyword->getText() == $word->getText()) {

        return FALSE;
      }
      if ($keyword->getUrl() == $word->getUrl()) {

        return FALSE;
      }

      return TRUE;
    });
  }

  /**
   * Replace the contents of a DOMNode.
   *
   * @param \DOMNode $node
   *   A DOMNode object.
   * @param string $content
   *   The text or HTML that will replace the contents of $node.
   */
  protected function replaceNodeContent(\DOMNode &$node, $content) {
    if (strlen($content)) {

      // Load the content into a new DOMDocument and retrieve the DOM nodes.
      $replacement_nodes = Html::load($content)->getElementsByTagName('body')
        ->item(0)
        ->childNodes;
    }
    else {
      $replacement_nodes = [$node->ownerDocument->createTextNode('')];
    }

    foreach ($replacement_nodes as $replacement_node) {

      // Import the replacement node from the new DOMDocument into the original
      // one, importing also the child nodes of the replacement node.
      $replacement_node = $node->ownerDocument->importNode($replacement_node, TRUE);
      $node->parentNode->insertBefore($replacement_node, $node);
    }
    $node->parentNode->removeChild($node);
  }

}

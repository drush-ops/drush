<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Class DrupalDataCollector
 */
class DrupalDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  private $redirectDestination;

  /**
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  private $urlGenerator;

  /**
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirectDestination
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   */
  public function __construct(RedirectDestinationInterface $redirectDestination, UrlGeneratorInterface $urlGenerator) {
    $this->redirectDestination = $redirectDestination;
    $this->urlGenerator = $urlGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    $this->data['version'] = Drupal::VERSION;
    $this->data['profile'] = drupal_get_profile();
    $this->data['config_url'] = (new Drupal\Core\Url('webprofiler.settings', [], ['query' => $this->redirectDestination->getAsArray()]))->toString();

    try {
      $process = new Process("git log -1 --pretty=format:'%H - %s (%ci)' --abbrev-commit");
      $process->setTimeout(3600);
      $process->mustRun();
      $this->data['git_commit'] = $process->getOutput();

      $process = new Process("git log -1 --pretty=format:'%h' --abbrev-commit");
      $process->setTimeout(3600);
      $process->mustRun();
      $this->data['abbr_git_commit'] = $process->getOutput();
    } catch (ProcessFailedException $e) {
      $this->data['git_commit'] = $this->data['git_commit_abbr'] = NULL;
    } catch (RuntimeException $e) {
      $this->data['git_commit'] = $this->data['git_commit_abbr'] = NULL;
    }
  }

  /**
   * @return string
   */
  public function getVersion() {
    return $this->data['version'];
  }

  /**
   * @return string
   */
  public function getProfile() {
    return $this->data['profile'];
  }

  /**
   * @return string
   */
  public function getConfigUrl() {
    return $this->data['config_url'];
  }

  /**
   * @return string
   */
  public function getGitCommit() {
    return $this->data['git_commit'];
  }

  /**
   * @return string
   */
  public function getAbbrGitCommit() {
    return $this->data['abbr_git_commit'];
  }

  /**
   * Returns the name of the collector.
   *
   * @return string The collector name
   *
   * @api
   */
  public function getName() {
    return 'drupal';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Drupal');
  }

  /**
   * {@inheritdoc}
   */
  public function hasPanel() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABwAAAAcCAYAAAByDd+UAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyRpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNS4xIE1hY2ludG9zaCIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDpCRDU5MUVDNzVGRTkxMUUzODdBMEJDOEZFRjA2QUY5MiIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDpCRDU5MUVDODVGRTkxMUUzODdBMEJDOEZFRjA2QUY5MiI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOkJENTkxRUM1NUZFOTExRTM4N0EwQkM4RkVGMDZBRjkyIiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOkJENTkxRUM2NUZFOTExRTM4N0EwQkM4RkVGMDZBRjkyIi8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+iYsuIgAAAiNJREFUeNq8lj3KwkAQhhOxUPAnoIgWQiysbFLYG2/gEVJaegQ9QfQECh4g3kA9QbxBLMQ2KaxE2G/ffG5Yl/ytiguDJBn32Z15d2dUQojy0wGgDHQwGFjj8Vh7hxFyZIHNZtOt1+uuDPRtYK/Xs4rFIpyJDFQaiIm73a7NYMyq1arf7/dnWWApIN3VHBPzINHwHX5J4FxAKg6j0Wh4aSDREGb8TxqIXJVKJSIDY1Yul30RmgqE87swHkrDq2cCkYNareZ9AmPWarX2mUAk/hswZrgkUoGyIsm7y1ggDachnrNPrVKpEKSJBxZYnC+Xi/l4PL56T99uN/y8KDYCUpj2i2JRzONkWZYyGo0UXdeV4/GobDYb5Xw+J/obxv+mTqdT8rWTpND1ek3ihm3bRNO0F188O44T+UwmE+TQjBXNcDiciLD5fE7Shuu6xDTN0JdGgfi+//J9v98nA6EmqIoHYsJPxuFwIOKxiERDVxNQ4I4PNw1Ram6DIFAWiwUWG5uv+/2+E98V+IdOp7OiZzF6pitMhC2XyxDEfOKg1+t1ldnTtNtthxcB8sAGcgQRUbWGufM8LxQPfJBv3n+73Tq5qgVyibqWdYsASOUfLYyJJ679yKyHadDZbBYqkn8HMHaO3ydMl674rI8RayOACKUo/+l0SuAf12ZI9TRYLSZCJYk76GgbnyA9TxOlMpiqqrnuQhxk7igd8raJbKi/bvX/BBgANANSieJk+XMAAAAASUVORK5CYII=';
  }
}

<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\Console\Completion\CompletionInput;

/**
 * Helpers shared by the image commands.
 *
 * Classes using this trait inject EntityTypeManagerInterface as
 * $this->entityTypeManager and ModuleHandlerInterface as $this->moduleHandler.
 */
trait ImageTrait
{
    use StringTranslationTrait;

    /**
     * Throws when the image module, which provides image styles, is not installed.
     */
    protected function requireImageModule(): void
    {
        if (!$this->moduleHandler->moduleExists('image')) {
            throw new \InvalidArgumentException((string) $this->t('The image module is not installed.'));
        }
    }

    /**
     * Loads an image style or throws when no style has the given machine name.
     */
    protected function loadStyle(string $name): ImageStyleInterface
    {
        return $this->loadStyles([$name])[$name];
    }

    /**
     * Loads image styles, naming every machine name that did not load.
     *
     * @return ImageStyleInterface[]
     *   The styles, keyed by machine name.
     */
    protected function loadStyles(array $names): array
    {
        $styles = $this->entityTypeManager->getStorage('image_style')->loadMultiple($names);
        if ($missing = array_diff($names, array_keys($styles))) {
            throw new \InvalidArgumentException((string) $this->t('Unable to load the image_style: @names', ['@names' => implode(', ', $missing)]));
        }
        return $styles;
    }

    /**
     * Machine names of every image style on the site.
     */
    protected function allStyleNames(): array
    {
        return array_keys($this->entityTypeManager->getStorage('image_style')->loadMultiple());
    }

    /**
     * Shell completion for image style machine name arguments.
     */
    public function suggestStyleNames(CompletionInput $input): array
    {
        return $this->allStyleNames();
    }
}

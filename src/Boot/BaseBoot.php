<?php

declare(strict_types=1);

namespace Drush\Boot;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class BaseBoot implements Boot, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected string|bool $uri = false;
    protected int $phase = DrupalBootLevels::NONE;

    public function __construct()
    {
        register_shutdown_function([$this, 'terminate']);
    }

    public function findUri($root, $uri): string
    {
        return 'default';
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function setUri($uri): void
    {
        $this->uri = $uri;
    }

    public function getPhase(): int
    {
        return $this->phase;
    }

    public function setPhase(int $phase): void
    {
        $this->phase = $phase;
    }

    public function validRoot(?string $path): bool
    {
    }

    public function getVersion($root)
    {
    }

    public function commandDefaults()
    {
    }

    public function reportCommandError($command)
    {
        // No longer used.
    }

    public function bootstrapPhaseMap(): array
    {
        return [
            'none' => DrupalBootLevels::NONE,
            'drush' => DrupalBootLevels::NONE,
            'max' => DrupalBootLevels::MAX,
            'root' => DrupalBootLevels::ROOT,
            'site' => DrupalBootLevels::SITE,
            'configuration' => DrupalBootLevels::CONFIGURATION,
            'database' => DrupalBootLevels::DATABASE,
            'full' => DrupalBootLevels::FULL
        ];
    }

    public function lookUpPhaseIndex($phase): ?int
    {
        if (is_numeric($phase)) {
            return (int) $phase;
        }
        $phaseMap = $this->bootstrapPhaseMap();
        if (isset($phaseMap[$phase])) {
            return $phaseMap[$phase];
        }

        if ((!str_starts_with($phase, 'DRUSH_BOOTSTRAP_')) || (!defined($phase))) {
            return null;
        }
        return constant($phase);
    }

    public function bootstrapDrush()
    {
    }

    protected function hasRegisteredSymfonyCommand($application, $name): bool
    {
        try {
            $application->get($name);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function terminate()
    {
    }
}

<?php

/**
 * @file
 * Definition of Drush\Command\DrushInputAdapter.
 */

namespace Drush\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * Adapter for Symfony Console InputInterface
 *
 * This class can serve as a stand-in wherever an InputInterface
 * is needed.  It calls through to ordinary Drush procedural functions.
 * This object should not be used directly; it exists only in
 * the Drush 8.x branch.
 *
 * We use this class rather than using an ArrayInput for two reasons:
 * 1) We do not want to convert our options array back to '--option=value'
 *    or '--option value' just to have them re-parsed again.
 * 2) We do not want Symfony to attempt to validate our options or arguments
 *    for us.
 */
class DrushInputAdapter implements InputInterface
{
    protected $arguments;
    protected $options;
    protected $interactive;

    public function __construct($arguments, $options, $command = false, $interactive = true)
    {
        $this->arguments = $arguments;
        $this->options = $options;

        // If a command name is provided as a parameter, then push
        // it onto the front of the arguments list as a service
        if ($command) {
            $this->arguments = array_merge(
                [ 'command' => $command ],
                $this->arguments
            );
        }
        // Is it interactive, or is it not interactive?
        // Call drush_get_option() here if value not passed in?
        $this->interactive = $interactive;
    }

    /**
     *  {@inheritdoc}
     */
    public function getFirstArgument(): ?string
    {
        return reset($this->arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameterOption($values, $onlyParams = false): bool
    {
        $values = (array) $values;

        foreach ($values as $value) {
            if (array_key_exists($value, $this->options)) {
                return true;
            }
        }

        return false;
    }

    /**
     *  {@inheritdoc}
     */
    public function getParameterOption($values, $default = false, $onlyParams = false): mixed
    {
        $values = (array) $values;

        foreach ($values as $value) {
            if (array_key_exists($value, $this->options)) {
                return $this->getOption($value);
            }
        }

        return $default;
    }

    /**
     *  {@inheritdoc}
     */
    public function bind(InputDefinition $definition): void
    {
        // no-op: this class exists to avoid validation
    }

    /**
     *  {@inheritdoc}
     */
    public function validate(): void
    {
        // no-op: this class exists to avoid validation
    }

    /**
     *  {@inheritdoc}
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     *  {@inheritdoc}
     */
    public function getArgument($name): mixed
    {
        // TODO: better to throw if an argument that does not exist is requested?
        return isset($this->arguments[$name]) ? $this->arguments[$name] : '';
    }

    /**
     *  {@inheritdoc}
     */
    public function setArgument($name, $value): void
    {
        $this->arguments[$name] = $value;
    }

    /**
     *  {@inheritdoc}
     */
    public function hasArgument($name): bool
    {
        return isset($this->arguments[$name]);
    }

    /**
     *  {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     *  {@inheritdoc}
     */
    public function getOption($name): mixed
    {
        return $this->options[$name];
    }

    /**
     *  {@inheritdoc}
     */
    public function setOption($name, $value): void
    {
        $this->options[$name] = $value;
    }

    /**
     *  {@inheritdoc}
     */
    public function hasOption($name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     *  {@inheritdoc}
     */
    public function isInteractive(): bool
    {
        return $this->interactive;
    }

    /**
     *  {@inheritdoc}
     */
    public function setInteractive($interactive): void
    {
        $this->interactive = $interactive;
    }

    public function __toString(): string
    {
        $tokens = [];

        foreach ($this->arguments as $arg) {
            if (is_array($arg)) {
                foreach ($arg as $val) {
                    $tokens[] = escapeshellarg((string) $val);
                }
            } elseif ($arg !== '' && $arg !== null) {
                $tokens[] = escapeshellarg((string) $arg);
            }
        }

        foreach ($this->options as $key => $val) {
            if ($val === true) {
                $tokens[] = '--' . $key;
            } elseif ($val === false || $val === null) {
                continue;
            } elseif (is_array($val)) {
                foreach ($val as $v) {
                    $tokens[] = '--' . $key . '=' . escapeshellarg((string) $v);
                }
            } else {
                $tokens[] = '--' . $key . '=' . escapeshellarg((string) $val);
            }
        }

        return implode(' ', $tokens);
    }
}

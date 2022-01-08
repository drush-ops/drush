<?php

namespace Drush\Symfony;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * UnvalidatedArgvInput is an ArgvInput that never reports errors when
 * extra options are provided.
 *
 * If the last argument of the command being called is not an array
 * argument, then an error will be thrown if there are too many arguments.
 *
 * We use this instead of a IndiscriminateInputDefinition in cases where we
 * know in advance that we wish to disable input validation for all commands.
 * In contrast, an IndiscriminateInputDefinition is attached to individual
 * Commands that should accept any option.
 */
class LessStrictArgvInput extends ArgvInput
{
    private $tokens;
    private $parsed;
    protected $additionalOptions = [];

    /**
     * Constructor.
     *
     * @param array|null           $argv       An array of parameters from the CLI (in the argv format)
     * @param InputDefinition|null $definition A InputDefinition instance
     */
    public function __construct(array $argv = null, InputDefinition $definition = null)
    {
        // We have to duplicate the implementation of ArgvInput
        // because of liberal use of `private`
        if (null === $argv) {
            $argv = $_SERVER['argv'];
        }

        $this->tokens = $argv;
        // strip the application name
        array_shift($this->tokens);

        parent::__construct($argv, $definition);
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name)
    {
        if (array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }
        if ($this->definition->hasOption($name)) {
            return $this->definition->getOption($name)->getDefault();
        }
        return false;
    }

    protected function setTokens(array $tokens): void
    {
        $this->tokens = $tokens;
    }

    /**
     * {@inheritdoc}
     */
    protected function parse(): void
    {
        $parseOptions = true;
        $this->parsed = $this->tokens;
        while (null !== $token = array_shift($this->parsed)) {
            if ($parseOptions && '' == $token) {
                $this->parseArgument($token);
            } elseif ($parseOptions && '--' == $token) {
                $parseOptions = false;
            } elseif ($parseOptions && 0 === strpos($token, '--')) {
                $this->parseLongOption($token);
            } elseif ($parseOptions && '-' === $token[0] && '-' !== $token) {
                $this->parseShortOption($token);
            } else {
                $this->parseArgument($token);
            }
        }
        // Put back any options that were injected.
        $this->options += $this->additionalOptions;
    }

    /**
     * Parses a short option.
     *
     * @param string $token The current token
     */
    private function parseShortOption($token): void
    {
        $name = substr($token, 1);

        if (strlen($name) > 1) {
            if ($this->definition->hasShortcut($name[0]) && $this->definition->getOptionForShortcut($name[0])->acceptValue()) {
                // an option with a value (with no space)
                $this->addShortOption($name[0], substr($name, 1));
            } else {
                $this->parseShortOptionSet($name);
            }
        } else {
            $this->addShortOption($name, null);
        }
    }

    /**
     * Parses a short option set.
     *
     * @param string $name The current token
     */
    private function parseShortOptionSet($name): void
    {
        $len = strlen($name);
        for ($i = 0; $i < $len; ++$i) {
            if (!$this->definition->hasShortcut($name[$i])) {
                $this->addShortOption($name[$i]);
            }

            $option = $this->definition->getOptionForShortcut($name[$i]);
            if ($option->acceptValue()) {
                $this->addLongOption($option->getName(), $i === $len - 1 ? null : substr($name, $i + 1));

                break;
            } else {
                $this->addLongOption($option->getName(), null);
            }
        }
    }

    /**
     * Parses a long option.
     *
     * @param string $token The current token
     */
    private function parseLongOption($token): void
    {
        $name = substr($token, 2);

        if (false !== $pos = strpos($name, '=')) {
            if (0 === strlen($value = substr($name, $pos + 1))) {
                // if no value after "=" then substr() returns "" since php7 only, false before
                // see http://php.net/manual/fr/migration70.incompatible.php#119151
                if (\PHP_VERSION_ID < 70000 && false === $value) {
                    $value = '';
                }
                array_unshift($this->parsed, $value);
            }
            $this->addLongOption(substr($name, 0, $pos), $value);
        } else {
            $this->addLongOption($name, null);
        }
    }

    /**
     * Parses an argument.
     *
     * @param string $token The current token
     *
     * @throws RuntimeException When too many arguments are given
     */
    private function parseArgument($token): void
    {
        $c = count($this->arguments);

        // if input is expecting another argument, add it
        if ($this->definition->hasArgument($c)) {
            $arg = $this->definition->getArgument($c);
            $this->arguments[$arg->getName()] = $arg->isArray() ? [$token] : $token;

        // if last argument isArray(), append token to last argument
        } elseif ($this->definition->hasArgument($c - 1) && $this->definition->getArgument($c - 1)->isArray()) {
            $arg = $this->definition->getArgument($c - 1);
            $this->arguments[$arg->getName()][] = $token;

        // unexpected argument
        } else {
            $all = $this->definition->getArguments();
            if (count($all)) {
                throw new RuntimeException(sprintf('Too many arguments, expected arguments "%s", provided arguments "%s".', implode('" "', array_keys($all)), implode('" "', array_keys($this->arguments))));
            }

            throw new RuntimeException(sprintf('No arguments expected, got "%s".', $token));
        }
    }

    /**
     * Adds a short option value.
     *
     * @param string $shortcut The short option key
     * @param mixed  $value    The value for the option
     *
     * @throws RuntimeException When option given doesn't exist
     */
    private function addShortOption($shortcut, $value): void
    {
        if (!$this->definition->hasShortcut($shortcut)) {
            // Hard to know what to do with unknown short options. Maybe
            // these should be added to the end of the arguments. This would only
            // be a good strategy if the last argument was an array argument.
            // We'll try adding as a long option for now.
            $this->addLongOption($shortcut, $value);
        }

        $this->addLongOption($this->definition->getOptionForShortcut($shortcut)->getName(), $value);
    }

    public function injectAdditionalOptions($additionalOptions): void
    {
        $this->additionalOptions += $additionalOptions;
        $this->options += $additionalOptions;
    }

    /**
     * Adds a long option value.
     *
     * @param string $name  The long option key
     * @param mixed  $value The value for the option
     *
     * @throws RuntimeException When option given doesn't exist
     */
    private function addLongOption($name, $value): void
    {
        if (!$this->definition->hasOption($name)) {
            // If we don't know anything about this option, then we'll
            // assume it is generic.
            $this->options[$name] = $value;
            return;
        }

        $option = $this->definition->getOption($name);

        if (null !== $value && !$option->acceptValue()) {
            throw new RuntimeException(sprintf('The "--%s" option does not accept a value.', $name));
        }

        if (in_array($value, ['', null], true) && $option->acceptValue() && count($this->parsed)) {
            // if option accepts an optional or mandatory argument
            // let's see if there is one provided
            $next = array_shift($this->parsed);
            if ((isset($next[0]) && '-' !== $next[0]) || in_array($next, ['', null], true)) {
                $value = $next;
            } else {
                array_unshift($this->parsed, $next);
            }
        }

        if (null === $value) {
            if ($option->isValueRequired()) {
                throw new RuntimeException(sprintf('The "--%s" option requires a value.', $name));
            }

            if (!$option->isArray() && !$option->isValueOptional()) {
                $value = true;
            }
        }

        if ($option->isArray()) {
            $this->options[$name][] = $value;
        } else {
            $this->options[$name] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstArgument()
    {
        foreach ($this->tokens as $token) {
            if ($token && '-' === $token[0]) {
                continue;
            }

            return $token;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameterOption($values, $onlyParams = false): bool
    {
        $values = (array) $values;

        foreach ($this->tokens as $token) {
            if ($onlyParams && $token === '--') {
                return false;
            }
            foreach ($values as $value) {
                if ($token === $value || 0 === strpos($token, $value . '=')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterOption($values, $default = false, $onlyParams = false)
    {
        $values = (array) $values;
        $tokens = $this->tokens;

        while (0 < count($tokens)) {
            $token = array_shift($tokens);
            if ($onlyParams && $token === '--') {
                return false;
            }

            foreach ($values as $value) {
                if ($token === $value || 0 === strpos($token, $value . '=')) {
                    if (false !== $pos = strpos($token, '=')) {
                        return substr($token, $pos + 1);
                    }

                    return array_shift($tokens);
                }
            }
        }

        return $default;
    }

    /**
     * Returns a stringified representation of the args passed to the command.
     *
     * @return string
     */
    public function __toString()
    {
        $tokens = array_map(function ($token) {
            if (preg_match('{^(-[^=]+=)(.+)}', $token, $match)) {
                return $match[1] . $this->escapeToken($match[2]);
            }

            if ($token && $token[0] !== '-') {
                return $this->escapeToken($token);
            }

            return $token;
        }, $this->tokens);

        return implode(' ', $tokens);
    }
}

<?php

declare(strict_types=1);

namespace Drush\TestTraits;

/**
 * OutputUtilsTrait provides some useful utility methods for test classes
 * that define `getOutputRaw()` and `getErrorOutputRaw()` methods.
 *
 * This trait is used by CliTestTrait and DrushTestTrait.
 */
trait OutputUtilsTrait
{
    /**
     * Accessor for the last output, non-trimmed.
     */
    abstract public function getOutputRaw(): string;

    /**
     * Accessor for the last stderr output, non-trimmed.
     */
    abstract public function getErrorOutputRaw(): string;

    /**
     * Get command output and simplify things like full paths and extra whitespace.
     */
    protected function getSimplifiedOutput(): string
    {
        return $this->simplifyOutput($this->getOutput());
    }

    /**
     * Returns a simplified version of the error output to facilitate testing.
     *
     * @return string
     *   A simplified version of the error output that has things like full
     *   paths and superfluous whitespace removed from it.
     */
    protected function getSimplifiedErrorOutput(): string
    {
        return $this->simplifyOutput($this->getErrorOutput());
    }

    /**
     * Remove things like full paths and extra whitespace from the given string.
     */
    protected function simplifyOutput(string $output): string
    {
        // We do not care if Drush inserts a -t or not in the string. Depends on whether there is a tty.
        $output = preg_replace('# -t #', ' ', $output);
        // Remove multiple blank lines
        $output = preg_replace("#\n\n\n*#m", "\n\n", $output);
        // Remove double spaces from output to help protect test from false negatives if spacing changes subtly
        $output = preg_replace('#  *#', ' ', $output);
        // Remove leading and trailing spaces.
        $output = preg_replace('#^[ \t]*#m', '', $output);
        $output = preg_replace('#[ \t]*$#m', '', $output);
        // Remove verbose info for rsync.
        $output = preg_replace('# -akzv --stats --progress #', ' -akz ', $output);
        // Debug flags may be added to command strings if we are in debug mode. Take those out so that tests in phpunit --debug mode work
        $output = preg_replace('# --debug #', ' ', $output);
        $output = preg_replace('# --verbose #', ' ', $output);
        $output = preg_replace('# -vvv #', ' ', $output);

        foreach ($this->pathsToSimplify() as $path => $simplification) {
            $output = str_replace($path, $simplification, $output);
        }

        return $output;
    }

    public function pathsToSimplify(): array
    {
        return [];
    }

    /**
     * Accessor for the last output, trimmed.
     */
    public function getOutput(): string
    {
        return trim($this->getOutputRaw());
    }

    /**
     * Accessor for the last stderr output, trimmed.
     */
    public function getErrorOutput(): string
    {
        return trim($this->getErrorOutputRaw());
    }

    /**
     * Accessor for the last output, rtrimmed and split on newlines.
     */
    public function getOutputAsList(): array
    {
        return array_map('rtrim', explode("\n", $this->getOutput()));
    }

    /**
     * Accessor for the last stderr output, rtrimmed and split on newlines.
     */
    public function getErrorOutputAsList(): array
    {
        return array_map('rtrim', explode("\n", $this->getErrorOutput()));
    }

    /**
     * Accessor for the last output, decoded from json.
     *
     * @param $key
     *   Optionally return only a top level element from the json object.
     */
    public function getOutputFromJSON(string|int|null $key = null): mixed
    {
        $output = $this->getOutput();
        $json = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("No json output received.\n\nOutput:\n\n$output\n\nStderr:\n\n" . $this->getErrorOutput());
        }
        if (isset($key)) {
            $json = $json[$key];
        }
        return $json;
    }
}

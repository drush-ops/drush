<?php
namespace Unish\Utils;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use PHPUnit\Framework\TestResult;
use Webmozart\PathUtil\Path;

/**
 * OutputUtilsTrait provides some useful utility methods for test classes
 * that define `getOutputRaw()` and `getErrorOutputRaw()` methods.
 */
trait OutputUtilsTrait
{
    /**
     * Get command output and simplify away things like full paths and extra
     * whitespace.
     */
    protected function getSimplifiedOutput()
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
    protected function getSimplifiedErrorOutput()
    {
        return $this->simplifyOutput($this->getErrorOutput());
    }

    /**
     * Remove things like full paths and extra whitespace from the given string.
     *
     * @param string $output
     *   The output string to simplify.
     *
     * @return string
     *   The simplified output.
     */
    protected function simplifyOutput($output)
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
        // Get rid of any full paths in the output
        $output = preg_replace('#' . Path::canonicalize(dirname(dirname(__DIR__))) . '/[^/]*#', '__DIR__', $output);
        $output = str_replace(self::getSandbox(), '__SANDBOX__', $output);
        $output = str_replace(self::getSut(), '__SUT__', $output);

        return $output;
    }

    /**
     * Accessor for the last output, trimmed.
     *
     * @return string
     *   Trimmed output as text.
     *
     * @access public
     */
    public function getOutput()
    {
        return trim($this->getOutputRaw());
    }

    /**
     * Accessor for the last stderr output, trimmed.
     *
     * @return string
     *   Trimmed stderr as text.
     *
     * @access public
     */
    public function getErrorOutput()
    {
        return trim($this->getErrorOutputRaw());
    }

    /**
     * Accessor for the last output, rtrimmed and split on newlines.
     *
     * @return array
     *   Output as array of lines.
     *
     * @access public
     */
    public function getOutputAsList()
    {
        return array_map('rtrim', explode("\n", $this->getOutput()));
    }

    /**
     * Accessor for the last stderr output, rtrimmed and split on newlines.
     *
     * @return array
     *   Stderr as array of lines.
     *
     * @access public
     */
    public function getErrorOutputAsList()
    {
        return array_map('rtrim', explode("\n", $this->getErrorOutput()));
    }

    /**
     * Accessor for the last output, decoded from json.
     *
     * @param string $key
     *   Optionally return only a top level element from the json object.
     *
     * @return object
     *   Decoded object.
     */
    public function getOutputFromJSON($key = null)
    {
        $output = $this->getOutput();
        $json = json_decode($output);
        if (!$json) {
            throw new \Exception("No json output received.\n\nOutput:\n\n$output\n\nStderr:\n\n" . $this->getErrorOutput());
        }
        if (isset($key)) {
            $json = $json->{$key}; // http://stackoverflow.com/questions/2925044/hyphens-in-keys-of-object
        }
        return $json;
    }

    /**
     * Accessor for the last output, non-trimmed.
     *
     * @return string
     *   Raw output as text.
     *
     * @access public
     */
    public abstract function getOutputRaw();

    /**
     * Accessor for the last stderr output, non-trimmed.
     *
     * @return string
     *   Raw stderr as text.
     *
     * @access public
     */
    public abstract function getErrorOutputRaw();
}

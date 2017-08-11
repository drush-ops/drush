<?php
namespace Drush\SiteAlias;

/**
 * Parse a string that contains a site specification.
 *
 * Site specifications contain some of the following elements:
 *   - remote-user
 *   - remote-server
 *   - path
 *   - sitename
 */
class SiteSpecParser
{
    protected $root;

    /**
     * Constructor
     *
     * @param string $root
     *   Drupal root (if provided)
     */
    public function __construct($root = '')
    {
        $this->root = $root;
    }

    /**
     * Parse a site specification
     *
     * @param string $spec
     *   A site specification in one of the accepted forms:
     *     - /path/to/drupal#sitename
     *     - user@server/path/to/drupal#sitename
     *     - user@server/path/to/drupal
     *     - user@server#sitename
     *   or, a site name:
     *     - #sitename
     * @return array
     *   A site specification array with the specified components filled in:
     *     - remote-user
     *     - remote-server
     *     - path
     *     - sitename
     *   or, an empty array if the provided parameter is not a valid site spec.
     */
    public function parse($spec)
    {
        return $this->validate($this->match($spec));
    }

    /**
     * Determine if the provided specification is value.
     *
     * @param string $spec
     *   @see parse()
     * @return bool
     */
    public function valid($spec)
    {
        $result = $this->match($spec);
        return !empty($result);
    }

    /**
     * Return the set of regular expression patterns that match the available
     * site specification formats.
     *
     * @return array
     *   key: site specification regex
     *   value: an array mapping from site specification component names to
     *     the elements in the 'matches' array containing the data for that element.
     */
    protected function patterns()
    {
        return [
            // /path/to/drupal#sitename
            '%^(/[^#]*)#([a-zA-Z0-9_-]+)$%' => [
                'root' => 1,
                'sitename' => 2,
            ],
            // user@server/path/to/drupal#sitename
            '%^([a-zA-Z0-9_-]+)@([a-zA-Z0-9_-]+)(/[^#]*)#([a-zA-Z0-9_-]+)$%' => [
                'remote-user' => 1,
                'remote-server' => 2,
                'root' => 3,
                'sitename' => 4,
            ],
            // user@server/path/to/drupal
            '%^([a-zA-Z0-9_-]+)@([a-zA-Z0-9_-]+)(/[^#]*)$%' => [
                'remote-user' => 1,
                'remote-server' => 2,
                'root' => 3,
                'sitename' => 'default', // Or '2' if sitename should be 'server'
            ],
            // user@server#sitename
            '%^([a-zA-Z0-9_-]+)@([a-zA-Z0-9_-]+)#([a-zA-Z0-9_-]+)$%' => [
                'remote-user' => 1,
                'remote-server' => 2,
                'sitename' => 3,
            ],
            // #sitename
            '%^#([a-zA-Z0-9_-]+)$%' => [
                'sitename' => 1,
            ],
        ];
    }

    /**
     * Run through all of the available regex patterns and determine if
     * any match the provided specification.
     *
     * @return array
     *   @see parse()
     */
    protected function match($spec)
    {
        foreach ($this->patterns() as $regex => $map) {
            if (preg_match($regex, $spec, $matches)) {
                return $this->mapResult($map, $matches);
            }
        }
        return [];
    }

    /**
     * Inflate the provided array so that it always contains the required
     * elements.
     *
     * @return array
     *   @see parse()
     */
    protected function defaults($result = [])
    {
        $result += [
            'remote-user' => '',
            'remote-server' => '',
            'root' => '',
            'sitename' => '',
        ];

        return $result;
    }

    /**
     * Take the data from the matches from the regular expression and
     * plug them into the result array per the info in the provided map.
     *
     * @param array $map
     *   An array mapping from result key to matches index.
     * @param array $matches
     *   The matched strings returned from preg_match
     * @return array
     *   @see parse()
     */
    protected function mapResult($map, $matches)
    {
        $result = [];

        foreach ($map as $key => $index) {
            $value = is_string($index) ? $index : $matches[$index];
            $result[$key] = $value;
        }

        if (empty($result)) {
            return [];
        }

        return $this->defaults($result);
    }

    /**
     * Validate the provided result. If the result is local, then it must
     * have a 'root'. If it does not, then fill in the root that was provided
     * to us in our consturctor.
     *
     * @param array $result
     *   @see parse() result.
     * @return array
     *   @see parse()
     */
    protected function validate($result)
    {
        if (empty($result) || !empty($result['remote-server'])) {
            return $result;
        }

        if (empty($result['root'])) {
            // TODO: should these throw an exception, so the user knows
            // why their site spec was invalid?
            if (empty($this->root) || !is_dir($this->root)) {
                return [];
            }

            $path = $this->root . '/sites/' . $result['sitename'];
            if (!is_dir($path)) {
                return [];
            }

            $result['root'] = $this->root;
            return $result;
        }

        return $result;
    }
}

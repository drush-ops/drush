<?php
namespace Drush\SiteAlias;

/**
 * Parse a string that contains a site specification.
 *
 * Site specifications contain some of the following elements:
 *   - user
 *   - host
 *   - path
 *   - uri (multisite selector)
 */
class SiteSpecParser
{
    /**
     * Parse a site specification
     *
     * @param string $spec
     *   A site specification in one of the accepted forms:
     *     - /path/to/drupal#uri
     *     - user@server/path/to/drupal#uri
     *     - user@server/path/to/drupal
     *     - user@server#uri
     *   or, a site name:
     *     - #uri
     * @param string $root
     *   Drupal root (if provided).
     * @return array
     *   A site specification array with the specified components filled in:
     *     - user
     *     - host
     *     - path
     *     - uri
     *   or, an empty array if the provided parameter is not a valid site spec.
     */
    public function parse($spec, $root = '')
    {
        $result = $this->match($spec);
        return $this->fixAndCheckUsability($result, $root);
    }

    /**
     * Determine if the provided specification is valid. Note that this
     * tests only for syntactic validity; to see if the specification is
     * usable, call 'parse()', which will also filter out specifications
     * for local sites that specify a multidev site that does not exist.
     *
     * @param string $spec
     *   @see parse()
     * @return bool
     */
    public function validSiteSpec($spec)
    {
        $result = $this->match($spec);
        return !empty($result);
    }

    /**
     * Determine whether or not the provided name is an alias name.
     *
     * @param string $aliasName
     * @return bool
     */
    public function isAliasName($aliasName)
    {
        return !empty($aliasName) && ($aliasName[0] == '@');
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
            // /path/to/drupal#uri
            '%^(/[^#]*)#([a-zA-Z0-9_-]+)$%' => [
                'root' => 1,
                'uri' => 2,
            ],
            // user@server/path/to/drupal#uri
            '%^([a-zA-Z0-9_-]+)@([a-zA-Z0-9_-]+)(/[^#]*)#([a-zA-Z0-9_-]+)$%' => [
                'user' => 1,
                'host' => 2,
                'root' => 3,
                'uri' => 4,
            ],
            // user@server/path/to/drupal
            '%^([a-zA-Z0-9_-]+)@([a-zA-Z0-9_-]+)(/[^#]*)$%' => [
                'user' => 1,
                'host' => 2,
                'root' => 3,
                'uri' => 'default', // Or '2' if uri should be 'host'
            ],
            // user@server#uri
            '%^([a-zA-Z0-9_-]+)@([a-zA-Z0-9_-]+)#([a-zA-Z0-9_-]+)$%' => [
                'user' => 1,
                'host' => 2,
                'uri' => 3,
            ],
            // #uri
            '%^#([a-zA-Z0-9_-]+)$%' => [
                'uri' => 1,
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
            'root' => '',
            'uri' => '',
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
    protected function fixAndCheckUsability($result, $root)
    {
        if (empty($result) || !empty($result['host'])) {
            return $result;
        }

        if (empty($result['root'])) {
            // TODO: should these throw an exception, so the user knows
            // why their site spec was invalid?
            if (empty($root) || !is_dir($root)) {
                return [];
            }

            $result['root'] = $root;
        }

        // TODO: If using a sitespec `#uri`, then `uri` MUST
        // be the name of a folder that exists in __DRUPAL_ROOT__/sites.
        // This restriction does NOT apply to the --uri option. Are there
        // instances where we need to allow 'uri' to be a literal uri
        // rather than the folder name? If so, we need to loosen this check.
        // I think it's fine as it is, though.
        $path = $result['root'] . '/sites/' . $result['uri'];
        if (!is_dir($path)) {
            return [];
        }

        return $result;
    }
}

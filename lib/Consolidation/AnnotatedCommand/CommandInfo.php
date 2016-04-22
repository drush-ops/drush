<?php
namespace Consolidation\AnnotatedCommand;

/**
 * Given a class and method name, parse the annotations in the
 * DocBlock comment, and provide accessor methods for all of
 * the elements that are needed to create a Symfony Console Command.
 */
class CommandInfo
{
    /**
     * @var \ReflectionMethod
     */
    protected $reflection;

    /**
     * @var boolean
     * @var string
    */
    protected $docBlockIsParsed;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @var string
     */
    protected $help = '';

    /**
     * @var DefaultsWithDescriptions
     */
    protected $options = [];

    /**
     * @var DefaultsWithDescriptions
     */
    protected $arguments = [];

    /**
     * @var array
     */
    protected $exampleUsage = [];

    /**
     * @var array
     */
    protected $otherAnnotations = [];

    /**
     * @var array
     */
    protected $aliases = [];

    /**
     * @var string
     */
    protected $methodName;

    /**
     * Create a new CommandInfo class for a particular method of a class.
     *
     * @param string|mixed $classNameOrInstance The name of a class, or an
     *   instance of it.
     * @param string $methodName The name of the method to get info about.
     */
    public function __construct($classNameOrInstance, $methodName)
    {
        $this->reflection = new \ReflectionMethod($classNameOrInstance, $methodName);
        $this->methodName = $methodName;
        // Set up a default name for the command from the method name.
        // This can be overridden via @command or @name annotations.
        $this->name = $this->convertName($this->reflection->name);
        $this->options = new DefaultsWithDescriptions($this->determineOptionsFromParameters(), false);
        $this->arguments = new DefaultsWithDescriptions($this->determineAgumentClassifications());
    }

    /**
     * Recover the method name provided to the constructor.
     *
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
    }

    /**
     * Return the primary name for this command.
     *
     * @return string
     */
    public function getName()
    {
        $this->parseDocBlock();
        return $this->name;
    }

    /**
     * Set the primary name for this command.
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get any annotations included in the docblock comment for the
     * implementation method of this command that are not already
     * handled by the primary methods of this class.
     *
     * @return array
     */
    public function getAnnotations()
    {
        $this->parseDocBlock();
        return $this->otherAnnotations;
    }

    /**
     * Return a specific named annotation for this command.
     *
     * @param string $annotation The name of the annotation.
     * @return string
     */
    public function getAnnotation($annotation)
    {
        // hasAnnotation parses the docblock
        if (!$this->hasAnnotation($annotation)) {
            return null;
        }
        return $this->otherAnnotations[$annotation];
    }

    /**
     * Check to see if the specified annotation exists for this command.
     *
     * @param string $annotation The name of the annotation.
     * @return boolean
     */
    public function hasAnnotation($annotation)
    {
        $this->parseDocBlock();
        return array_key_exists($annotation, $this->otherAnnotations);
    }

    /**
     * Save any tag that we do not explicitly recognize in the
     * 'otherAnnotations' map.
     */
    public function addOtherAnnotation($name, $content)
    {
        $this->otherAnnotations[$name] = $content;
    }

    /**
     * Get the synopsis of the command (~first line).
     *
     * @return string
     */
    public function getDescription()
    {
        $this->parseDocBlock();
        return $this->description;
    }

    /**
     * Set the command description.
     *
     * @param string $description The description to set.
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Get the help text of the command (the description)
     */
    public function getHelp()
    {
        $this->parseDocBlock();
        return $this->help;
    }
    /**
     * Set the help text for this command.
     *
     * @param string $help The help text.
     */
    public function setHelp($help)
    {
        $this->help = $help;
    }

    /**
     * Return the list of aliases for this command.
     * @return string[]
     */
    public function getAliases()
    {
        $this->parseDocBlock();
        return $this->aliases;
    }

    /**
     * Set aliases that can be used in place of the command's primary name.
     *
     * @param string|string[] $aliases
     */
    public function setAliases($aliases)
    {
        if (is_string($aliases)) {
            $aliases = explode(',', static::convertListToCommaSeparated($aliases));
        }
        $this->aliases = array_filter($aliases);
    }

    /**
     * Return the examples for this command. This is @usage instead of
     * @example because the later is defined by the phpdoc standard to
     * be example method calls.
     *
     * @return string[]
     */
    public function getExampleUsages()
    {
        $this->parseDocBlock();
        return $this->exampleUsage;
    }

    /**
     * Add an example usage for this command.
     *
     * @param string $usage An example of the command, including the command
     *   name and all of its example arguments and options.
     * @param string $description An explanation of what the example does.
     */
    public function setExampleUsage($usage, $description)
    {
        $this->exampleUsage[$usage] = $description;
    }

    /**
     * Return the list of refleaction parameters.
     *
     * @return ReflectionParameter[]
     */
    public function getParameters()
    {
        return $this->reflection->getParameters();
    }

    /**
     * Descriptions of commandline arguements for this command.
     *
     * @return DefaultsWithDescriptions
     */
    public function arguments()
    {
        return $this->arguments;
    }

    /**
     * Descriptions of commandline options for this command.
     *
     * @return DefaultsWithDescriptions
     */
    public function options()
    {
        return $this->options;
    }

    /**
     * An option might have a name such as 'silent|s'. In this
     * instance, we will allow the @option or @default tag to
     * reference the option only by name (e.g. 'silent' or 's'
     * instead of 'silent|s').
     *
     * @param string $optionName
     * @return string
     */
    public function findMatchingOption($optionName)
    {
        // Exit fast if there's an exact match
        if ($this->options->exists($optionName)) {
            return $optionName;
        }
        $existingOptionName = $this->findExistingOption($optionName);
        if (isset($existingOptionName)) {
            return $existingOptionName;
        }
        return $this->findOptionAmongAlternatives($optionName);
    }

    /**
     * @param string $optionName
     * @return string
     */
    protected function findOptionAmongAlternatives($optionName)
    {
        // Check the other direction: if the annotation contains @silent|s
        // and the options array has 'silent|s'.
        $checkMatching = explode('|', $optionName);
        if (count($checkMatching) > 1) {
            foreach ($checkMatching as $checkName) {
                if ($this->options->exists($checkName)) {
                    $this->options->rename($checkName, $optionName);
                    return $optionName;
                }
            }
        }
        return $optionName;
    }

    /**
     * @param string $optionName
     * @return string|null
     */
    protected function findExistingOption($optionName)
    {
        // Check to see if we can find the option name in an existing option,
        // e.g. if the options array has 'silent|s' => false, and the annotation
        // is @silent.
        foreach ($this->options()->getValues() as $name => $default) {
            if (in_array($optionName, explode('|', $name))) {
                return $name;
            }
        }
    }

    /**
     * Examine the parameters of the method for this command, and
     * build a list of commandline arguements for them.
     *
     * @return array
     */
    protected function determineAgumentClassifications()
    {
        $args = [];
        $params = $this->reflection->getParameters();
        if (!empty($this->determineOptionsFromParameters())) {
            array_pop($params);
        }
        foreach ($params as $param) {
            $defaultValue = $this->getArgumentClassification($param);
            if ($defaultValue !== false) {
                $args[$param->name] = $defaultValue;
            }
        }
        return $args;
    }

    /**
     * Examine the provided parameter, and determine whether it
     * is a parameter that will be filled in with a positional
     * commandline argument.
     *
     * @return false|null|string|array
     */
    protected function getArgumentClassification($param)
    {
        $defaultValue = null;
        if ($param->isDefaultValueAvailable()) {
            $defaultValue = $param->getDefaultValue();
            if ($this->isAssoc($defaultValue)) {
                return false;
            }
        }
        if ($param->isArray()) {
            return [];
        }
        // Commandline arguments must be strings, so ignore
        // any parameter that is typehinted to anything else.
        if (($param->getClass() != null) && ($param->getClass() != 'string')) {
            return false;
        }
        return $defaultValue;
    }

    /**
     * Examine the parameters of the method for this command, and determine
     * the disposition of the options from them.
     *
     * @return array
     */
    protected function determineOptionsFromParameters()
    {
        $params = $this->reflection->getParameters();
        if (empty($params)) {
            return [];
        }
        $param = end($params);
        if (!$param->isDefaultValueAvailable()) {
            return [];
        }
        if (!$this->isAssoc($param->getDefaultValue())) {
            return [];
        }
        return $param->getDefaultValue();
    }

    /**
     * Helper; determine if an array is associative or not. An array
     * is not associative if its keys are numeric, and numbered sequentially
     * from zero. All other arrays are considered to be associative.
     *
     * @param arrau $arr The array
     * @return boolean
     */
    protected function isAssoc($arr)
    {
        if (!is_array($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Convert from a method name to the corresponding command name. A
     * method 'fooBar' will become 'foo:bar', and 'fooBarBazBoz' will
     * become 'foo:bar-baz-boz'.
     *
     * @param string $camel method name.
     * @return string
     */
    protected function convertName($camel)
    {
        $splitter="-";
        $camel=preg_replace('/(?!^)[[:upper:]][[:lower:]]/', '$0', preg_replace('/(?!^)[[:upper:]]+/', $splitter.'$0', $camel));
        $camel = preg_replace("/$splitter/", ':', $camel, 1);
        return strtolower($camel);
    }

    /**
     * Parse the docBlock comment for this command, and set the
     * fields of this class with the data thereby obtained.
     */
    protected function parseDocBlock()
    {
        if (!$this->docBlockIsParsed) {
            $docblock = $this->reflection->getDocComment();
            $parser = new CommandDocBlockParser($this);
            $parser->parse($docblock);
            $this->docBlockIsParsed = true;
        }
    }

    /**
     * Given a list that might be 'a b c' or 'a, b, c' or 'a,b,c',
     * convert the data into the last of these forms.
     */
    protected static function convertListToCommaSeparated($text)
    {
        return preg_replace('#[ \t\n\r,]+#', ',', $text);
    }
}

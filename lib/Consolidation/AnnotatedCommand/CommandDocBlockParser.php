<?php
namespace Consolidation\AnnotatedCommand;

use phpDocumentor\Reflection\DocBlock\Tag\ParamTag;
use phpDocumentor\Reflection\DocBlock;

/**
 * Given a class and method name, parse the annotations in the
 * DocBlock comment, and provide accessor methods for all of
 * the elements that are needed to create an annotated Command.
 */
class CommandDocBlockParser
{
    /**
     * @var CommandInfo
     */
    protected $commandInfo;

    /**
     * @var array
     */
    protected $tagProcessors = [
        'command' => 'processCommandTag',
        'name' => 'processCommandTag',
        'param' => 'processArgumentTag',
        'option' => 'processOptionTag',
        'default' => 'processDefaultTag',
        'aliases' => 'processAliases',
        'usage' => 'processUsageTag',
        'description' => 'processAlternateDescriptionTag',
        'desc' => 'processAlternateDescriptionTag',
    ];

    public function __construct(CommandInfo $commandInfo)
    {
        $this->commandInfo = $commandInfo;
    }

    /**
     * Parse the docBlock comment for this command, and set the
     * fields of this class with the data thereby obtained.
     */
    public function parse($docblock)
    {
        $phpdoc = new DocBlock($docblock);

        // First set the description (synopsis) and help.
        $this->commandInfo->setDescription((string)$phpdoc->getShortDescription());
        $this->commandInfo->setHelp((string)$phpdoc->getLongDescription());

        // Iterate over all of the tags, and process them as necessary.
        foreach ($phpdoc->getTags() as $tag) {
            $processFn = [$this, 'processGenericTag'];
            if (array_key_exists($tag->getName(), $this->tagProcessors)) {
                $processFn = [$this, $this->tagProcessors[$tag->getName()]];
            }
            $processFn($tag);
        }
    }

    /**
     * Save any tag that we do not explicitly recognize in the
     * 'otherAnnotations' map.
     */
    protected function processGenericTag($tag)
    {
        $this->commandInfo->addOtherAnnotation($tag->getName(), $tag->getContent());
    }

    /**
     * Set the name of the command from a @command or @name annotation.
     */
    protected function processCommandTag($tag)
    {
        $this->commandInfo->setName($tag->getContent());
        // We also store the name in the 'other annotations' so that is is
        // possible to determine if the method had a @command annotation.
        $this->processGenericTag($tag);
    }

    /**
     * The @description and @desc annotations may be used in
     * place of the synopsis (which we call 'description').
     * This is discouraged.
     *
     * @deprecated
     */
    protected function processAlternateDescriptionTag($tag)
    {
        $this->commandInfo->setDescription($tag->getContent());
    }

    /**
     * Store the data from a @param annotation in our argument descriptions.
     */
    protected function processArgumentTag($tag)
    {
        if (!$tag instanceof ParamTag) {
            return;
        }
        $variableName = $tag->getVariableName();
        $variableName = str_replace('$', '', $variableName);
        $description = static::removeLineBreaks($tag->getDescription());
        $this->commandInfo->arguments()->add($variableName, $description);
    }

    /**
     * Given a docblock description in the form "$variable description",
     * return the variable name and description via the 'match' parameter.
     */
    protected function pregMatchNameAndDescription($source, &$match)
    {
        $nameRegEx = '\\$(?P<name>[^ \t]+)[ \t]+';
        $descriptionRegEx = '(?P<description>.*)';
        $optionRegEx = "/{$nameRegEx}{$descriptionRegEx}/s";

        return preg_match($optionRegEx, $source, $match);
    }

    /**
     * Store the data from an @option annotation in our option descriptions.
     */
    protected function processOptionTag($tag)
    {
        if (!$this->pregMatchNameAndDescription($tag->getDescription(), $match)) {
            return;
        }
        $variableName = $this->commandInfo->findMatchingOption($match['name']);
        $desc = $match['description'];
        $description = static::removeLineBreaks($desc);
        $this->commandInfo->options()->add($variableName, $description);
    }

    /**
     * Store the data from a @default annotation in our argument or option store,
     * as appropriate.
     */
    protected function processDefaultTag($tag)
    {
        if (!$this->pregMatchNameAndDescription($tag->getDescription(), $match)) {
            return;
        }
        $variableName = $match['name'];
        $defaultValue = $this->interpretDefaultValue($match['description']);
        if ($this->commandInfo->arguments()->exists($variableName)) {
            $this->commandInfo->arguments()->setDefaultValue($variableName, $defaultValue);
            return;
        }
        $variableName = $this->commandInfo->findMatchingOption($variableName);
        if ($this->commandInfo->options()->exists($variableName)) {
            $this->commandInfo->options()->setDefaultValue($variableName, $defaultValue);
        }
    }

    protected function interpretDefaultValue($defaultValue)
    {
        $defaults = [
            'null' => null,
            'true' => true,
            'false' => false,
            "''" => '',
            '[]' => [],
        ];
        foreach ($defaults as $defaultName => $defaultTypedValue) {
            if ($defaultValue == $defaultName) {
                return $defaultTypedValue;
            }
        }
        return $defaultValue;
    }

    /**
     * Process the comma-separated list of aliases
     */
    protected function processAliases($tag)
    {
        $this->commandInfo->setAliases($tag->getDescription());
    }

    /**
     * Store the data from a @usage annotation in our example usage list.
     */
    protected function processUsageTag($tag)
    {
        $lines = explode("\n", $tag->getContent());
        $usage = array_shift($lines);
        $description = static::removeLineBreaks(implode("\n", $lines));

        $this->commandInfo->setExampleUsage($usage, $description);
    }

    /**
     * Given a list that might be 'a b c' or 'a, b, c' or 'a,b,c',
     * convert the data into the last of these forms.
     */
    protected static function convertListToCommaSeparated($text)
    {
        return preg_replace('#[ \t\n\r,]+#', ',', $text);
    }

    /**
     * Take a multiline description and convert it into a single
     * long unbroken line.
     */
    protected static function removeLineBreaks($text)
    {
        return trim(preg_replace('#[ \t\n\r]+#', ' ', $text));
    }
}

<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run these commands using the --include option - e.g. `drush --include=/path/to/drush/examples art sandwich`
 *
 * See [Drush Test Traits](https://github.com/drush-ops/drush/blob/12.x/docs/contribute/unish.md#about-the-test-suites) for info on testing Drush commands.
 */

class ArtCommands extends DrushCommands implements CustomEventAwareInterface
{
    use CustomEventAwareTrait;

    /** @var string[] */
    protected ?array $arts;

    /**
     * Show a fabulous picture.
     */
    #[CLI\Command(name: 'artwork:show', aliases: ['arts'])]
    #[CLI\Argument(name: 'art', description: 'The name of the art to display')]
    #[CLI\Usage(name: 'drush art sandwich', description: 'Show a marvelous picture of a sandwich with pickles.')]
    public function art($art = '')
    {
        $data = $this->getArt();
        $name = $data[$art]['name'];
        $description = $data[$art]['description'];
        $path = $data[$art]['path'];
        $msg = dt(
            'Okay. Here is {art}: {description}',
            ['art' => $name, 'description' => $description]
        );
        $this->output()->writeln("\n" . $msg . "\n");
        $this->printFile($path);
    }

    /**
     * Show a table of information about available art.
     */
    #[CLI\Command(name: 'artwork:list', aliases: ['artls'])]
    #[CLI\FieldLabels(labels: ['name' => 'Name', 'description' => 'Description', 'path' => 'Path'])]
    #[CLI\DefaultTableFields(fields: ['name', 'description'])]
    #[CLI\FilterDefaultField(field: 'name')]
    public function listArt($options = ['format' => 'table']): RowsOfFields
    {
        $data = $this->getArt();
        return new RowsOfFields($data);
    }

    /**
     * Commandfiles may also add topics.  These will appear in
     * the list of topics when `drush topic` is executed.
     * To view the topic below, run `drush --include=/full/path/to/examples topic`
     */

    /**
     * Ruminations on the true meaning and philosophy of artwork.
     */
    #[CLI\Command(name: 'artwork:explain')]
    #[CLI\Topics(isTopic: true, path: __DIR__ . '/art-topic.md')]
    #[CLI\Help(hidden: true)]
    public function ruminate(): void
    {
        self::printFile($this->commandData);
    }

    /**
     * Return the available built-in art. Any Drush commandfile may provide
     * more art by implementing a 'drush-art' on-event hook. This on-event
     * hook is defined in the 'findArt' method below.
     */
    #[CLI\Hook(type: HookManager::ON_EVENT, target: 'drush-art')]
    public function builtInArt()
    {
        return [
            'drush' => [
                'name' => 'Drush',
                'description' => 'The Drush logo.',
                'path' => __DIR__ . '/art/drush-nocolor.txt',
            ],
            'sandwich' => [
                'name' => 'Sandwich',
                'description' => 'A tasty meal with bread often consumed at lunchtime.',
                'path' => __DIR__ . '/art/sandwich-nocolor.txt',
            ],
        ];
    }

    #[CLI\Hook(type: HookManager::INTERACT, target: 'artwork:show')]
    public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData)
    {
        $io = new DrushStyle($input, $output);

        // If the user did not specify any artwork, then prompt for one.
        $art = $input->getArgument('art');
        if (empty($art)) {
            $data = $this->getArt();
            $selections = $this->convertArtListToKeyValue($data);
            $selection = $io->choice('Select art to display', $selections);
            $input->setArgument('art', $selection);
        }
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'artwork:show')]
    public function artValidate(CommandData $commandData)
    {
        $art = $commandData->input()->getArgument('art');
        $data = $this->getArt();
        if (!isset($data[$art])) {
            throw new \Exception(dt('I do not have any art called "{name}".', ['name' => $art]));
        }
    }

    /**
     * Get a list of available artwork. Cache result for future fast access.
     */
    protected function getArt(): array
    {
        if (!isset($this->arts)) {
            $this->arts = $this->findArt();
        }
        return $this->arts;
    }

    /**
     * Use custom defined on-event hook 'drush-art' to find available artwork.
     */
    protected function findArt()
    {
        $arts = [];
        $handlers = $this->getCustomEventHandlers('drush-art');
        foreach ($handlers as $handler) {
            $handlerResult = $handler();
            $arts = array_merge($arts, $handlerResult);
        }
        return $arts;
    }

    /**
     * Given a list of artwork, convert to a 'key' => 'Name: Description' array.
     */
    protected function convertArtListToKeyValue(array $data): array
    {
        $result = [];
        foreach ($data as $key => $item) {
            $result[$key] = $item['name'] . ': ' . $item['description'];
        }
        return $result;
    }
}

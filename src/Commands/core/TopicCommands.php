<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TopicCommands extends DrushCommands
{
    const TOPIC = 'core:topic';

    /**
     * Read detailed documentation on a given topic.
     */
    #[CLI\Command(name: self::TOPIC, aliases: ['topic', 'core-topic'])]
    #[CLI\Argument(name: 'topic_name', description: 'The name of the topic you wish to view. If omitted, list all topic descriptions (and names in parenthesis).')]
    #[CLI\Usage(name: 'drush topic', description: 'Pick from all available topics.')]
    #[CLI\Usage(name: 'drush topic docs-repl', description: 'Show documentation for the Drush interactive shell')]
    #[CLI\Usage(name: 'drush docs:r', description: "Filter topics for those starting with 'docs-r'.")]
    #[CLI\Complete(method_name_or_callable: 'topicComplete')]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX)]
    #[CLI\Topics(topics: [DocsCommands::README])]
    public function topic($topic_name): int
    {
        $application = Drush::getApplication();
        $input = new ArrayInput([$topic_name], null);
        return $application->run($input);
    }

    #[CLI\Hook(type: HookManager::INTERACT, target: self::TOPIC)]
    public function interact(InputInterface $input, OutputInterface $output): void
    {
        $topics = self::getAllTopics();
        $topic_name = $input->getArgument('topic_name');
        if (!empty($topic_name)) {
            // Filter the topics to those matching the query.
            foreach ($topics as $key => $topic) {
                if (!str_contains($key, $topic_name)) {
                    unset($topics[$key]);
                }
            }
        }
        if (count($topics) > 1) {
            // Show choice list.
            foreach ($topics as $key => $topic) {
                $choices[$key] = $topic->getDescription() . " ($key)";
            }
            natcasesort($choices);
            $topic_name = $this->io()->choice(dt('Choose a topic'), $choices);
            $input->setArgument('topic_name', $topic_name);
        }
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: self::TOPIC)]
    public function validate(CommandData $commandData): void
    {
        $topic_name = $commandData->input()->getArgument('topic_name');
        if (!in_array($topic_name, array_keys(self::getAllTopics()))) {
            throw new \Exception(dt("!topic topic not found.", ['!topic' => $topic_name]));
        }
    }

    public function topicComplete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('topic_name')) {
            $suggestions->suggestValues(array_keys(self::getAllTopics()));
        }
    }

    /**
     * Retrieve all defined topics
     *
     * @return Command[]
     */
    public static function getAllTopics(): array
    {
        $application = Drush::getApplication();
        $all = $application->all();
        foreach ($all as $key => $command) {
            if ($command instanceof AnnotatedCommand) {
                /** @var AnnotationData $annotationData */
                $annotationData = $command->getAnnotationData();
                if ($annotationData->has('topic')) {
                    $topics[$command->getName()] = $command;
                }
            }
        }
        return $topics ?? [];
    }
}

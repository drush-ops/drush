<?php

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TopicCommands extends DrushCommands
{
    /**
     * Read detailed documentation on a given topic.
     *
     * @command core:topic
     * @param $topic_name  The name of the topic you wish to view. If omitted, list all topic descriptions (and names in parenthesis).
     * @usage drush topic
     *   Pick from all available topics.
     * @usage drush topic docs-repl
     *   Show documentation for the Drush interactive shell
     * @usage drush docs:r
     *   Filter topics for those starting with 'docs-r'.
     * @complete topicComplete
     * @remote-tty
     * @aliases topic,core-topic
     * @bootstrap max
     * @topics docs:readme
     */
    public function topic($topic_name): int
    {
        $application = Drush::getApplication();
        $input = new ArrayInput([$topic_name], null);
        return $application->run($input);
    }

    /**
     * @hook interact topic
     */
    public function interact(InputInterface $input, OutputInterface $output): void
    {
        $topics = self::getAllTopics();
        $topic_name = $input->getArgument('topic_name');
        if (!empty($topic_name)) {
            // Filter the topics to those matching the query.
            foreach ($topics as $key => $topic) {
                if (strstr($key, $topic_name) === false) {
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

    /**
     * @hook validate topic
     */
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
        /** @var Application $application */
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
        return $topics;
    }
}

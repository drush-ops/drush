<?php

/**
 * Adapted from Illuminate\Console\Concerns
 */

namespace Drush\Commands;

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSearchPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\PasswordPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\TextPrompt;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait ConfiguresPrompts
{
    /**
     * Configure the prompt fallbacks.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return void
     */
    protected function configurePrompts(InputInterface $input)
    {
        Prompt::setOutput($this->output);

        Prompt::interactive(($input->isInteractive() && defined('STDIN') && stream_isatty(STDIN)) || $this->runningUnitTests());

        Prompt::fallbackWhen(!$input->isInteractive() || strtoupper(substr(PHP_OS, 0, 3)) == "WIN" || $this->runningUnitTests());

        TextPrompt::fallbackUsing(fn (TextPrompt $prompt) => $this->promptUntilValid(
            fn () => (new SymfonyStyle($this->input, $this->output))->ask($prompt->label, $prompt->default ?: null) ?? '',
            $prompt->required,
            $prompt->validate
        ));

        PasswordPrompt::fallbackUsing(fn (PasswordPrompt $prompt) => $this->promptUntilValid(
            // @todo there is no secret().
            fn () => (new SymfonyStyle($this->input, $this->output))->secret($prompt->label) ?? '',
            $prompt->required,
            $prompt->validate
        ));

        ConfirmPrompt::fallbackUsing(fn (ConfirmPrompt $prompt) => $this->promptUntilValid(
            fn () => (new SymfonyStyle($this->input, $this->output))->confirm($prompt->label, $prompt->default),
            $prompt->required,
            $prompt->validate
        ));

        SelectPrompt::fallbackUsing(fn (SelectPrompt $prompt) => $this->promptUntilValid(
            fn () => (new SymfonyStyle($this->input, $this->output))->choice($prompt->label, $prompt->options, $prompt->default),
            false,
            $prompt->validate
        ));

        MultiSelectPrompt::fallbackUsing(function (MultiSelectPrompt $prompt) {
            $style = new SymfonyStyle($this->input, $this->output);
            if ($prompt->default !== []) {
                return $this->promptUntilValid(
                    fn () => $style->choice($prompt->label, $prompt->options, implode(',', $prompt->default), true),
                    $prompt->required,
                    $prompt->validate
                );
            }

            return $this->promptUntilValid(
                fn () => collect($style->choice($prompt->label, ['' => 'None', ...$prompt->options], 'None', true))
                    ->reject('')
                    ->all(),
                $prompt->required,
                $prompt->validate
            );
        });

        SuggestPrompt::fallbackUsing(fn (SuggestPrompt $prompt) => $this->promptUntilValid(
            // @todo No askWithCompletion
            fn () => (new SymfonyStyle($this->input, $this->output))->askWithCompletion($prompt->label, $prompt->options, $prompt->default ?: null) ?? '',
            $prompt->required,
            $prompt->validate
        ));

        SearchPrompt::fallbackUsing(fn (SearchPrompt $prompt) => $this->promptUntilValid(
            function () use ($prompt) {
                $query = (new SymfonyStyle($this->input, $this->output))->ask($prompt->label);

                $options = ($prompt->options)($query);

                return (new SymfonyStyle($this->input, $this->output))->choice($prompt->label, $options);
            },
            false,
            $prompt->validate
        ));

        MultiSearchPrompt::fallbackUsing(fn (MultiSearchPrompt $prompt) => $this->promptUntilValid(
            function () use ($prompt) {
                $style = new SymfonyStyle($this->input, $this->output);
                $query = $style->ask($prompt->label);

                $options = ($prompt->options)($query);

                if ($prompt->required === false) {
                    if (array_is_list($options)) {
                        return collect($style->choice($prompt->label, ['None', ...$options], 'None', true))
                            ->reject('None')
                            ->values()
                            ->all();
                    }

                    return collect($style->choice($prompt->label, ['' => 'None', ...$options], '', true))
                        ->reject('')
                        ->values()
                        ->all();
                }

                return $style->choice($prompt->label, $options, true);
            },
            $prompt->required,
            $prompt->validate
        ));
    }

    /**
     * Prompt the user until the given validation callback passes.
     *
     * @param  \Closure  $prompt
     * @param  bool|string  $required
     * @param  \Closure|null  $validate
     * @return mixed
     */
    protected function promptUntilValid($prompt, $required, $validate)
    {
        while (true) {
            $result = $prompt();
            $style = new SymfonyStyle($this->input, $this->output);

            if ($required && ($result === '' || $result === [] || $result === false)) {
                $style->error(is_string($required) ? $required : 'Required.');

                continue;
            }

            if ($validate) {
                $error = $validate($result);

                if (is_string($error) && strlen($error) > 0) {
                    $style->error($error);

                    continue;
                }
            }

            return $result;
        }
    }

    /**
     * Restore the prompts output.
     *
     * @return void
     */
    protected function restorePrompts()
    {
        Prompt::setOutput($this->output);
    }

    protected function runningUnitTests(): bool
    {
        if (! defined('PHPUNIT_COMPOSER_INSTALL') && ! defined('__PHPUNIT_PHAR__')) {
            // is not PHPUnit run
            return false;
        }
        return true;
    }
}

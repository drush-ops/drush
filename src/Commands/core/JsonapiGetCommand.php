<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Command\OutputFormat;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Execute a JSONAPI request.',
    usages: [
        'jn:get jsonapi/node/article',
        'jn:get jsonapi/node/article | jq',
    ],
    help: 'Get a list of articles back as JSON. Filter the JSON by piping to jq. See https://jqlang.org/ for lots more jq features.',
)]
final class JsonapiGetCommand
{
    use StringTranslationTrait;

    public const string NAME = 'jn:get';

    public function __construct(
        #[Autowire(service: 'kernel')]
        protected readonly DrupalKernelInterface $kernel,
        protected readonly ModuleHandlerInterface $moduleHandler,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('The JSONAPI URL to request.')]
        string $url,
        #[Option('The output format: json or yaml.')]
        OutputFormat $format = OutputFormat::Json,
    ): int {
        // @todo Remove once the `drush` entrypoint is retired.
        //   DoctorServiceProvider only registers this command with `dr` when
        //   jsonapi is installed.
        if (!$this->moduleHandler->moduleExists('jsonapi')) {
            throw new \InvalidArgumentException((string) $this->t('The jsonapi module is not installed.'));
        }

        $response = $this->kernel->handle(Request::create($url, 'GET'), HttpKernelInterface::SUB_REQUEST);
        $content = (string) $response->getContent();
        if (!$response->isSuccessful()) {
            // JSON:API error documents carry a detail per error.
            $decoded = json_decode($content, true);
            $details = is_array($decoded) ? array_column($decoded['errors'] ?? [], 'detail') : [];
            throw new \RuntimeException(trim((string) $this->t('The request to @url returned status @status.', [
                '@url' => $url,
                '@status' => $response->getStatusCode(),
            ]) . ' ' . implode(' ', $details)));
        }

        try {
            // Objects keep {} distinct from [] in JSON. Yaml needs arrays.
            $data = json_decode($content, $format === OutputFormat::Yaml, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException((string) $this->t('The response from @url is not JSON.', ['@url' => $url]));
        }

        $result = match ($format) {
            OutputFormat::Json => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => Yaml::encode($data),
        };
        // Yaml::encode() ends with a newline of its own; writeln adds one.
        $output->writeln(rtrim($result, "\n"), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}

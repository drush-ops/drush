Dependency Injection
==================

A command gets the services it needs through its constructor. Drupal's container builds each command and autowires the constructor: each parameter's type hint names the service to inject.

```php
public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'logger.channel.example')]
    private readonly LoggerInterface $logger,
) {
}
```

- A type hint works when the container has a service or alias with that name. Core defines aliases for most of its interfaces, such as `EntityTypeManagerInterface`, `ConfigFactoryInterface` and `GuzzleHttp\ClientInterface`.
- When a type has no alias, or several services share it, name the service with Symfony's `#[Autowire(service: '...')]` attribute (`Symfony\Component\DependencyInjection\Attribute\Autowire`), as above.
- For a service from a module that may not be installed, make the parameter nullable with a `null` default. It receives `null` when the service is missing. [EntitySaveCommand](https://github.com/drush-ops/drush/blob/14.x/src/Commands/entity/EntitySaveCommand.php) does this for content_moderation.

Don't call `\Drupal::service()` in a command. Injected services can be replaced in a test: build the command with `new` and pass what the test needs. See [Testing](commands.md#testing).

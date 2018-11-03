<?php

namespace Drupal\Tests\serialization\Unit\CompilerPass;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\serialization\RegisterSerializationClassesCompilerPass;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Serializer\Serializer;

/**
 * @coversDefaultClass \Drupal\serialization\RegisterSerializationClassesCompilerPass
 * @group serialization
 */
class RegisterSerializationClassesCompilerPassTest extends UnitTestCase {

  /**
   * @covers ::process
   */
  public function testEncoders() {
    $container = new ContainerBuilder();
    $container->setDefinition('serializer', new Definition(Serializer::class, [[], []]));

    $encoder_1_definition = new Definition('TestClass');
    $encoder_1_definition->addTag('encoder', ['format' => 'xml']);
    $encoder_1_definition->addTag('_provider', ['provider' => 'test_provider_a']);
    $container->setDefinition('encoder_1', $encoder_1_definition);

    $encoder_2_definition = new Definition('TestClass');
    $encoder_2_definition->addTag('encoder', ['format' => 'json']);
    $encoder_2_definition->addTag('_provider', ['provider' => 'test_provider_a']);
    $container->setDefinition('encoder_2', $encoder_2_definition);

    $encoder_3_definition = new Definition('TestClass');
    $encoder_3_definition->addTag('encoder', ['format' => 'hal_json']);
    $encoder_3_definition->addTag('_provider', ['provider' => 'test_provider_b']);
    $container->setDefinition('encoder_3', $encoder_3_definition);

    $normalizer_1_definition = new Definition('TestClass');
    $normalizer_1_definition->addTag('normalizer');
    $container->setDefinition('normalizer_1', $normalizer_1_definition);

    $compiler_pass = new RegisterSerializationClassesCompilerPass();
    $compiler_pass->process($container);

    // Check registration of formats and providers.
    $this->assertEquals(['xml', 'json', 'hal_json'], $container->getParameter('serializer.formats'));
    $this->assertEquals(['xml' => 'test_provider_a', 'json' => 'test_provider_a', 'hal_json' => 'test_provider_b'], $container->getParameter('serializer.format_providers'));

    // Check all encoder and normalizer service definitions are marked private.
    $this->assertFalse($encoder_1_definition->isPublic());
    $this->assertFalse($encoder_2_definition->isPublic());
    $this->assertFalse($encoder_3_definition->isPublic());

    $this->assertFalse($normalizer_1_definition->isPublic());
  }

}

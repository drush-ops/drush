<?php

namespace Drupal\Core\Controller\ArgumentResolver;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Yields a PSR7 request object based on the request object passed along.
 */
final class Psr7RequestValueResolver implements ArgumentValueResolverInterface {

  /**
   * The PSR-7 converter.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected $httpMessageFactory;

  /**
   * Constructs a new ControllerResolver.
   *
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $http_message_factory
   *   The PSR-7 converter.
   */
  public function __construct(HttpMessageFactoryInterface $http_message_factory) {
    $this->httpMessageFactory = $http_message_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function supports(Request $request, ArgumentMetadata $argument) {
    return $argument->getType() == ServerRequestInterface::class;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(Request $request, ArgumentMetadata $argument) {
    yield $this->httpMessageFactory->createRequest($request);
  }

}

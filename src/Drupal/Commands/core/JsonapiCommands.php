<?php
namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\StructuredData\UnstructuredData;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class JsonapiCommands extends DrushCommands
{

    /**
     * Execute a JSONAPI request.
     *
     * @command jn:get
     *
     * @param $url The JSONAPI URL to request.
     * @usage drush jn:get jsonapi/node/article
     *   Get a list of articles back as JSON.
     * @usage drush jn:get jsonapi/node/article | jq
     *   Pretty print JSON by piping to jq. See https://stedolan.github.io/jq/ for lots more jq features.
     * @validate-module-enabled jsonapi
     *
     * @return UnstructuredData
     */
    public function get($url, $options = ['format' => 'json'])
    {
        $kernel = Drush::bootstrap()->getKernel();
        $sub_request = Request::create($url, 'GET');
        $subResponse = $kernel->handle($sub_request, HttpKernelInterface::SUB_REQUEST);
        return new UnstructuredData(json_decode($subResponse->getContent()));
    }
}

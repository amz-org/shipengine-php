<?php declare(strict_types=1);

namespace ShipEngine;

use Http\Client\Common\Plugin\BaseUriPlugin;
use Http\Client\Common\Plugin\HeaderDefaultsPlugin;
use Http\Client\Common\Plugin\RetryPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ShipEngine\Service\ShipEngineConfig;

/**
 * A wrapped `JSON-RPC 2.0` HTTP client.
 *
 * @pacakge ShipEngine
 */
final class ShipEngineClient
{
    /**
     * @var PluginClient
     */
    private PluginClient $client;

    private ShipEngineConfig $config;

    /**
     * ShipEngineClient constructor.
     *
     * @param ShipEngineConfig $config
     * @param string $user_agent
     * @param HttpClient|null $client
     */
    public function __construct(ShipEngineConfig $config, string $user_agent, HttpClient $client = null)
    {
        $this->config = $config;

        if (!$client) {
            $client = HttpClientDiscovery::find();
        }

        $headers = array();
        $headers['Api-Key'] = $config->api_key;
        $headers['User-Agent'] = $user_agent;
        $headers['Content-Type'] = 'application/json';

        $uri_factory = UriFactoryDiscovery::find();

        if (!getenv('CLIENT_BASE_URI')) {
            $base_url = $config->base_url;
        } else {
            $base_url = getenv('CLIENT_BASE_URI');
        }

        $base_uri = $uri_factory->createUri($base_url);

        $plugins = array();
        $plugins[] = new HeaderDefaultsPlugin($headers);
        $plugins[] = new BaseUriPlugin($base_uri);
        $plugins[] = new RetryPlugin([
            'retries' => $config->retries,
            'error_response_decider' => function (RequestInterface $request, ResponseInterface $response): bool {
                $status = $response->getStatusCode();
                return $status === 429;
            }
        ]);

        $this->client = new PluginClient($client, $plugins);
    }

    /**
     * Send a `JSON-RPC 2.0` request via HTTP Messages.
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }
}

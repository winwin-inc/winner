<?php

declare(strict_types=1);

namespace winwin\winner;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use winwin\winner\exception\BadResponseException;
use winwin\winner\exception\ResourceNotFoundException;

class JsonRpcGatewayClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var ClientInterface
     */
    private $httpClient;
    /**
     * @var bool
     */
    private $debug;
    /**
     * @var mixed
     */
    private $result;
    /**
     * @var Config
     */
    private $config;
    /**
     * @var int
     */
    private $id = 1;

    public function __construct(Config $config, LoggerInterface $logger, bool $debug = false)
    {
        $this->config = $config;
        $this->setLogger($logger);
        $this->debug = $debug;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    protected function getHttpClient(): ClientInterface
    {
        if (!$this->httpClient) {
            $handler = HandlerStack::create();
            if ($this->debug) {
                $handler->push(Middleware::log($this->logger, new MessageFormatter(MessageFormatter::DEBUG)));
            }
            $handler->push(Middleware::mapResponse(function (ResponseInterface $response) {
                $data = json_decode((string) $response->getBody(), true);
                if (404 === $response->getStatusCode()) {
                    throw new ResourceNotFoundException($data['cause'] ?? (string) $response->getBody());
                }
                if (!empty($data) && !array_key_exists('result', $data)) {
                    throw new BadResponseException("key 'result' not found in response: ".$response->getBody());
                }
                $this->result = $data['result'] ?? null;

                return $response;
            }));
            $this->httpClient = new Client([
                'handler' => $handler,
                'base_uri' => $this->config->getEndpoint(),
                'headers' => [
                    'content-type' => 'application/json',
                    'apikey' => $this->config->getToken(),
                ],
            ]);
        }

        return $this->httpClient;
    }

    public function call(string $servant, string $method, ...$params)
    {
        if (empty($servant)) {
            throw new \InvalidArgumentException('Servant is required');
        }
        $this->getHttpClient()->request('POST', '/', [
            'json' => [
                'method' => $servant.'.'.$method,
                'params' => $params,
                'id' => $this->id++,
            ],
        ]);

        return $this->result;
    }
}

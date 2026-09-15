<?php

declare(strict_types=1);

namespace A2A\Client;

use A2A\Protocol\{ErrorCode, Json, ProtocolException, Validator};
use Lf\A2a\V1\AgentCard;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class Discovery
{
    private HttpClientInterface $http;
    public function __construct(HttpClientInterface $http, bool $allowPrivateNetwork = false)
    {
        $this->http = $allowPrivateNetwork ? $http : new NoPrivateNetworkHttpClient($http);
    }
    public function discover(string $baseUrl, CallOptions $options = new CallOptions()): AgentCard
    {
        $parts = parse_url($baseUrl);
        if ($parts === false || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || isset($parts['query'])) {
            throw new \InvalidArgumentException('An absolute HTTP(S) discovery URL is required');
        }
        $response = $this->http->request('GET', rtrim($baseUrl, '/').'/.well-known/agent-card.json', ['headers' => $options->httpHeaders(), 'timeout' => $options->timeoutSeconds, 'max_duration' => $options->timeoutSeconds, 'max_redirects' => 0]);
        try {
            if ($response->getStatusCode() !== 200) {
                throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Agent card discovery failed');
            }
            $body = '';
            foreach ($this->http->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    throw new ProtocolException(ErrorCode::DeadlineExceeded, 'Agent card discovery timed out');
                }
                $body .= $chunk->getContent();
                if (strlen($body) > $options->maxMessageBytes) {
                    throw new ProtocolException(ErrorCode::ResourceExhausted, 'Agent card exceeds limit');
                }
            }
            $card = Json::message($body, AgentCard::class);
            (new Validator())->validate($card);
            return $card;
        } finally {
            $response->cancel();
        }
    }
}

<?php

declare(strict_types=1);
require dirname(__DIR__, 2).'/vendor/autoload.php';
$card = A2A\Protocol\Json::message(file_get_contents(__DIR__.'/card.json'), Lf\A2a\V1\AgentCard::class);
foreach ($card->getSupportedInterfaces() as $interface) {
    $interface->setUrl(match ($interface->getProtocolBinding()) {
        'GRPC' => '127.0.0.1:50051',
        'JSONRPC' => 'http://127.0.0.1:9999/a2a/rpc',
        default => 'http://127.0.0.1:9999/a2a',
    });
}
$authenticator = new class () implements A2A\Security\Authenticator {
    public function authenticate(array $headers, string $tenant, ?float $deadline): A2A\Security\CallContext
    {
        $headers = array_change_key_case($headers);
        return new A2A\Security\CallContext('tck', $tenant, version: $headers['a2a-version'] ?? '1.0', deadline: $deadline);
    }
};
return new A2A\Tests\Fixtures\Application('/tmp/a2a-tck-storage', new A2A\Tests\Fixtures\TckExecutor(), $authenticator, $card, new A2A\Server\PushOptions(['example.com', 'localhost', '127.0.0.1'], retryDelaySeconds: 0.1, allowPrivateNetwork: true, requireHttps: false), new A2A\Transport\Http\HttpOptions(cardLastModified: filemtime(__DIR__.'/card.json')));

<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\Client\{CallOptions, Client};
use A2A\Protocol\{Json, ProtocolException};
use A2A\Tests\Fixtures\{TlsCertificates, TlsServer};
use A2A\Transport\Grpc\GrpcClientOptions;
use A2A\Transport\HttpTransport;
use Lf\A2a\V1\{SendMessageRequest, TaskState};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class TlsTest extends TestCase
{
    private static TlsCertificates $certificates;
    private ?TlsServer $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$certificates = new TlsCertificates();
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
    }

    public static function bindings(): array
    {
        return [['grpc'], ['jsonrpc'], ['rest']];
    }

    private function client(string $binding, string $authority = 'ca', ?string $identity = null): Client
    {
        $certificates = self::$certificates;
        $endpoint = $this->server->endpoint($binding);
        if ($binding === 'grpc') {
            $transport = (new GrpcClientOptions(
                rootCertificates: $certificates->pem($authority),
                privateKey: $identity === null ? null : file_get_contents($certificates->path($identity.'.key')),
                certificateChain: $identity === null ? null : $certificates->pem($identity),
            ))->connect($endpoint);
        } else {
            $http = HttpClient::create([
                'cafile' => $certificates->path($authority.'.pem'),
                'local_cert' => $identity === null ? null : $certificates->path($identity.'.pem'),
                'local_pk' => $identity === null ? null : $certificates->path($identity.'.key'),
            ]);
            $transport = new HttpTransport($http, $endpoint, $binding === 'jsonrpc');
        }
        return new Client($transport, new CallOptions(timeoutSeconds: 2, headers: ['Authorization' => 'Bearer test-alice-token']));
    }

    private function request(): SendMessageRequest
    {
        return Json::message('{"message":{"messageId":"'.bin2hex(random_bytes(8)).'","role":"ROLE_USER","parts":[{"text":"hello"}]}}', SendMessageRequest::class);
    }

    #[DataProvider('bindings')]
    public function testTrustedServerSupportsRequestsAndStreaming(string $binding): void
    {
        $this->server = new TlsServer(self::$certificates, $binding);
        $this->assertRequestsAndStreaming($this->client($binding));
    }

    #[DataProvider('bindings')]
    public function testMutualTlsSupportsRequestsAndStreaming(string $binding): void
    {
        $this->server = new TlsServer(self::$certificates, $binding, mutual: true);
        $this->assertRequestsAndStreaming($this->client($binding, identity: 'client'));
    }

    private function assertRequestsAndStreaming(Client $client): void
    {
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $client->sendMessage($this->request())->getTask()->getStatus()->getState());
        $events = iterator_to_array($client->sendStreamingMessage($this->request()));
        self::assertCount(4, $events);
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $events[3]->getStatusUpdate()->getStatus()->getState());
    }

    #[DataProvider('bindings')]
    public function testUntrustedServerIsRejected(string $binding): void
    {
        $this->server = new TlsServer(self::$certificates, $binding);
        $this->assertHandshakeRejected($this->client($binding, authority: 'untrusted-ca'));
    }

    #[DataProvider('bindings')]
    public function testWrongServerNameIsRejected(string $binding): void
    {
        $this->server = new TlsServer(self::$certificates, $binding, certificate: 'wrong-host');
        $this->assertHandshakeRejected($this->client($binding));
    }

    #[DataProvider('bindings')]
    public function testClientCertificateIsRequired(string $binding): void
    {
        $this->server = new TlsServer(self::$certificates, $binding, mutual: true);
        $this->assertHandshakeRejected($this->client($binding));
        $this->assertRequestsAndStreaming($this->client($binding, identity: 'client'));
    }

    #[DataProvider('bindings')]
    public function testUntrustedClientCertificateIsRejected(string $binding): void
    {
        $this->server = new TlsServer(self::$certificates, $binding, mutual: true);
        $this->assertHandshakeRejected($this->client($binding, identity: 'untrusted-client'));
        $this->assertRequestsAndStreaming($this->client($binding, identity: 'client'));
    }

    private function assertHandshakeRejected(Client $client): void
    {
        try {
            $client->sendMessage($this->request());
            self::fail('TLS connection was accepted with invalid credentials');
        } catch (ProtocolException | TransportExceptionInterface $error) {
            self::assertMatchesRegularExpression('/SSL|TLS|certificate|peer name|connection reset/i', $error->getMessage());
        }
    }
}

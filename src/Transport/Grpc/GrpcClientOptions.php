<?php

declare(strict_types=1);

namespace A2A\Transport\Grpc;

final readonly class GrpcClientOptions
{
    public function __construct(
        public bool $tls = true,
        public ?string $rootCertificates = null,
        public ?string $privateKey = null,
        public ?string $certificateChain = null,
        public int $maxReceiveMessageBytes = 4194304,
        public int $maxSendMessageBytes = 4194304,
    ) {
        if (($privateKey === null) !== ($certificateChain === null) || min($maxReceiveMessageBytes, $maxSendMessageBytes) < 1) {
            throw new \InvalidArgumentException('Invalid gRPC client configuration');
        }
        if (!$tls && ($rootCertificates !== null || $privateKey !== null || $certificateChain !== null)) {
            throw new \InvalidArgumentException('Certificates require TLS');
        }
    }
    public function connect(string $target): GrpcTransport
    {
        if (!extension_loaded('grpc') || !class_exists(\Grpc\BaseStub::class)) {
            throw new \LogicException('Install ext-grpc and grpc/grpc to enable the gRPC client');
        }
        $credentials = $this->tls ? \Grpc\ChannelCredentials::createSsl($this->rootCertificates, $this->privateKey, $this->certificateChain) : \Grpc\ChannelCredentials::createInsecure();
        return new GrpcTransport(new NativeClient($target, ['credentials' => $credentials, 'grpc.max_receive_message_length' => $this->maxReceiveMessageBytes, 'grpc.max_send_message_length' => $this->maxSendMessageBytes]));
    }
}

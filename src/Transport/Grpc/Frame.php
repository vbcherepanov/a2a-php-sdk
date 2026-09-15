<?php

declare(strict_types=1);

namespace A2A\Transport\Grpc;

use A2A\Protocol\{ErrorCode, ProtocolException};

final class Frame
{
    public static function encode(string $payload, int $maxBytes): string
    {
        if (strlen($payload) > $maxBytes) {
            throw new ProtocolException(ErrorCode::ResourceExhausted, 'gRPC message exceeds limit');
        }
        return pack('CN', 0, strlen($payload)).$payload;
    }
    public static function decode(string $frame, int $maxBytes): string
    {
        if (strlen($frame) < 5) {
            throw ProtocolException::invalid('Truncated gRPC frame');
        }
        $header = unpack('Ccompressed/Nlength', substr($frame, 0, 5));
        if ($header === false || $header['compressed'] !== 0) {
            throw new ProtocolException(ErrorCode::UnsupportedOperation, 'Compressed gRPC requests are not supported');
        }
        if ($header['length'] > $maxBytes) {
            throw new ProtocolException(ErrorCode::ResourceExhausted, 'gRPC message exceeds limit');
        }
        if ($header['length'] !== strlen($frame) - 5) {
            throw ProtocolException::invalid('Invalid gRPC frame length');
        }
        return substr($frame, 5);
    }
}

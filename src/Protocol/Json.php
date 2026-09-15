<?php

declare(strict_types=1);

namespace A2A\Protocol;

use Google\Protobuf\Internal\Message;

final class Json
{
    public const MAX_DEPTH = 64;
    public static function object(string $json): \stdClass
    {
        try {
            $value = json_decode($json, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ProtocolException(ErrorCode::ParseError, 'Invalid JSON', $e);
        }
        if (!$value instanceof \stdClass) {
            throw new ProtocolException(ErrorCode::InvalidRequest, 'Expected a JSON object');
        }
        return $value;
    }
    public static function encode(object $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
    /** @template T of Message
     * @param class-string<T> $class
     * @return T
     */
    public static function message(string $json, string $class): Message
    {
        self::object($json);
        $message = new $class();
        try {
            $message->mergeFromJsonString($json, true);
        } catch (\Throwable $e) {
            throw new ProtocolException(ErrorCode::InvalidParams, 'Invalid protobuf JSON payload', $e);
        }
        return $message;
    }
    public static function data(Message $message): \stdClass
    {
        return self::object($message->serializeToJsonString());
    }
}

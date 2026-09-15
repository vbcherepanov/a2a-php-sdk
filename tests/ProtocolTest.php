<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\Protocol\{Json, ProtocolException, Validator};
use A2A\Transport\{SseDecoder};
use A2A\Transport\Grpc\Frame;
use Lf\A2a\V1\{GetTaskRequest, SendMessageRequest, Part};
use PHPUnit\Framework\TestCase;

final class ProtocolTest extends TestCase
{
    public function testOptionalZeroAndBinaryRoundTrip(): void
    {
        $request = Json::message('{"id":"task","historyLength":0}', GetTaskRequest::class);
        self::assertTrue($request->hasHistoryLength());
        self::assertSame(0, $request->getHistoryLength());
        $restored = new GetTaskRequest();
        $restored->mergeFromString($request->serializeToString());
        self::assertTrue($restored->hasHistoryLength());
        self::assertFalse((new GetTaskRequest())->hasHistoryLength());
        $part = (new Part())->setRaw("\x00\xff");
        self::assertSame('{"raw":"AP8="}', $part->serializeToJsonString());
    }
    public function testInvalidMessageIsRejected(): void
    {
        $this->expectException(ProtocolException::class);
        (new Validator())->validate(Json::message('{"message":{"messageId":"id","role":"ROLE_USER","parts":[{}]}}', SendMessageRequest::class));
    }
    public function testUnknownFieldsAreIgnored(): void
    {
        $request = Json::message('{"id":"t","future":true}', GetTaskRequest::class);
        self::assertSame('t', $request->getId());
    }
    public function testSseFragmentationAndMultiline(): void
    {
        $decoder = new SseDecoder(1024);
        $out = [];
        foreach (str_split("\xEF\xBB\xBF: comment\r\ndata: {\"a\":\r\ndata: 1}\r\n\r\n") as $byte) {
            $out = [...$out, ...$decoder->feed($byte)];
        }
        $decoder->finish();
        self::assertSame(["{\"a\":\n1}"], $out);
    }
    public function testTruncatedSseIsRejected(): void
    {
        $decoder = new SseDecoder(1024);
        $decoder->feed('data: partial');
        $this->expectException(ProtocolException::class);
        $decoder->finish();
    }
    public function testInvalidGrpcFrameIsRejected(): void
    {
        $this->expectException(ProtocolException::class);
        Frame::decode(pack('CN', 0, 10).'x', 1024);
    }
    public function testGrpcFramesRoundTrip(): void
    {
        self::assertSame("\0\1test", Frame::decode(Frame::encode("\0\1test", 1024), 1024));
    }
}

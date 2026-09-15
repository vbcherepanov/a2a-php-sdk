<?php

declare(strict_types=1);

namespace A2A\Protocol;

use Google\Protobuf\Any;
use Google\Rpc\ErrorInfo;
use Google\Rpc\Status;

final class ProtocolException extends \RuntimeException
{
    public function __construct(public readonly ErrorCode $error, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $error->value, $previous);
    }
    public static function invalid(string $message): self
    {
        return new self(ErrorCode::InvalidParams, $message);
    }
    public function status(): Status
    {
        $info = new ErrorInfo();
        $info->setReason($this->error->reason())->setDomain('a2a-protocol.org');
        $detail = new Any();
        $detail->pack($info);
        return (new Status())->setCode($this->error->grpcStatus())->setMessage($this->getMessage())->setDetails([$detail]);
    }
}

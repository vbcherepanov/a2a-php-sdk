<?php

declare(strict_types=1);

namespace A2A\Protocol;

final class ErrorMapper
{
    public static function rpc(ProtocolException $error): \stdClass
    {
        $status = Json::data($error->status());
        return (object) ['code' => $error->error->value, 'message' => $error->getMessage(), 'data' => $status->details ?? []];
    }
    public static function rest(ProtocolException $error): \stdClass
    {
        $status = Json::data($error->status());
        $status->code = $error->error->httpStatus();
        return (object) ['error' => $status];
    }
    public static function fromWire(\stdClass $error): ProtocolException
    {
        $code = isset($error->code) && is_int($error->code) ? ErrorCode::tryFrom($error->code) : null;
        $details = $error->details ?? $error->data ?? [];
        if (is_array($details)) {
            foreach ($details as $detail) {
                if ($detail instanceof \stdClass && ($detail->{'@type'} ?? '') === 'type.googleapis.com/google.rpc.ErrorInfo') {
                    foreach (ErrorCode::cases() as $candidate) {
                        if (($detail->reason ?? '') === $candidate->reason()) {
                            $code = $candidate;
                        }
                    }
                }
            }
        }
        return new ProtocolException($code ?? ErrorCode::InternalError, is_string($error->message ?? null) ? $error->message : 'Remote error');
    }
}

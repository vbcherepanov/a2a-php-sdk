<?php

declare(strict_types=1);

namespace A2A\Protocol;

enum ErrorCode: int
{
    case ParseError = -32700;
    case InvalidRequest = -32600;
    case MethodNotFound = -32601;
    case InvalidParams = -32602;
    case InternalError = -32603;
    case TaskNotFound = -32001;
    case TaskNotCancelable = -32002;
    case PushNotSupported = -32003;
    case UnsupportedOperation = -32004;
    case ContentTypeNotSupported = -32005;
    case InvalidAgentResponse = -32006;
    case ExtendedCardNotConfigured = -32007;
    case ExtensionRequired = -32008;
    case VersionNotSupported = -32009;
    case Unauthenticated = -32010;
    case PermissionDenied = -32011;
    case ResourceExhausted = -32012;
    case DeadlineExceeded = -32013;
    public function reason(): string
    {
        return match ($this) {
            self::TaskNotFound => 'TASK_NOT_FOUND',
            self::TaskNotCancelable => 'TASK_NOT_CANCELABLE',
            self::PushNotSupported => 'PUSH_NOTIFICATION_NOT_SUPPORTED',
            self::UnsupportedOperation => 'UNSUPPORTED_OPERATION',
            self::ContentTypeNotSupported => 'CONTENT_TYPE_NOT_SUPPORTED',
            self::InvalidAgentResponse => 'INVALID_AGENT_RESPONSE',
            self::ExtendedCardNotConfigured => 'EXTENDED_AGENT_CARD_NOT_CONFIGURED',
            self::ExtensionRequired => 'EXTENSION_SUPPORT_REQUIRED',
            self::VersionNotSupported => 'VERSION_NOT_SUPPORTED',
            default => strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->name) ?? $this->name),
        };
    }
    public function httpStatus(): int
    {
        return match ($this) {
            self::TaskNotFound => 404,
            self::MethodNotFound => 404,
            self::InternalError => 500,
            self::InvalidAgentResponse => 502,
            self::TaskNotCancelable => 409,
            self::ContentTypeNotSupported => 415,
            self::Unauthenticated => 401,
            self::PermissionDenied => 403,
            self::ResourceExhausted => 429,
            self::DeadlineExceeded => 504,
            default => 400,
        };
    }
    public function grpcStatus(): int
    {
        return match ($this) {
            self::TaskNotFound => 5,
            self::MethodNotFound, self::PushNotSupported, self::UnsupportedOperation, self::VersionNotSupported => 12,
            self::TaskNotCancelable, self::ExtendedCardNotConfigured, self::ExtensionRequired => 9,
            self::InternalError, self::InvalidAgentResponse => 13,
            self::Unauthenticated => 16,
            self::PermissionDenied => 7,
            self::ResourceExhausted => 8,
            self::DeadlineExceeded => 4,
            default => 3,
        };
    }
}

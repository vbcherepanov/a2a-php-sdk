<?php

declare(strict_types=1);

namespace A2A\Protocol;

use Google\Protobuf\Internal\Message;

final class Validator
{
    public function validate(Message $message): void
    {
        $name = substr($message::class, strrpos($message::class, '\\') + 1);
        $this->object(Json::data($message), $name, $name);
    }
    private function object(\stdClass $value, string $type, string $path): void
    {
        $schema = Schema::MESSAGES[$type] ?? null;
        if ($schema === null) {
            return;
        }
        foreach ($schema['fields'] as $name => $field) {
            $item = $value->{$name} ?? null;
            $emptyResponseField = $type === 'ListTasksResponse' && in_array($name, ['tasks', 'totalSize', 'nextPageToken'], true);
            if ($field['required'] && !$emptyResponseField && ($item === null || $item === '' || $item === [])) {
                throw ProtocolException::invalid($path.'.'.$name.' is required');
            }
            if ($item instanceof \stdClass) {
                $this->object($item, $field['type'], $path.'.'.$name);
            } elseif (is_array($item)) {
                foreach ($item as $index => $entry) {
                    if ($entry instanceof \stdClass) {
                        $this->object($entry, $field['type'], $path.'.'.$name.'.'.$index);
                    }
                }
            }
        }
        foreach ($schema['groups'] as $group) {
            $present = array_filter($group, static fn (string $name): bool => property_exists($value, $name));
            if (count($present) !== 1) {
                throw ProtocolException::invalid($path.' requires exactly one of '.implode(', ', $group));
            }
        }
        foreach (['historyLength', 'pageSize'] as $field) {
            if (isset($value->{$field}) && (!is_int($value->{$field}) || $value->{$field} < 0)) {
                throw ProtocolException::invalid($path.'.'.$field.' must be non-negative');
            }
        }
        if ($type === 'ListTasksRequest' && isset($value->pageSize) && ($value->pageSize < 1 || $value->pageSize > 100)) {
            throw ProtocolException::invalid('pageSize must be between 1 and 100');
        }
        if ($type === 'Message' && !in_array($value->role ?? null, ['ROLE_USER', 'ROLE_AGENT'], true)) {
            throw ProtocolException::invalid('Invalid message role');
        }
        if ($type === 'TaskStatus' && (!is_string($value->state ?? null) || $value->state === 'TASK_STATE_UNSPECIFIED')) {
            throw ProtocolException::invalid('Invalid task state');
        }
        $interfaceUrl = $value->url ?? '';
        if ($type === 'AgentInterface' && ($value->protocolBinding ?? '') === 'GRPC' && is_string($interfaceUrl) && !str_contains($interfaceUrl, '://')) {
            $interfaceUrl = 'http://'.$interfaceUrl;
        }
        if ($type === 'AgentInterface' && (!filter_var($interfaceUrl, FILTER_VALIDATE_URL) || ($value->protocolVersion ?? '') !== '1.0')) {
            throw ProtocolException::invalid('Invalid agent interface');
        }
        if ($type === 'Part' && isset($value->url) && (!is_string($value->url) || !filter_var($value->url, FILTER_VALIDATE_URL))) {
            throw ProtocolException::invalid('Invalid part URL');
        }
    }
}

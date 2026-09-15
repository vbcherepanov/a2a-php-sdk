<?php

declare(strict_types=1);

namespace A2A\Server;

use A2A\Observability\Metrics;
use A2A\Protocol\{ErrorCode, Json, Operation, ProtocolException, Validator};
use A2A\Security\Authenticator;
use Google\Protobuf\Internal\Message;
use Lf\A2a\V1\{AgentCard, StreamResponse};
use Psr\Log\LoggerInterface;

final readonly class Gateway
{
    public function __construct(private Dispatcher $dispatcher, private Authenticator $authenticator, private Metrics $metrics, private LoggerInterface $logger, private AgentCard $card, private ServerOptions $options)
    {
    }
    /** @param array<string, string> $headers
     * @return Message|iterable<StreamResponse>
     */
    public function invoke(Operation $operation, Message $request, array $headers, string $tenant = '', ?float $deadline = null): Message|iterable
    {
        $started = microtime(true);
        try {
            $context = $this->authenticator->authenticate($headers, $tenant, min($deadline ?? INF, $started + $this->options->requestTimeoutSeconds));
            $context->check();
            if ($context->tenant !== $tenant) {
                throw new ProtocolException(ErrorCode::PermissionDenied, 'Tenant mismatch');
            }
            $data = Json::data($request);
            if (isset($data->tenant) && $data->tenant !== $tenant) {
                throw ProtocolException::invalid('Tenant does not match endpoint');
            }
            foreach ($this->card->getCapabilities()?->getExtensions() ?? [] as $extension) {
                if ($extension->getRequired() && !in_array($extension->getUri(), $context->extensions, true)) {
                    throw new ProtocolException(ErrorCode::ExtensionRequired, 'Required extension was not requested');
                }
            }
            $response = $this->dispatcher->dispatch($operation, $request, $context);
            if ($response instanceof Message) {
                $this->validateResponse($response);
                $this->observe($operation, 'success', $started);
                return $response;
            }
            return (function () use ($response, $operation, $started, $context): iterable {
                $outcome = 'disconnected';
                try {
                    foreach ($response as $event) {
                        $context->check();
                        $this->validateResponse($event);
                        yield $event;
                    }
                    $outcome = 'success';
                } catch (\Throwable $error) {
                    $outcome = 'error';
                    throw $this->error($error, $operation);
                } finally {
                    $this->observe($operation, $outcome, $started);
                }
            })();
        } catch (\Throwable $error) {
            $this->observe($operation, 'error', $started);
            throw $this->error($error, $operation);
        }
    }
    private function validateResponse(Message $message): void
    {
        try {
            (new Validator())->validate($message);
            if (strlen($message->serializeToString()) > $this->options->maxMessageBytes) {
                throw new \LengthException('Response exceeds limit');
            }
        } catch (\Throwable $error) {
            throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Agent produced an invalid response', $error);
        }
    }
    private function error(\Throwable $error, Operation $operation): ProtocolException
    {
        $this->logger->error('A2A operation failed', ['operation' => $operation->value, 'exception_class' => $error::class]);
        return $error instanceof ProtocolException ? $error : new ProtocolException(ErrorCode::InternalError, 'Internal server error', $error);
    }
    private function observe(Operation $operation, string $outcome, float $start): void
    {
        $this->metrics->observe($operation->value, $outcome, microtime(true) - $start);
    }
}

<?php

declare(strict_types=1);

namespace A2A\Server;

use A2A\Protocol\{ErrorCode, ProtocolException};
use A2A\Security\CallContext;
use A2A\Storage\Proto\{Delivery, Record};
use A2A\Storage\TaskRepository;
use Lf\A2a\V1\{Task, TaskPushNotificationConfig};
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PushManager
{
    private HttpClientInterface $http;
    public function __construct(HttpClientInterface $http, private PushOptions $options, private TaskRepository $repository, private LoggerInterface $logger)
    {
        $this->http = $options->allowPrivateNetwork ? $http : new NoPrivateNetworkHttpClient($http);
    }
    public function validate(TaskPushNotificationConfig $config): void
    {
        $url = parse_url($config->getUrl());
        if ($url === false || !in_array($url['scheme'] ?? '', $this->options->requireHttps ? ['https'] : ['https', 'http'], true) || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || !in_array(strtolower($url['host'] ?? ''), $this->options->allowedHosts, true)) {
            throw ProtocolException::invalid('Webhook must use HTTPS and an explicitly allowed host');
        }
        $auth = $config->getAuthentication();
        if ($auth !== null && (!in_array(strtolower($auth->getScheme()), ['bearer', 'basic'], true) || preg_match('/[\r\n]/', $auth->getCredentials()))) {
            throw ProtocolException::invalid('Unsupported or invalid webhook authentication');
        }
        if (preg_match('/[\r\n]/', $config->getToken())) {
            throw ProtocolException::invalid('Invalid webhook token');
        }
    }
    public function enqueue(Record $record, Task $task): void
    {
        $this->assertCapacity($record, Lifecycle::stopped(Lifecycle::state($task)));
        foreach ($record->getConfigs() as $config) {
            if (count($record->getOutbox()) >= $this->options->maxPendingPerTask) {
                throw new ProtocolException(ErrorCode::ResourceExhausted, 'Webhook outbox limit reached');
            }
            $events = $record->getEvents();
            $event = count($events) > 0 ? clone $events[count($events) - 1] : (new \Lf\A2a\V1\StreamResponse())->setStatusUpdate((new \Lf\A2a\V1\TaskStatusUpdateEvent())->setTaskId($task->getId())->setContextId($task->getContextId())->setStatus($task->getStatus() ?? throw new \LogicException('Missing task status')));
            $record->getOutbox()[] = (new Delivery())->setId(Uuid::v7()->toRfc4122())->setTask(Lifecycle::project($task, null))->setConfig(clone $config)->setEvent($event);
        }
    }
    public function assertCapacity(Record $record, bool $final = false): void
    {
        $required = count($record->getOutbox()) + count($record->getConfigs()) * ($final ? 1 : 2);
        if ($required > $this->options->maxPendingPerTask) {
            throw new ProtocolException(ErrorCode::ResourceExhausted, 'Webhook outbox capacity exhausted');
        }
    }
    public function deliver(Record $record): void
    {
        $taskId = $record->getTask()?->getId() ?? '';
        $context = new CallContext($record->getPrincipal(), $record->getTenant());
        foreach ($record->getOutbox() as $delivery) {
            if ($delivery->getAttempts() >= $this->options->maxAttempts || $delivery->getNextAttempt() > microtime(true)) {
                continue;
            }
            $success = false;
            try {
                $config = $delivery->getConfig() ?? throw new \LogicException('Missing delivery config');
                $this->validate($config);
                $headers = ['Content-Type' => 'application/json', 'A2A-Version' => '1.0'];
                if ($config->getToken() !== '') {
                    $headers['X-A2A-Notification-Token'] = $config->getToken();
                }
                if (($auth = $config->getAuthentication()) !== null) {
                    $headers['Authorization'] = $auth->getScheme().' '.$auth->getCredentials();
                }
                $response = $this->http->request('POST', $config->getUrl(), [
                    'headers' => $headers,
                    'body' => ($delivery->getEvent() ?? throw new \LogicException('Missing webhook event'))->serializeToJsonString(),
                    'max_redirects' => 0, 'timeout' => $this->options->timeoutSeconds, 'max_duration' => $this->options->timeoutSeconds,
                ]);
                try {
                    $status = $response->getStatusCode();
                    if ($status < 200 || $status >= 300) {
                        throw new \RuntimeException('Webhook returned HTTP '.$status);
                    }
                    $success = true;
                } finally {
                    $response->cancel();
                }
            } catch (\Throwable $error) {
                $this->logger->warning('A2A webhook delivery failed', ['task_id' => $taskId, 'delivery_id' => $delivery->getId(), 'attempt' => $delivery->getAttempts() + 1, 'exception_class' => $error::class]);
            }
            $this->repository->update($context, $taskId, function (Record $current) use ($delivery, $success): void {
                $outbox = [];
                foreach ($current->getOutbox() as $item) {
                    if ($item->getId() === $delivery->getId()) {
                        if ($success) {
                            continue;
                        }
                        $item->setAttempts($item->getAttempts() + 1);
                        $item->setNextAttempt(microtime(true) + $this->options->retryDelaySeconds * (2 ** min($item->getAttempts(), 10)));
                    }
                    $outbox[] = $item;
                }
                $current->setOutbox($outbox);
            });
        }
    }
}

<?php

namespace Tests\Fakes\Notifications;

use App\Contracts\Notifications\PushNotificationSender;
use App\Data\Notifications\PushNotificationMessageData;
use App\Data\Notifications\PushSendResultData;

final class FakePushNotificationSender implements PushNotificationSender
{
    /** @var list<array{message: PushNotificationMessageData, tokenHash: string}> */
    public array $calls = [];

    /** @var list<array{message: PushNotificationMessageData, topic: string}> */
    public array $topicCalls = [];

    private PushSendResultData $nextResult;

    public function __construct()
    {
        $this->nextResult = new PushSendResultData(true, 'fake-provider-reference');
    }

    public function send(PushNotificationMessageData $message, string $deviceToken): PushSendResultData
    {
        $this->calls[] = ['message' => $message, 'tokenHash' => hash('sha256', $deviceToken)];

        return $this->nextResult;
    }

    public function sendToTopic(PushNotificationMessageData $message, string $topic): PushSendResultData
    {
        $this->topicCalls[] = ['message' => $message, 'topic' => $topic];

        return $this->nextResult;
    }

    public function respondWith(PushSendResultData $result): void
    {
        $this->nextResult = $result;
    }
}

<?php

namespace App\Contracts\Notifications;

use App\Data\Notifications\PushNotificationMessageData;
use App\Data\Notifications\PushSendResultData;

interface PushNotificationSender
{
    public function send(PushNotificationMessageData $message, string $deviceToken): PushSendResultData;

    public function sendToTopic(PushNotificationMessageData $message, string $topic): PushSendResultData;
}

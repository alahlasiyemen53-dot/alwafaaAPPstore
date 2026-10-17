<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';
}

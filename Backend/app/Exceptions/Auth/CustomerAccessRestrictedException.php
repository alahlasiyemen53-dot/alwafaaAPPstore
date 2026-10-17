<?php

namespace App\Exceptions\Auth;

use Exception;

class CustomerAccessRestrictedException extends Exception
{
    public function __construct(string $message = 'تم تقييد أو إيقاف هذا الحساب. يرجى التواصل مع الدعم الفني.')
    {
        parent::__construct($message, 403);
    }
}

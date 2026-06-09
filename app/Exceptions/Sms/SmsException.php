<?php

namespace App\Exceptions\Sms;

use Exception;
use Throwable;

class SmsException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $providerCode = null,
        public readonly ?string $messageId = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

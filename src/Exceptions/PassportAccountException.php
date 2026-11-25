<?php

namespace Ghazali\EPassPackage\Exceptions;

class PassportAccountException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly string $errorCode = 'PASSPORT_ACCOUNT_ERROR',
    ) {
        parent::__construct($message);
    }
}

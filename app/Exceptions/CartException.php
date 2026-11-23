<?php

namespace App\Exceptions;

use Exception;

/**
 * A cart operation was rejected by server-side validation.
 * $errorKey is a translation key under `store.` in lang files; the
 * customer-facing Arabic message is resolved at render time.
 */
class CartException extends Exception
{
    public function __construct(public readonly string $errorKey)
    {
        parent::__construct($errorKey);
    }
}

<?php

namespace App\Exceptions;

use Exception;

/**
 * Order creation was rejected during final transactional
 * revalidation (spec §5B). $errorKey is a `store.*` translation key;
 * $productName optionally names the offending line in Arabic.
 */
class OrderException extends Exception
{
    public function __construct(
        public readonly string $errorKey,
        public readonly ?string $productName = null,
    ) {
        parent::__construct($errorKey);
    }

    public function translatedMessage(): string
    {
        return __('store.'.$this->errorKey, ['product' => $this->productName ?? '']);
    }
}

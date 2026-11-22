<?php

namespace App\Support;

/** Per-request Content-Security-Policy nonce, shared by the middleware and the @nonce Blade directive. */
class Csp
{
    private ?string $nonce = null;

    public function nonce(): string
    {
        return $this->nonce ??= base64_encode(random_bytes(16));
    }
}

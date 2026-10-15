<?php
declare(strict_types=1);

namespace Fortext\ApiServisim\Exception;

final class ApiServisimHTTPError extends ApiServisimError
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $reason,
        public readonly string $body
    ) {
        parent::__construct('Apiservisim HTTP ' . $statusCode . ': ' . $reason);
    }
}

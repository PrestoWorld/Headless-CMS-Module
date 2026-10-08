<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Http;

final class ErrorCode
{
    public const BAD_REQUEST = 'bad_request';
    public const UNAUTHORIZED = 'unauthorized';
    public const FORBIDDEN = 'forbidden';
    public const NOT_FOUND = 'not_found';
    public const METHOD_NOT_ALLOWED = 'method_not_allowed';
    public const VALIDATION_ERROR = 'validation_error';
    public const INTERNAL_ERROR = 'internal_error';
}
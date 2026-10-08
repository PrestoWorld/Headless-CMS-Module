<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Tests\Support;

final class FakeBlogProvider extends FakeProvider
{
    public function __construct()
    {
        parent::__construct('blog');
    }
}
<?php

declare(strict_types=1);

namespace App\Support;

enum VideoProvider: string
{
    case Youtube = 'youtube';
    case File = 'file';
}

<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $template, array $data = [], string $layout = 'layouts/app'): void
    {
        view($template, $data, $layout);
    }
}


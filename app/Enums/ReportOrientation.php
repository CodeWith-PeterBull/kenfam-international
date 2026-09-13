<?php

namespace App\Enums;

enum ReportOrientation: string
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';

    public function layout(): string
    {
        return "reports.layouts.pdf-{$this->value}";
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

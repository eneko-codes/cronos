<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;

class Tooltip extends Component
{
    public function __construct(public readonly ?string $text = null) {}

    public function render()
    {
        return view('components.tooltip');
    }
}

<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Badge extends Component
{
    public string $variant;

    public string $size;

    /**
     * Create a new component instance.
     */
    public function __construct(string $variant = 'default', string $size = 'md')
    {
        $this->variant = $variant;
        $this->size = $size;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.badge', ['variant' => $this->variant]);
    }
}

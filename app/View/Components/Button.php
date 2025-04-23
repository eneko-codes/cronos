<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Button extends Component
{
    public $variant;

    public $size;

    public $disabled;

    /**
     * Create a new component instance.
     */
    public function __construct(
        $variant = 'default',
        $size = 'md',
        $disabled = false
    ) {
        $this->variant = $variant;
        $this->size = $size;
        $this->disabled = $disabled;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.button', [
            'variant' => $this->variant,
            'size' => $this->size,
            'disabled' => $this->disabled,
        ]);
    }
}

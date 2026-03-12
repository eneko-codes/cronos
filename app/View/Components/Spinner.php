<?php

declare(strict_types=1);

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Spinner extends Component
{
    /**
     * The size of the spinner.
     */
    public string $size;

    /**
     * Create a new component instance.
     */
    public function __construct(string $size = '4')
    {
        $this->size = $size;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.spinner');
    }
}

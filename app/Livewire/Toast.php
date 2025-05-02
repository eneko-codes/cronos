<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class Toast extends Component
{
    public array $toasts = [];

    #[On('add-toast')]
    public function addToast(string $message, string $variant = 'default'): void
    {
        $this->toasts[] = [
            'id' => uniqid(),
            'message' => $message,
            'variant' => $variant,
        ];
    }

    #[On('remove-toast')]
    public function removeToast(string $id): void
    {
        $this->toasts = array_filter(
            $this->toasts,
            fn ($toast) => $toast['id'] !== $id
        );
    }

    public function render()
    {
        return view('livewire.toast');
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Toast;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToastTest extends TestCase
{
  #[Test]
  public function it_shows_toast_from_session_flash()
  {
    Session::flash('toast', [
      'message' => 'Test message',
      'variant' => 'success',
      'persistant' => false,
    ]);

    Livewire::test(Toast::class)
      ->assertSee('Test message')
      ->assertSeeHtml('bg-green-50');
  }

  #[Test]
  public function it_shows_persistant_toast_from_session()
  {
    Session::flash('toast', [
      'message' => 'Important notice',
      'variant' => 'warning',
      'persistant' => true,
    ]);

    Livewire::test(Toast::class)
      ->assertSee('Important notice')
      ->assertSeeHtml('bg-yellow-50');
  }

  #[Test]
  public function it_shows_toast_from_dispatch()
  {
    Livewire::test(Toast::class)
      ->dispatch('add-toast', 'Dispatched message', 'error')
      ->assertSee('Dispatched message')
      ->assertSeeHtml('bg-red-50');
  }

  #[Test]
  public function it_removes_toast_after_animation()
  {
    $component = Livewire::test(Toast::class);

    // Add a toast
    $component
      ->dispatch('add-toast', 'Test message')
      ->assertSee('Test message');

    // Simulate removing the toast
    $firstToast = $component->get('toasts')[0];
    $component
      ->dispatch('remove-toast', $firstToast['id'])
      ->assertDontSee('Test message');
  }
}

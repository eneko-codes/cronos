<?php

namespace App\Livewire;

use App\Mail\LoginEmail;
use App\Models\LoginToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Livewire component for handling user login.
 */
#[Title('Login')]
class Login extends Component
{
  /**
   * User's email address.
   *
   * @var string
   */
  #[Validate('required|email|exists:users,email')]
  public $email = '';

  /**
   * Remember me option.
   *
   * @var bool
   */
  #[Validate('boolean')]
  public $remember = false;

  /**
   * Message displayed after sending the magic login link.
   *
   * @var string
   */
  public $tokenSentMessage = '';

  public $hasUsers;

  public function mount(): void
  {
    $this->hasUsers = User::exists();
  }

  /**
   * Sends a magic login link to the user's email address.
   *
   * @return void
   */
  public function sendMagicLink()
  {
    $this->validate();

    $user = User::where('email', $this->email)->first();
    $ipAddress = Request::ip();
    $userAgent = Request::header('User-Agent');
    $timestamp = Carbon::now()->toIso8601String();

    Log::channel('auth')->info('Login attempt initiated', [
      'user_id' => $user->id,
      'email' => $user->email,
      'name' => $user->name,
      'ip_address' => $ipAddress,
      'user_agent' => $userAgent,
      'timestamp' => $timestamp,
      'remember_me' => $this->remember,
    ]);

    // Generate a unique token
    $token = Str::random(60);

    // Create or update the token in the database
    LoginToken::updateOrCreate(
      ['user_id' => $user->id],
      [
        'token' => hash('sha256', $token),
        'expires_at' => Carbon::now()->addMinutes(15),
        'remember' => $this->remember, // Store remember preference
      ]
    );

    // Use queue to send the email with the magic link, including the 'remember' flag
    Mail::to($user->email)->queue(
      new LoginEmail($user, $token, $this->remember)
    );

    Log::channel('auth')->info('Login token generated and email queued', [
      'user_id' => $user->id,
      'email' => $user->email,
      'token_expires_at' => Carbon::now()->addMinutes(15)->toIso8601String(),
      'ip_address' => $ipAddress,
    ]);

    // Set a local message
    $this->tokenSentMessage = 'Click on the login link we sent to your email.';

    // Toast message for redirects
    $this->dispatch('add-toast', message: 'Login link sent!', variant: 'info');
  }

  /**
   * Renders the login view.
   *
   * @return \Illuminate\View\View
   */
  #[Layout('components.layouts.auth')]
  public function render()
  {
    return view('livewire.login');
  }
}

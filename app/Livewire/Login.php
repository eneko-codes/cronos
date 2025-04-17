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
  #[Validate('required|email')]
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
    // First validate only required and email format
    $this->validate([
      'email' => 'required|email',
      'remember' => 'boolean',
    ]);

    // Log the login attempt regardless of whether the email exists
    $ipAddress = Request::ip();
    $userAgent = Request::header('User-Agent');

    // Check if user exists
    $user = User::where('email', $this->email)->first();

    // If user doesn't exist, log the invalid email attempt
    if (!$user) {
      Log::info(
        'Magic link requested for non-existent email: ' . $this->email,
        [
          'email' => $this->email,
          'ip_address' => $ipAddress,
          'user_agent' => $userAgent,
        ]
      );

      $this->addError(
        'email',
        'The provided email does not match our records.'
      );
      return;
    }

    // Log successful magic link request
    Log::info('Magic link requested for user: ' . $user->name, [
      'email' => $this->email,
      'name' => $user->name,
      'user_id' => $user->id,
      'ip_address' => $ipAddress,
      'user_agent' => $userAgent,
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

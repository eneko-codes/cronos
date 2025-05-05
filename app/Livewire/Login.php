<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Auth\RequestMagicLinkAction;
use Illuminate\Support\Facades\Request;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Livewire component handling the user login form interaction.
 * Allows users to enter their email, request a magic link, and choose the "Remember Me" option.
 */
#[Title('Login')]
class Login extends Component
{
    /**
     * The user's email address, bound to the input field.
     *
     * @var string
     */
    #[Validate('required|email')]
    public $email = '';

    /**
     * The "Remember Me" option, bound to the checkbox.
     *
     * @var bool
     */
    #[Validate('boolean')]
    public $remember = false;

    /**
     * A message displayed on the form after a magic link has been successfully sent.
     * Empty if no message should be shown.
     *
     * @var string
     */
    public $tokenSentMessage = '';

    /**
     * Handles the submission of the login form to send a magic link.
     *
     * Validates the email and remember flag. Delegates the core logic of finding the user,
     * generating/storing the token, and queuing the email to the RequestMagicLinkAction.
     * Updates the component state to show a success message or an error if the email is not found.
     *
     * @param  RequestMagicLinkAction  $requestMagicLinkAction  The action responsible for sending the link.
     * @return void
     */
    public function sendMagicLink(RequestMagicLinkAction $requestMagicLinkAction)
    {
        // Validate basic email format and remember flag first.
        $this->validate([
            'email' => 'required|email',
            'remember' => 'boolean',
        ]);

        // Get request details
        $ipAddress = Request::ip();
        $userAgent = Request::header('User-Agent');

        // Execute the action to handle user lookup, token generation/storage, logging, and email queuing.
        $linkSent = $requestMagicLinkAction->handle(
            $this->email,
            $this->remember,
            $ipAddress,
            $userAgent
        );

        // If the action returns false, it means the user wasn't found.
        // Add the specific error message to the 'email' field for UI feedback.
        if (! $linkSent) {
            $this->addError(
                'email',
                'The provided email does not match our records.'
            );

            return; // Stop execution
        }

        // If the action was successful (returned true):
        // Update the component state to show the confirmation message.
        $this->tokenSentMessage = 'Click on the login link we sent to your email.';

        // Dispatch a browser event for a toast notification.
        $this->dispatch('add-toast', message: 'Login link sent!', variant: 'info');
    }

    /**
     * Renders the login view component.
     *
     * @return \Illuminate\View\View
     */
    #[Layout('components.layouts.auth')]
    public function render()
    {
        return view('livewire.login');
    }
}

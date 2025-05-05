<?php

namespace App\Jobs\Auth;

use App\Mail\LoginEmail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendLoginEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user instance.
     */
    public User $user;

    /**
     * The signed login URL.
     */
    public string $url;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, string $url)
    {
        $this->user = $user;
        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * Attempts to send the magic login email and logs the success.
     */
    public function handle(): void
    {
        try {
            Mail::to($this->user->email)->send(new LoginEmail($this->user, $this->url));

            Log::info('Magic login email sent successfully: '.$this->user->name, [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'name' => $this->user->name,
                'job' => self::class,
            ]);
        } catch (Throwable $exception) {
            // Rethrow the exception to trigger the failed() method
            throw $exception;
        }
    }

    /**
     * Handle a job failure.
     *
     * Logs the failure details when the job cannot be completed.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Failed to send magic login email: '.$this->user->name, [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'name' => $this->user->name,
            'job' => self::class,
            'exception_message' => $exception->getMessage(),
            'exception_trace' => $exception->getTraceAsString(), // Optional: for detailed debugging
        ]);
    }
}

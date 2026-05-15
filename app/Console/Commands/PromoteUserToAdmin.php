<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class PromoteUserToAdmin extends Command
{
    /**
     * The name and signature of the console command.
     * Example: php artisan user:promote admin@example.com
     *
     * @var string
     */
    protected $signature = 'user:promote {email : The email address of the user to promote}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote a user to be an administrator';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        // Basic validation
        $validator = Validator::make(
            ['email' => $email],
            [
                'email' => 'required|email|exists:users,email', // Ensure user exists
            ]
        );

        if ($validator->fails()) {
            $this->error('Invalid email provided or user not found:');
            foreach ($validator->errors()->all() as $error) {
                $this->error("- {$error}");
            }

            return Command::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user->isAdmin()) {
            $this->warn("User '{$email}' is already an admin.");

            return Command::SUCCESS;
        }

        $user->user_type = RoleType::Admin;
        $user->save();

        $this->info("User '{$email}' has been successfully promoted to admin.");

        return Command::SUCCESS;
    }
}

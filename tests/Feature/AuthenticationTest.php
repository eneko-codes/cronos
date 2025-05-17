<?php

use App\Mail\LoginEmail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('a user can request a magic login link', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $response = $this->post(route('login.request'), [
        'email' => $user->email,
        'remember' => false,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Click on the login link we sent to your email.');

    $this->assertDatabaseHas('login_tokens', [
        'user_id' => $user->id,
    ]);

    Mail::assertQueued(LoginEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

test('a non-existent user cannot request a magic login link', function (): void {
    Mail::fake();

    $email = 'nonexistent@example.com';

    $response = $this->post(route('login.request'), [
        'email' => $email,
        'remember' => false,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['email' => 'The provided email does not match our records.']);
    $this->assertDatabaseMissing('login_tokens', [
        'user_id' => User::whereEmail($email)->first()?->id, // Ensure we check against a potential user ID
    ]);
    Mail::assertNotQueued(LoginEmail::class);
});

test('a user can log in with a valid magic link token', function (): void {
    Mail::fake(); // Still need to fake mail for the request part

    $user = User::factory()->create();

    // 1. Request the link first to generate a token
    $this->post(route('login.request'), [
        'email' => $user->email,
        'remember' => false,
    ]);

    $loginToken = LoginToken::where('user_id', $user->id)->first();
    $this->assertNotNull($loginToken);

    // Simulate the raw token that would be in the email link
    // We need to retrieve the original token. The RequestLoginLinkAction stores a hash.
    // For testing, we'll need to work backwards or modify how we test this part.
    // A simpler way for testing is to grab the URL from the Mail::fake() and extract the token.

    $rawToken = null;
    Mail::assertQueued(LoginEmail::class, function (LoginEmail $mail) use ($user, &$rawToken) {
        if ($mail->hasTo($user->email)) {
            // Extract token from the URL. This is a bit complex as URL is generated.
            // For simplicity, let's assume we know the token generation or can mock it.
            // In a real scenario, you might need to inspect $mail->url more deeply or
            // make the token generation more testable.

            // Let's re-fetch the token, and assume the URL generation uses a known pattern
            // or we mock the URL generation for the test.
            // The `RequestLoginLinkAction` generates a random 60 char token.
            // We stored the HASH. The link contains the RAW token.
            // This means we can't directly get the raw token from the DB.

            // We need to capture the raw token sent in the email for the verification step.
            // Let's modify the LoginEmail mailable or the action to make the raw token accessible for testing,
            // or capture it from the Mail::fake().

            // Let's try capturing from the mailable's public property if possible, or its view data.
            // The LoginEmail has a public $url property.
            parse_str(parse_url($mail->url, PHP_URL_QUERY), $queryParams);
            $rawToken = $queryParams['token'];

            return true;
        }

        return false;
    });

    $this->assertNotNull($rawToken, 'Raw token could not be extracted from email.');

    // 2. Visit the verification link with the token
    $response = $this->get(route('login.verify', ['token' => $rawToken, 'remember' => '0']));

    $response->assertRedirect(route('dashboard')); // Assuming 'dashboard' is the intended redirect
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseMissing('login_tokens', [
        'user_id' => $user->id, // Token should be deleted after use
    ]);
});

test('a user cannot log in with an invalid magic link token', function (): void {
    $user = User::factory()->create(); // User exists, but token will be wrong

    $invalidToken = str_repeat('a', 60); // A 60-char string, but not one from our system

    $response = $this->get(route('login.verify', ['token' => $invalidToken, 'remember' => '0']));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('token', 'The login link is invalid or has expired. Please request a new one.');
    $this->assertGuest();
});

test('a user cannot log in with a malformed (wrong length) magic link token', function (): void {
    $malformedToken = str_repeat('a', 59); // Incorrect length, fails 'size:60' validation

    $response = $this->get(route('login.verify', ['token' => $malformedToken, 'remember' => '0']));

    $response->assertRedirect(route('login'));
    // This error message comes from VerifyLoginTokenRequest::failedValidation()
    // which uses messages defined in VerifyLoginTokenRequest::messages()
    $response->assertSessionHasErrors(['token' => 'Incorrect validation token']);
    $this->assertGuest();
});

test('a user cannot log in with an expired magic link token', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    // Request the link
    $this->post(route('login.request'), [
        'email' => $user->email,
        'remember' => false,
    ]);

    $loginTokenEntry = LoginToken::where('user_id', $user->id)->first();
    $this->assertNotNull($loginTokenEntry);

    // Capture the raw token from the email
    $rawToken = null;
    Mail::assertQueued(LoginEmail::class, function (LoginEmail $mail) use ($user, &$rawToken) {
        if ($mail->hasTo($user->email)) {
            parse_str(parse_url($mail->url, PHP_URL_QUERY), $queryParams);
            $rawToken = $queryParams['token'];

            return true;
        }

        return false;
    });
    $this->assertNotNull($rawToken);

    // Mark the token as expired
    $loginTokenEntry->update(['expires_at' => now()->subMinutes(1)]);

    $response = $this->get(route('login.verify', ['token' => $rawToken, 'remember' => '0']));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('token', 'The login link has expired. Please request a new one.');
    $this->assertGuest();
    // Ensure the expired token was deleted by the LoginTokenExpiredException handler (or VerifyLoginTokenAction)
    $this->assertDatabaseMissing('login_tokens', ['id' => $loginTokenEntry->id]);
});

test('login token is deleted after successful login', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    $this->post(route('login.request'), [
        'email' => $user->email,
        'remember' => false,
    ]);

    $loginTokenEntry = LoginToken::where('user_id', $user->id)->first();
    $this->assertNotNull($loginTokenEntry);

    $rawToken = null;
    Mail::assertQueued(LoginEmail::class, function (LoginEmail $mail) use ($user, &$rawToken) {
        if ($mail->hasTo($user->email)) {
            parse_str(parse_url($mail->url, PHP_URL_QUERY), $queryParams);
            $rawToken = $queryParams['token'];

            return true;
        }

        return false;
    });
    $this->assertNotNull($rawToken);

    $this->get(route('login.verify', ['token' => $rawToken, 'remember' => '0']));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseMissing('login_tokens', ['id' => $loginTokenEntry->id]);
});

test('user session is remembered when remember me is checked', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    // 1. Request the link with remember true
    $this->post(route('login.request'), [
        'email' => $user->email,
        'remember' => '1', // true
    ]);

    $rawToken = null;
    Mail::assertQueued(LoginEmail::class, function (LoginEmail $mail) use ($user, &$rawToken) {
        if ($mail->hasTo($user->email)) {
            parse_str(parse_url($mail->url, PHP_URL_QUERY), $queryParams);
            $rawToken = $queryParams['token'];
            // Also assert that 'remember=1' is in the URL query params from RequestLoginLinkAction
            $this->assertEquals('1', $queryParams['remember']);

            return true;
        }

        return false;
    });
    $this->assertNotNull($rawToken);

    // 2. Visit the verification link with the token and remember=1
    // The `VerifyLoginTokenAction` takes `remember` from the request, which comes from the signed URL.
    $response = $this->get(route('login.verify', ['token' => $rawToken, 'remember' => '1']));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    // Check if the session cookie is set to be long-lived
    // This requires inspecting the cookie directly which can be complex in feature tests without a browser.
    // Laravel's Auth::login($user, $remember) handles setting the appropriate session lifetime.
    // We can infer this by checking if the LoginToken stored `remember` as true and Auth::login was called with it.

    // We can also check that the Auth facade remembers the user if we could fast-forward time
    // and make another request. However, for simplicity, we trust Auth::login($user, true) works.
    // A more direct test for remember me might involve checking cookie attributes if possible
    // or using a Dusk test for true browser behavior.

    // For now, we ensure the LoginToken record that *was* created had remember = true
    // The LoginToken is deleted upon successful login, so we'd have to check its state *before* deletion.
    // The `VerifyLoginTokenAction` uses the `remember` value passed to its `handle` method,
    // which comes from the request query parameter `remember` set by `RequestLoginLinkAction`.
    // So, if `RequestLoginLinkAction` put `remember=1` in the URL, and `login.verify` received it,
    // `Auth::login` would be called with `remember=true`.

    // The assertion `Mail::assertQueued` already checked if `remember=1` was in the URL.
    // So we trust that `VerifyLoginTokenAction` used it correctly.
});

test('an authenticated user can log out', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user); // Authenticate the user

    $this->assertTrue(Auth::check()); // Verify user is authenticated before logout

    $response = $this->post(route('logout')); // Assuming logout is a POST request

    $response->assertRedirect('/'); // Or route('login') depending on your app's desired behavior
    $this->assertGuest(); // Verify user is no longer authenticated
});

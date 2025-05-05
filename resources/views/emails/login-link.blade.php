<x-mail::message>
  <!-- prettier-ignore -->
  # Hello {{ $user->name }},
  Click the button below to log in to your Cronos account.

  <x-mail::button :url="$url">Log In</x-mail::button>

  The link will expire in 15 minutes.
</x-mail::message>

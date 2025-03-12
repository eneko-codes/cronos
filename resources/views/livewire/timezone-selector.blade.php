<div>
  <select
    wire:model.live="timezone"
    class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800"
  >
    @foreach (timezone_identifiers_list() as $tz)
      <option value="{{ $tz }}">
        {{ $tz }}
      </option>
    @endforeach
  </select>
</div>

@props([
  'checked' => false,
  'disabled' => false,
  'name' => null,
  'id' => null,
  'model' => null,
])

<label
  @class([
    'relative inline-flex h-6 w-11 cursor-pointer items-center rounded-full border-2 transition-colors duration-200 ease-in-out',
    'border-blue-700 bg-blue-600' => $checked && ! $disabled,
    'border-gray-300 bg-gray-200 dark:border-gray-600 dark:bg-gray-700' =>
      ! $checked && ! $disabled,
    'cursor-not-allowed border-gray-400 bg-gray-300 opacity-50 dark:border-gray-500 dark:bg-gray-600' => $disabled,
    'focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2' => ! $disabled,
  ])
>
  <span class="sr-only">Toggle switch</span>

  {{-- Hidden checkbox for Livewire compatibility --}}
  <input
    type="checkbox"
    @if($name) name="{{ $name }}" @endif
    @if($id) id="{{ $id }}" @endif
    @if($model) {{ $model }} @endif
    @checked($checked)
    @disabled($disabled)
    class="sr-only"
    role="switch"
    aria-checked="{{ $checked ? 'true' : 'false' }}"
  />

  {{-- Visual toggle knob --}}
  <span
    @class([
      'pointer-events-none inline-block h-4 w-4 transform rounded-full border border-gray-200 bg-white shadow-lg ring-0 transition duration-200 ease-in-out dark:border-gray-300',
      'translate-x-6' => $checked,
      'translate-x-1' => ! $checked,
    ])
    aria-hidden="true"
  ></span>
</label>

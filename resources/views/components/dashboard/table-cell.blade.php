@props([
  'dayData',
  'type' => 'scheduled',
  //scheduled,
  leave,
  attendance,
  worked'showDeviations' => false,
  'deviationType' => null,
  //attendanceVsScheduled,
  workedVsScheduled,
  workedVsAttendance,
])

@php
  $isFutureDate = $dayData->isFuture();
  $futureTextClass = $isFutureDate ? 'text-gray-400 dark:text-gray-500' : 'text-gray-700 dark:text-gray-300';
  $dataCellClasses = 'border border-gray-100 bg-white p-2 whitespace-nowrap dark:border-gray-600 dark:bg-gray-800';

  $value = match ($type) {
    'scheduled' => $dayData->schedule?->duration,
    'leave' => $dayData->leave?->duration,
    'attendance' => $dayData->attendance?->duration,
    'worked' => $dayData->worked?->duration,
    default => null,
  };

  $hasData = match ($type) {
    'scheduled' => $dayData->schedule?->hasData(),
    'leave' => $dayData->leave?->hasData(),
    'attendance' => $dayData->attendance?->hasData(),
    'worked' => $dayData->worked?->hasData(),
    default => false,
  };
@endphp

<td class="{{ $dataCellClasses }} {{ $futureTextClass }}">
  @if ($type === 'scheduled' && $hasData)
    <x-dashboard.scheduled-cell
      :schedule="$dayData->schedule"
      :future-text-class="$futureTextClass"
    />
  @elseif ($type === 'leave' && $hasData)
    <x-dashboard.leave-cell
      :leave="$dayData->leave"
      :future-text-class="$futureTextClass"
    />
  @elseif ($type === 'attendance' && $hasData)
    <x-dashboard.attendance-cell
      :attendance="$dayData->attendance"
      :day-data="$dayData"
      :future-text-class="$futureTextClass"
    />
  @elseif ($type === 'worked' && $hasData)
    <x-dashboard.worked-cell
      :worked="$dayData->worked"
      :future-text-class="$futureTextClass"
    />
  @elseif ($showDeviations && $deviationType)
    <x-dashboard.deviation-cell
      :deviation="$dayData->{$deviationType}"
      :is-future-date="$isFutureDate"
    />
  @endif
</td>

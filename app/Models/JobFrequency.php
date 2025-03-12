<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class JobFrequency
 *
 * Represents the frequency configuration for scheduled jobs.
 *
 * @property string $job_class
 * @property string $frequency
 */
class JobFrequency extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'job_frequencies';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = ['frequency'];

  /**
   * Retrieves the available frequency options.
   *
   * @return array
   */
  public static function getFrequencyOptions(): array
  {
    return [
      'never' => 'Never',
      'everyMinute' => 'Every Minute',
      'everyFiveMinutes' => 'Every 5 Minutes',
      'everyFifteenMinutes' => 'Every 15 Minutes',
      'everyThirtyMinutes' => 'Every 30 Minutes',
      'hourly' => 'Every Hour',
      'everyTwoHours' => 'Every Two Hours',
      'everyThreeHours' => 'Every Three Hours',
      'everyFourHours' => 'Every Four Hours',
      'everySixHours' => 'Every Six Hours',
      'everyTwelveHours' => 'Every Twelve Hours',
      'dailyAt_9' => 'Daily at 9:00',
      'daily' => 'Daily at midnight',
      'weekly' => 'Weekly on Sunday',
      'twiceMonthly' => 'Twice Monthly (1st and 15th)',
      'monthly' => 'Monthly',
    ];
  }

  /**
   * Converts the frequency value to the corresponding schedule method.
   *
   * @return callable|null
   *
   * @throws \InvalidArgumentException If the frequency value is invalid.
   */
  public function getScheduleMethod(): ?callable
  {
    return match ($this->frequency) {
      'never' => null, // Skip scheduling
      'everyMinute' => fn($schedule) => $schedule->everyMinute(),
      'everyFiveMinutes' => fn($schedule) => $schedule->everyFiveMinutes(),
      'everyFifteenMinutes' => fn(
        $schedule
      ) => $schedule->everyFifteenMinutes(),
      'everyThirtyMinutes' => fn($schedule) => $schedule->everyThirtyMinutes(),
      'hourly' => fn($schedule) => $schedule->hourly(),
      'everyTwoHours' => fn($schedule) => $schedule->everyTwoHours(),
      'everyThreeHours' => fn($schedule) => $schedule->everyThreeHours(),
      'everyFourHours' => fn($schedule) => $schedule->everyFourHours(),
      'everySixHours' => fn($schedule) => $schedule->everySixHours(),
      'everyTwelveHours' => fn($schedule) => $schedule->everyTwelveHours(),
      'dailyAt_9' => fn($schedule) => $schedule->dailyAt('09:00'),
      'daily' => fn($schedule) => $schedule->daily(),
      'weekly' => fn($schedule) => $schedule->weeklyOn(7),
      'twiceMonthly' => fn($schedule) => $schedule->twiceMonthly(1, 15),
      'monthly' => fn($schedule) => $schedule->monthly(),
      default => throw new \InvalidArgumentException(
        "Invalid frequency: {$this->frequency}"
      ),
    };
  }

  /**
   * Finds a frequency record by job class or returns null.
   *
   * @param string $jobClass
   * @return self|null
   */
  public static function findOrDefault(string $jobClass): ?self
  {
    return static::where('job_class', $jobClass)->first();
  }

  /**
   * Retrieves the configuration.
   *
   * @return self
   */
  public static function getConfig(): self
  {
    return static::first() ??
      static::create(['frequency' => 'everyThirtyMinutes']);
  }

  /**
   * Retrieves the batch information.
   *
   * @return array
   */
  public static function getBatchInfo(): array
  {
    return [
      'name' => 'Full Synchronization',
      'description' =>
        'Synchronizes all data from Odoo, Proofhub, and Desktime',
    ];
  }
}

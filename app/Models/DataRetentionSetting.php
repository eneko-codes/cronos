<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class DataRetentionSetting
 *
 * Represents a data retention configuration for time-related user data.
 *
 * @property int $id
 * @property string $data_type
 * @property int $retention_days
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DataRetentionSetting extends Model
{
  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = ['data_type', 'retention_days'];

  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'retention_days' => 'integer',
  ];

  /**
   * Data types supported for retention policies
   *
   * @return array
   */
  public static function dataTypes(): array
  {
    return [
      'time_entries' => 'Time Entries',
      'user_attendances' => 'Attendance Records',
      'user_schedules' => 'Schedules',
      'user_leaves' => 'Leave Records',
    ];
  }

  /**
   * Get a list of predefined retention period options.
   *
   * @return array
   */
  public static function retentionOptions(): array
  {
    return [
      30 => '30 days',
      90 => '3 months',
      180 => '6 months',
      365 => '1 year',
      730 => '2 years',
      1095 => '3 years',
      1825 => '5 years',
    ];
  }

  /**
   * Get the retention setting for a specific data type.
   *
   * @param string $dataType
   * @return static
   */
  public static function forType(string $dataType): self
  {
    return static::where('data_type', $dataType)->first();
  }
}

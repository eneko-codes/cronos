<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
 *
 * @mixin \Eloquent
 */
class Setting extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['key', 'value'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // We will handle casting dynamically in getValue/setValue for flexibility
    ];

    /**
     * Get a setting value by key.
     *
     * Always fetches directly from the database.
     *
     * @param  string  $key  The setting key.
     * @param  mixed  $default  The default value if the key is not found.
     * @return mixed The setting value.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = self::where('key', $key)->first();

        $value = $setting ? self::castValue($key, $setting->value) : $default;

        return $value;
    }

    /**
     * Set a setting value by key.
     *
     * Creates or updates the setting in the database.
     *
     * @param  string  $key  The setting key.
     * @param  mixed  $value  The setting value.
     * @return Setting The created or updated setting model.
     */
    public static function setValue(string $key, mixed $value): Setting
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) || is_object($value) ? json_encode($value) : $value,
            ]
        );

        Log::info("Setting '{$key}' updated.");

        return $setting;
    }

    /**
     * Dynamically cast the setting value based on its key.
     *
     * This allows flexibility without needing to predefine all casts.
     * Add more cases as needed.
     *
     * @param  string  $key  The setting key.
     * @param  mixed  $value  The raw value from the database.
     * @return mixed The casted value.
     */
    private static function castValue(string $key, mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        // Attempt to decode JSON if the value looks like a JSON string
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded; // Return as array if valid JSON
            }
        }

        // Example specific casts (expand as needed)
        switch ($key) {
            case str_starts_with($key, 'data_retention.'):
                // Assuming retention periods are integers (days)
                return (int) $value;
            case 'job_frequency.sync':
                // Frequency is likely a string (e.g., 'daily', 'hourly')
                return (string) $value;
            case 'notification.telescope_prune_frequency':
                // Frequency is likely a string
                return (string) $value;
                // Add more specific key-based casting rules here
                // case 'some_boolean_setting':
                //    // Consider adding boolean casting here for keys ending in .enabled
                //    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            default:
                // Default to string or original value if no specific cast matches
                return $value;
        }
    }
}

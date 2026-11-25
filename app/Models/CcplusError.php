<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcplusError extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $connection = 'globaldb';
    protected $table = 'ccplus_errors';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id', 'message', 'severity_id', 'explanation', 'suggestion', 'new_status', 'color'];

    public function severity()
    {
        return $this->belongsTo('App\Models\Severity', 'severity_id');
    }

    public function failedHarvests()
    {
        return $this->hasMany('App\Models\FailedHarvest');
    }

    public static function resolveThemeColor(int $errorId, ?array $errorData = null, string $fallbackColor = 'error'): string
    {
        if ($errorId === 0) {
            return $fallbackColor;
        }

        if (in_array($errorId, [3030, 3050, 3060, 3061, 3062, 3063, 9030], true)) {
            return 'success';
        }
        if (in_array($errorId, [1030, 2000, 2010, 2020, 9100], true)) {
            return 'warning';
        }
        if (in_array($errorId, [1000, 1010, 1011, 1020, 3031], true)) {
            return 'info';
        }
        if (in_array($errorId, [9200, 9300], true)) {
            return 'secondary';
        }

        $newStatus = $errorData['new_status'] ?? '';
        return match ($newStatus) {
            'Success'             => 'success',
            'BadCreds'            => 'warning',
            'ReQueued', 'Pending' => 'info',
            'Fail'                => 'error',
            default               => match (strtoupper((string) ($errorData['color'] ?? ''))) {
                '#00DD00' => 'success',
                '#FF9900' => 'warning',
                '#39AAE0' => 'info',
                '#999999' => 'secondary',
                '#DD0000' => 'error',
                default   => $fallbackColor,
            },
        };
    }
}

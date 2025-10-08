<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BulkUploadJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'upload_id',
        'user_id',
        'status',
        'csv_data',
        'results',
        'error_message',
        'total_rows',
        'processed_rows',
        'success_count',
        'error_count',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'csv_data' => 'array',
        'results' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the progress percentage
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }

    /**
     * Check if the job is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'failed']);
    }

    /**
     * Check if the job is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if the job failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}

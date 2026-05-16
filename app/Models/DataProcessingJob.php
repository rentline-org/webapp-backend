<?php

namespace App\Models;

use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $job_id
 * @property DataProcessingJobType $type
 * @property DataProcessingJobStatus $status
 * @property string|null $entity_type
 * @property array<array-key, mixed>|null $filters
 * @property string|null $file_name
 * @property string|null $file_path
 * @property string|null $original_file_name
 * @property int|null $total_rows
 * @property int|null $processed_rows
 * @property int|null $success_count
 * @property int|null $error_count
 * @property array<array-key, mixed>|null $errors
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob completed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob exports()
 * @method static \Database\Factories\DataProcessingJobFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob failed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob imports()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob processing()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereErrorCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereErrors($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereJobId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereOriginalFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereProcessedRows($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereSuccessCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereTotalRows($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataProcessingJob whereUserId($value)
 * @mixin \Eloquent
 */
class DataProcessingJob extends Model
{
    use HasFactory;
    protected $fillable = [
        'job_id',
        'type',
        'status',
        'entity_type',
        'filters',
        'file_name',
        'file_path',
        'original_file_name',
        'total_rows',
        'processed_rows',
        'success_count',
        'error_count',
        'errors',
        'error_message',
        'started_at',
        'completed_at',
        'user_id',
    ];

    protected $casts = [
        'type' => DataProcessingJobType::class,
        'status' => DataProcessingJobStatus::class,
        'filters' => 'array',
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', DataProcessingJobStatus::PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', DataProcessingJobStatus::PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', DataProcessingJobStatus::COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', DataProcessingJobStatus::FAILED);
    }

    public function scopeImports($query)
    {
        return $query->where('type', DataProcessingJobType::IMPORT);
    }

    public function scopeExports($query)
    {
        return $query->where('type', DataProcessingJobType::EXPORT);
    }

    public function isPending(): bool
    {
        return $this->status === DataProcessingJobStatus::PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === DataProcessingJobStatus::PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === DataProcessingJobStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === DataProcessingJobStatus::FAILED;
    }

    public function getProgressPercentage(): int
    {
        if (! $this->total_rows || $this->total_rows === 0) {
            return 0;
        }

        return min(100, (int) (($this->processed_rows / $this->total_rows) * 100));
    }

    public function getDownloadUrl(): ?string
    {
        if ($this->isCompleted() && $this->file_path) {
            return url('storage/' . $this->file_path);
        }

        return null;
    }
}

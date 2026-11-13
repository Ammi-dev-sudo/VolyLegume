<?php

namespace App\Models;

use App\Enums\IssuePhotoKind;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CropIssuePhoto extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'crop_issue_id',
        'photo_path',
        'kind',
        'caption',
        'taken_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => IssuePhotoKind::class,
            'taken_at' => 'datetime',
        ];
    }

    public function cropIssue(): BelongsTo
    {
        return $this->belongsTo(CropIssue::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

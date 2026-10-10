<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<int, int>|null $shared_with
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const TYPE_FILE = 'file';

    public const TYPE_FOLDER = 'folder';

    public const TYPE_LINK = 'link';

    public const TYPES = [
        self::TYPE_FILE,
        self::TYPE_FOLDER,
        self::TYPE_LINK,
    ];

    protected $table = 'documents';

    protected $fillable = [
        'company_id',
        'folder_id',
        'name',
        'type',
        'mime_type',
        'size',
        'url',
        'content',
        'version',
        'is_public',
        'shared_with',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
            'is_public' => 'boolean',
            'shared_with' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DocumentFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

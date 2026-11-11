<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only audit entry. Holds no personal data; subject_user_id is not a foreign key.
 *
 * @property int $id
 * @property string $event
 * @property int|null $subject_user_id
 * @property string $actor `cli` or an admin user ID
 * @property Carbon $created_at
 */
#[UseFactory(AuditLogFactory::class)]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['event', 'subject_user_id', 'actor'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'subject_user_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}

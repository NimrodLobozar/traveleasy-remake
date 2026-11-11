<?php

declare(strict_types=1);

use App\Models\AuditLog;

it('stores an entry for a user ID that has no users row', function () {
    $log = AuditLog::factory()->create(['subject_user_id' => 987654]);

    $log->refresh();

    expect($log->subject_user_id)->toBe(987654)
        ->and($log->created_at)->not->toBeNull()
        ->and($log->getAttributes())->not->toHaveKey('updated_at');
});

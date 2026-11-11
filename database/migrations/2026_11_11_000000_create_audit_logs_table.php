<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail. subject_user_id deliberately has no foreign key,
     * so entries survive the erasure of the user. No personal data is stored.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 64);
            $table->unsignedBigInteger('subject_user_id')->nullable();
            $table->string('actor', 64);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['subject_user_id', 'event']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

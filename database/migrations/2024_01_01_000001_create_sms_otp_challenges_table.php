<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('recipient', 191)->index();
            $table->string('purpose', 100)->index();
            $table->string('code_hash', 64);
            $table->timestamp('expires_at')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestamp('consumed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['recipient', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_otp_challenges');
    }
};

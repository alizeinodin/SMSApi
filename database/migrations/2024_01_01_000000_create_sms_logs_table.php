<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('driver')->nullable()->index();
            $table->string('operation')->index();
            $table->string('mobile')->nullable()->index();
            $table->text('content')->nullable();
            $table->string('template_id')->nullable();
            $table->json('parameters')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('message_id')->nullable()->index();
            $table->string('pack_id')->nullable()->index();
            $table->string('delivery_status')->nullable()->index();
            $table->unsignedSmallInteger('delivery_state')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('message')->nullable();
            $table->json('response')->nullable();
            $table->json('attempts')->nullable();
            $table->unsignedInteger('cost')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};

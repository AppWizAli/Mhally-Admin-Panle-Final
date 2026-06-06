<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('chat_messages', 'voice_duration')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->string('voice_duration', 30)->nullable()->after('message_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chat_messages', 'voice_duration')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropColumn('voice_duration');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE buyers SET email = NULL WHERE email IS NOT NULL AND TRIM(email) = ''");
        DB::statement("UPDATE suppliers SET email = NULL WHERE email IS NOT NULL AND TRIM(email) = ''");

        DB::statement("ALTER TABLE buyers MODIFY email VARCHAR(180) NULL DEFAULT NULL");
        DB::statement("ALTER TABLE suppliers MODIFY email VARCHAR(180) NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE buyers SET email = '' WHERE email IS NULL");
        DB::statement("UPDATE suppliers SET email = '' WHERE email IS NULL");

        DB::statement("ALTER TABLE buyers MODIFY email VARCHAR(180) NOT NULL");
        DB::statement("ALTER TABLE suppliers MODIFY email VARCHAR(180) NOT NULL");
    }
};

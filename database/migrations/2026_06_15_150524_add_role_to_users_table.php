<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ✅ إضافة حقل role بعد is_admin
            $table->string('role')->default('moderator')->after('is_admin');
        });

        // ✅ تحديث الأدمن الحالي ليكون دوره 'admin'
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);

        // ✅ تحديث باقي المستخدمين ليكون دورهم 'moderator'
        DB::table('users')->where('is_admin', false)->update(['role' => 'moderator']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};

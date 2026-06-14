<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB; // <--- هذا السطر هو الحل

return new class extends Migration
{
    public function up(): void
    {
        $socials = [
            'facebook',
            'instagram',
            'twitter',
            'linkedin',
            'whatsapp',
            'youtube',
            'tiktok',
        ];

        foreach ($socials as $key) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => '', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'facebook',
            'instagram',
            'twitter',
            'linkedin',
            'whatsapp',
            'youtube',
            'tiktok',
        ])->delete();
    }
};

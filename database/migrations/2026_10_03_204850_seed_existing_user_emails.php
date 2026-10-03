<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing users' emails to the user_emails table
        DB::table('users')
            ->whereNotNull('email')
            ->chunkById(100, function ($users) {
                $rows = [];
                foreach ($users as $user) {
                    $rows[] = [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'is_primary' => true,
                        'is_verified' => (bool) $user->email_verified_at,
                        'verified_at' => $user->email_verified_at,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ];
                }
                if (! empty($rows)) {
                    DB::table('user_emails')->insert($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot easily reverse, but we can truncate
        DB::table('user_emails')->truncate();
    }
};

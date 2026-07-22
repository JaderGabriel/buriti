<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->nullable()->after('name');
        });

        $users = DB::table('users')->select('id', 'email')->orderBy('id')->get();
        foreach ($users as $user) {
            $base = Str::of((string) $user->email)->before('@')->lower()->replaceMatches('/[^a-z0-9_]/', '')->value();
            if ($base === '') {
                $base = 'user'.$user->id;
            }

            $username = $base;
            $i = 1;
            while (
                DB::table('users')
                    ->where('username', $username)
                    ->where('id', '!=', $user->id)
                    ->exists()
            ) {
                $username = $base.$i;
                $i++;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};

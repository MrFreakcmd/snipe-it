<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumables_users', function (Blueprint $table) {
            $table->unsignedInteger('qty')->nullable()->default(1)->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('consumables_users', function (Blueprint $table) {
            $table->dropColumn('qty');
        });
    }
};

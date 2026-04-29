<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checkout_acceptances', function (Blueprint $table) {
            $table->boolean('signed_in_place')->default(false)->after('declined_at');
            $table->unsignedBigInteger('signed_in_place_admin')->nullable()->after('signed_in_place');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkout_acceptances', function (Blueprint $table) {
            $table->dropColumn(['signed_in_place', 'signed_in_place_admin']);
        });
    }
};


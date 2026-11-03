<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Repères écrits par le gérant pour guider le voyageur jusqu'à la résidence (lot 5). Donnés seulement après confirmation.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residence', function (Blueprint $table) {
            $table->string('reperes', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('residence', function (Blueprint $table) {
            $table->dropColumn('reperes');
        });
    }
};

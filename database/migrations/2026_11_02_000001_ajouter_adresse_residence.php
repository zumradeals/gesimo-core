<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adresse écrite de la résidence, renseignée par le gérant et donnée au voyageur seulement après confirmation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residence', function (Blueprint $table) {
            $table->string('adresse', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('residence', function (Blueprint $table) {
            $table->dropColumn('adresse');
        });
    }
};

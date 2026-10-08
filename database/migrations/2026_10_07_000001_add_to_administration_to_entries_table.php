<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El destino de un ingreso puede ser un inmueble, la administración o ambos.
     */
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->string('apartment', 20)->nullable()->change();
            $table->boolean('to_administration')->default(false)->after('apartment');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn('to_administration');
            $table->string('apartment', 20)->nullable(false)->change();
        });
    }
};

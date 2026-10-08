<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La aprobación de una salida de material vence a las 48 horas y el retiro
     * registra la placa del vehículo con el que sale.
     */
    public function up(): void
    {
        Schema::table('material_exits', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('approved_at');
            $table->string('exit_plate', 20)->nullable()->after('entry_id');
        });

        // Aprobaciones anteriores: también valen 48 horas desde que se aprobaron
        DB::table('material_exits')
            ->where('status', 'aprobada')
            ->whereNull('expires_at')
            ->whereNotNull('approved_at')
            ->get(['id', 'approved_at'])
            ->each(fn ($row) => DB::table('material_exits')->where('id', $row->id)
                ->update(['expires_at' => Carbon::parse($row->approved_at)->addHours(48)]));
    }

    public function down(): void
    {
        Schema::table('material_exits', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'exit_plate']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - Proveedores (ferreterías, depósitos…) que entregan material a una obra.
     * - Material de la casa sin trabajador dueño (lo entregó un proveedor).
     * - Autorizaciones de salida de material (sobrantes, escombros, devoluciones).
     */
    public function up(): void
    {
        // Solo MySQL valida el ENUM; en SQLite es texto y acepta cualquier valor
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE entries MODIFY COLUMN type ENUM('propietario','residente','autorizado','visitante','proveedor') NOT NULL DEFAULT 'visitante'");
        }

        Schema::table('entries', function (Blueprint $table) {
            $table->foreignId('work_id')->nullable()->after('work_worker_id')->constrained()->nullOnDelete();
            $table->string('supplier_company', 150)->nullable()->after('work_id');
        });

        Schema::table('work_items', function (Blueprint $table) {
            $table->foreignId('work_worker_id')->nullable()->change();
        });

        Schema::create('material_exits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->constrained()->cascadeOnDelete();
            $table->string('description', 150);
            $table->string('quantity', 50);
            $table->string('reason', 20); // sobrante | escombro | devolucion | otro
            $table->string('status', 20)->default('pendiente'); // pendiente | aprobada | rechazada | ejecutada
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('entry_id')->nullable()->constrained()->nullOnDelete(); // quién lo sacó
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['work_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_exits');

        Schema::table('entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_id');
            $table->dropColumn('supplier_company');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE entries MODIFY COLUMN type ENUM('propietario','residente','autorizado','visitante') NOT NULL DEFAULT 'visitante'");
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Obras (arreglos locativos y construcciones) con sus trabajadores y el
     * inventario de herramientas y materiales por casa y por trabajador.
     */
    public function up(): void
    {
        Schema::create('works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominium_id')->nullable()->constrained('condominiums')->nullOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('type', 20); // locativa | construccion
            $table->string('contractor_company', 150)->nullable();
            $table->string('contractor_name', 150);
            $table->string('contractor_document', 30)->nullable();
            $table->string('contractor_phone', 20)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('schedule', 100)->nullable(); // horario permitido, texto libre
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pendiente'); // pendiente | aprobada | rechazada | suspendida | cerrada
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'status']);
        });

        Schema::create('work_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('cedula', 20);
            $table->string('phone', 20)->nullable();
            $table->timestamps();

            $table->unique(['work_id', 'cedula']);
            $table->index('cedula');
        });

        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_worker_id')->constrained()->cascadeOnDelete(); // trabajador dueño
            $table->string('name', 120);
            $table->string('serial', 60)->nullable();
            $table->string('kind', 20); // herramienta | material
            $table->unsignedInteger('quantity_inside')->default(0);
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'kind']);
        });

        Schema::create('item_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_worker_id')->nullable()->constrained()->nullOnDelete(); // quién la movió
            $table->string('direction', 10); // ingreso | salida | queda
            $table->unsignedInteger('quantity');
            $table->boolean('is_transfer')->default(false);
            $table->string('photo_path')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominium_id')->nullable()->constrained('condominiums')->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value');
            $table->timestamps();

            $table->unique(['condominium_id', 'key']);
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->foreignId('work_worker_id')->nullable()->after('to_administration')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_worker_id');
        });

        Schema::dropIfExists('settings');
        Schema::dropIfExists('work_logs');
        Schema::dropIfExists('item_movements');
        Schema::dropIfExists('work_items');
        Schema::dropIfExists('work_workers');
        Schema::dropIfExists('works');
    }
};

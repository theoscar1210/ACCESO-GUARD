<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Authorization;
use App\Models\Entry;
use App\Models\ExitRecord;
use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\PropertyRental;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CondominiumDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🏢 Sembrando datos demo del condominio...');

        // -------------------------------------------------------
        // 1. VIGILANTES (3)
        // -------------------------------------------------------
        $vigilantes = [
            ['first_name' => 'Jorge',    'last_name' => 'Ramírez',  'cedula' => '10100001', 'username' => 'vigilante2', 'email' => 'vigilante2@demo.com'],
            ['first_name' => 'Luis',     'last_name' => 'Ortega',   'cedula' => '10100002', 'username' => 'vigilante3', 'email' => 'vigilante3@demo.com'],
            ['first_name' => 'Carlos',   'last_name' => 'Mendoza',  'cedula' => '10100003', 'username' => 'vigilante4', 'email' => 'vigilante4@demo.com'],
        ];

        $vigilanteUsers = [];
        foreach ($vigilantes as $v) {
            $user = User::firstOrCreate(['username' => $v['username']], array_merge($v, [
                'phone' => '300' . rand(1000000, 9999999),
                'password' => Hash::make('Vigilante@123'),
            ]));
            $user->syncRoles(['Vigilante']);
            $vigilanteUsers[] = $user;
        }

        // -------------------------------------------------------
        // 2. PROPIETARIOS + INMUEBLES (10 propiedades)
        // -------------------------------------------------------
        $propertiesData = [
            ['number' => '101', 'block' => 'Torre A', 'type' => 'apartamento'],
            ['number' => '102', 'block' => 'Torre A', 'type' => 'apartamento'],
            ['number' => '201', 'block' => 'Torre A', 'type' => 'apartamento'],
            ['number' => '202', 'block' => 'Torre A', 'type' => 'apartamento'],
            ['number' => '301', 'block' => 'Torre B', 'type' => 'apartamento'],
            ['number' => '302', 'block' => 'Torre B', 'type' => 'apartamento'],
            ['number' => 'Casa-01', 'block' => null, 'type' => 'casa'],
            ['number' => 'Casa-02', 'block' => null, 'type' => 'casa'],
            ['number' => 'Casa-03', 'block' => null, 'type' => 'casa'],
            ['number' => 'Local-01', 'block' => null, 'type' => 'local'],
        ];

        $propietariosData = [
            ['first_name' => 'Andrés',    'last_name' => 'Morales',    'cedula' => '20200001', 'username' => 'propietario2', 'email' => 'prop2@demo.com'],
            ['first_name' => 'Patricia',  'last_name' => 'Herrera',    'cedula' => '20200002', 'username' => 'propietario3', 'email' => 'prop3@demo.com'],
            ['first_name' => 'Ricardo',   'last_name' => 'Vargas',     'cedula' => '20200003', 'username' => 'propietario4', 'email' => 'prop4@demo.com'],
            ['first_name' => 'Claudia',   'last_name' => 'Jiménez',    'cedula' => '20200004', 'username' => 'propietario5', 'email' => 'prop5@demo.com'],
            ['first_name' => 'Fernando',  'last_name' => 'Castro',     'cedula' => '20200005', 'username' => 'propietario6', 'email' => 'prop6@demo.com'],
            ['first_name' => 'Sofía',     'last_name' => 'Restrepo',   'cedula' => '20200006', 'username' => 'propietario7', 'email' => 'prop7@demo.com'],
            ['first_name' => 'Mauricio',  'last_name' => 'Peña',       'cedula' => '20200007', 'username' => 'propietario8', 'email' => 'prop8@demo.com'],
            ['first_name' => 'Valentina', 'last_name' => 'Ríos',       'cedula' => '20200008', 'username' => 'propietario9', 'email' => 'prop9@demo.com'],
            ['first_name' => 'Sergio',    'last_name' => 'Montoya',    'cedula' => '20200009', 'username' => 'propietario10', 'email' => 'prop10@demo.com'],
            ['first_name' => 'Diana',     'last_name' => 'Salcedo',    'cedula' => '20200010', 'username' => 'propietario11', 'email' => 'prop11@demo.com'],
        ];

        $properties = [];
        $propietarios = [];

        foreach ($propertiesData as $i => $propData) {
            // Crear inmueble
            $property = Property::firstOrCreate(['number' => $propData['number']], $propData);
            $properties[] = $property;

            // Crear propietario
            $pd = $propietariosData[$i];
            $propietario = User::firstOrCreate(['username' => $pd['username']], array_merge($pd, [
                'phone' => '310' . rand(1000000, 9999999),
                'password' => Hash::make('Propietario@123'),
            ]));
            $propietario->syncRoles(['Propietario']);
            $propietarios[] = $propietario;

            // Asignar propietario al inmueble
            if (!$property->owners()->where('user_id', $propietario->id)->exists()) {
                $property->owners()->attach($propietario->id, [
                    'since_date' => Carbon::now()->subMonths(rand(6, 36))->format('Y-m-d'),
                ]);
            }
        }

        // -------------------------------------------------------
        // 3. RESIDENTES (5 — arriendan en apartamentos)
        // -------------------------------------------------------
        $residentesData = [
            ['first_name' => 'Camila',    'last_name' => 'Torres',     'cedula' => '30300001', 'username' => 'residente2', 'email' => 'res2@demo.com'],
            ['first_name' => 'Felipe',    'last_name' => 'Gutiérrez',  'cedula' => '30300002', 'username' => 'residente3', 'email' => 'res3@demo.com'],
            ['first_name' => 'Natalia',   'last_name' => 'Ospina',     'cedula' => '30300003', 'username' => 'residente4', 'email' => 'res4@demo.com'],
            ['first_name' => 'Miguel',    'last_name' => 'Arango',     'cedula' => '30300004', 'username' => 'residente5', 'email' => 'res5@demo.com'],
            ['first_name' => 'Laura',     'last_name' => 'Quintero',   'cedula' => '30300005', 'username' => 'residente6', 'email' => 'res6@demo.com'],
        ];

        // Propiedades 0-4 (Torre A: 101, 102, 201, 202, 301) tendrán arrendatario
        $residentes = [];
        foreach ($residentesData as $i => $rd) {
            $residente = User::firstOrCreate(['username' => $rd['username']], array_merge($rd, [
                'phone' => '320' . rand(1000000, 9999999),
                'password' => Hash::make('Residente@123'),
            ]));
            $residente->syncRoles(['Residente']);
            $residentes[] = $residente;

            // Asignar arrendamiento en la propiedad i
            $prop = $properties[$i];
            if (!PropertyRental::where('user_id', $residente->id)->where('property_id', $prop->id)->exists()) {
                PropertyRental::create([
                    'property_id' => $prop->id,
                    'user_id'     => $residente->id,
                    'start_date'  => Carbon::now()->subMonths(rand(1, 12))->format('Y-m-d'),
                    'end_date'    => Carbon::now()->addMonths(rand(3, 18))->format('Y-m-d'),
                    'is_active'   => true,
                ]);
            }
        }

        // -------------------------------------------------------
        // 4. NÚCLEO FAMILIAR (2-3 por propietario y residente)
        // -------------------------------------------------------
        $familyPool = [
            ['first_name' => 'Ana',       'last_name' => 'García',     'cedula' => '40400001', 'relationship' => 'esposa'],
            ['first_name' => 'Pedro',     'last_name' => 'Morales',    'cedula' => '40400002', 'relationship' => 'hijo'],
            ['first_name' => 'Elena',     'last_name' => 'Herrera',    'cedula' => '40400003', 'relationship' => 'hija'],
            ['first_name' => 'Roberto',   'last_name' => 'Vargas',     'cedula' => '40400004', 'relationship' => 'esposo'],
            ['first_name' => 'Lucia',     'last_name' => 'Jiménez',    'cedula' => '40400005', 'relationship' => 'madre'],
            ['first_name' => 'Tomás',     'last_name' => 'Castro',     'cedula' => '40400006', 'relationship' => 'hijo'],
            ['first_name' => 'Isabela',   'last_name' => 'Restrepo',   'cedula' => '40400007', 'relationship' => 'hija'],
            ['first_name' => 'Gonzalo',   'last_name' => 'Peña',       'cedula' => '40400008', 'relationship' => 'padre'],
            ['first_name' => 'Marcela',   'last_name' => 'Ríos',       'cedula' => '40400009', 'relationship' => 'esposa'],
            ['first_name' => 'Julián',    'last_name' => 'Montoya',    'cedula' => '40400010', 'relationship' => 'hermano'],
            ['first_name' => 'Daniela',   'last_name' => 'Salcedo',    'cedula' => '40400011', 'relationship' => 'hija'],
            ['first_name' => 'Santiago',  'last_name' => 'López',      'cedula' => '40400012', 'relationship' => 'hijo'],
            ['first_name' => 'Paola',     'last_name' => 'Ramos',      'cedula' => '40400013', 'relationship' => 'esposa'],
            ['first_name' => 'Alejandro', 'last_name' => 'Díaz',       'cedula' => '40400014', 'relationship' => 'esposo'],
            ['first_name' => 'Verónica',  'last_name' => 'Núñez',      'cedula' => '40400015', 'relationship' => 'hermana'],
        ];

        $familyIndex = 0;
        $allUsers = array_merge($propietarios, $residentes);
        foreach ($allUsers as $u) {
            $count = rand(1, 2);
            for ($j = 0; $j < $count && $familyIndex < count($familyPool); $j++, $familyIndex++) {
                $f = $familyPool[$familyIndex];
                if (!FamilyMember::where('cedula', $f['cedula'])->exists()) {
                    FamilyMember::create(array_merge($f, [
                        'user_id' => $u->id,
                        'phone'   => '315' . rand(1000000, 9999999),
                    ]));
                }
            }
        }

        // -------------------------------------------------------
        // 5. AUTORIZACIONES (mix activas / usadas / vencidas)
        // -------------------------------------------------------
        $visitantesAuth = [
            ['first_name' => 'Manuel',  'last_name' => 'Blanco',   'cedula' => '50500001'],
            ['first_name' => 'Teresa',  'last_name' => 'Vega',     'cedula' => '50500002'],
            ['first_name' => 'Héctor',  'last_name' => 'Fuentes',  'cedula' => '50500003'],
            ['first_name' => 'Gloria',  'last_name' => 'Parra',    'cedula' => '50500004'],
            ['first_name' => 'Ernesto', 'last_name' => 'Silva',    'cedula' => '50500005'],
            ['first_name' => 'Carmen',  'last_name' => 'Medina',   'cedula' => '50500006'],
            ['first_name' => 'Raúl',    'last_name' => 'Iglesias', 'cedula' => '50500007'],
            ['first_name' => 'Beatriz', 'last_name' => 'Lozano',   'cedula' => '50500008'],
        ];

        $authStatuses = ['activo', 'activo', 'activo', 'usado', 'usado', 'vencido', 'vencido', 'activo'];

        foreach ($visitantesAuth as $i => $va) {
            $status = $authStatuses[$i];
            $owner  = $allUsers[$i % count($allUsers)];
            $apt    = $properties[$i % count($properties)];

            $startDays  = $status === 'vencido' ? -30 : ($status === 'usado' ? -10 : -2);
            $endDays    = $status === 'vencido' ? -5  : ($status === 'activo' ? 30 : 0);

            if (!Authorization::where('cedula', $va['cedula'])->exists()) {
                Authorization::create(array_merge($va, [
                    'user_id'      => $owner->id,
                    'type'         => $i % 3 === 0 ? 'autorizado' : 'visitante',
                    'status'       => $status,
                    'start_date'   => Carbon::now()->addDays($startDays)->toDateString(),
                    'end_date'     => $status === 'activo' && $i % 2 === 0
                                        ? Carbon::now()->addDays($endDays)->toDateString()
                                        : ($status === 'vencido' ? Carbon::now()->addDays($endDays)->toDateString() : null),
                    'observations' => $i % 2 === 0 ? 'Visita programada' : null,
                ]));
            }
        }

        // -------------------------------------------------------
        // 6. INGRESOS — últimos 30 días (mezcla de tipos)
        // -------------------------------------------------------
        $vigilante = $vigilanteUsers[0];
        $entryTypes = ['propietario', 'residente', 'autorizado', 'visitante'];
        $vehicleTypes = ['ninguno', 'ninguno', 'automovil', 'camioneta', 'moto', 'bicicleta'];
        $plates = ['ABC123', 'XYZ456', 'DEF789', 'GHI012', null, null, null];

        // Inmueble propio de cada propietario y residente (el que tiene asignado o arrendado)
        $homeByCedula = [];
        foreach ($propietarios as $i => $p) {
            $homeByCedula[$p->cedula] = $properties[$i]->number;
        }
        foreach ($residentes as $i => $r) {
            $homeByCedula[$r->cedula] = $properties[$i]->number;
        }

        // La app no permite dos ingresos activos con la misma cédula
        $activeCedulas = [];

        // Los ingresos no usan firstOrCreate: si ya se sembraron, no se duplican
        $alreadySeeded = Entry::where('registered_by', $vigilante->username)->exists();

        for ($day = 29; $day >= 0 && ! $alreadySeeded; $day--) {
            $date      = Carbon::now()->subDays($day);
            $numToday  = rand(4, 12);

            for ($k = 0; $k < $numToday; $k++) {
                $type    = $entryTypes[array_rand($entryTypes)];
                $vehicle = $vehicleTypes[array_rand($vehicleTypes)];
                $plate   = $vehicle !== 'ninguno' ? $plates[array_rand($plates)] : null;

                // Propietario o residente real, del tipo elegido, entrando a su propio inmueble
                $group = $type === 'propietario' ? $propietarios : ($type === 'residente' ? $residentes : []);

                if (count($group) > 0) {
                    $person = $group[array_rand($group)];
                    $firstName = $person->first_name;
                    $lastName  = $person->last_name;
                    $cedula    = $person->cedula;
                    $apt       = $homeByCedula[$cedula];
                } else {
                    $firstName = ['Juan', 'Pedro', 'Ana', 'Luis', 'Sofia', 'Carlos'][ rand(0, 5)];
                    $lastName  = ['González', 'Pérez', 'Martínez', 'López', 'Rodríguez'][ rand(0, 4)];
                    $cedula    = (string) rand(10000000, 99999999);
                    $apt       = $properties[array_rand($properties)]->number;
                }

                $entryTime = $date->copy()->setTime(rand(6, 22), rand(0, 59));

                // Los ingresos de hoy no pueden quedar en el futuro
                if ($entryTime->isFuture()) {
                    $entryTime = Carbon::now()->subMinutes(rand(5, 180));
                }

                $entry = Entry::create([
                    'first_name'    => $firstName,
                    'last_name'     => $lastName,
                    'cedula'        => $cedula,
                    'apartment'     => $apt,
                    'type'          => $type,
                    'vehicle'       => $vehicle,
                    'plate'         => $plate ? strtoupper($plate) : null,
                    'observations'  => null,
                    'registered_by' => $vigilante->username,
                    'user_id'       => $vigilante->id,
                    'entry_at'      => $entryTime,
                ]);

                // 80% de los ingresos tienen salida registrada (excepto hoy); si la
                // persona ya quedó dentro con otro ingreso, este también sale
                $hasExit = $day > 0 && rand(1, 10) <= 8;
                if (! $hasExit && isset($activeCedulas[$cedula])) {
                    $hasExit = true;
                }
                if (! $hasExit) {
                    $activeCedulas[$cedula] = true;
                }

                if ($hasExit) {
                    $exitTime = $entryTime->copy()->addMinutes(rand(30, 480));

                    ExitRecord::create([
                        'entry_id'   => $entry->id,
                        'exited_at'  => $exitTime->isFuture() ? Carbon::now() : $exitTime,
                        'exited_by'  => $vigilante->username,
                    ]);
                }
            }
        }

        // -------------------------------------------------------
        // 7. COMUNICADOS (4 anuncios)
        // -------------------------------------------------------
        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'Administrador'))->first();

        if ($admin) {
            $announcements = [
                [
                    'title'  => 'Mantenimiento de ascensores — Torre A',
                    'body'   => 'Se informa a los residentes que el próximo lunes 27 se realizará mantenimiento preventivo de los ascensores de la Torre A entre las 8:00 am y las 12:00 pm. Disculpen las molestias.',
                    'target' => 'all',
                ],
                [
                    'title'  => 'Recordatorio: Pago de administración',
                    'body'   => 'Se recuerda a todos los propietarios que el pago de administración del mes en curso vence el día 5. Por favor realizar el pago a tiempo para evitar recargos.',
                    'target' => 'propietario',
                ],
                [
                    'title'  => 'Jornada de fumigación — Áreas comunes',
                    'body'   => 'Este viernes se realizará fumigación en todas las áreas comunes del conjunto. Se solicita retirar mascotas de jardines y pasillos entre las 7:00 am y las 10:00 am.',
                    'target' => 'all',
                ],
                [
                    'title'  => 'Bienvenida nuevos residentes',
                    'body'   => 'La administración da la bienvenida a las familias que se han integrado recientemente a nuestra comunidad. Estamos a su disposición para cualquier requerimiento.',
                    'target' => 'residente',
                ],
            ];

            foreach ($announcements as $i => $a) {
                Announcement::firstOrCreate(['title' => $a['title']], array_merge($a, [
                    'created_by' => $admin->id,
                    'send_push'  => true,
                    'created_at' => Carbon::now()->subDays(($i + 1) * 3),
                ]));
            }
        }

        $this->command->info('✅ Demo completado:');
        $this->command->info('   • 3 vigilantes adicionales');
        $this->command->info('   • 10 propietarios + 10 inmuebles');
        $this->command->info('   • 5 residentes con arrendamiento activo');
        $this->command->info('   • ~15 familiares registrados');
        $this->command->info('   • 8 autorizaciones (activas, usadas, vencidas)');
        $this->command->info('   • ~' . (30 * 8) . ' ingresos en los últimos 30 días');
        $this->command->info('   • 4 comunicados');
    }
}

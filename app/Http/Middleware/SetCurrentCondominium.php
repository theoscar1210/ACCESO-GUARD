<?php

namespace App\Http\Middleware;

use App\Models\Condominium;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentCondominium
{
    public function handle(Request $request, Closure $next): Response
    {
        // Evita arrastrar el condominio de una petición anterior en el mismo proceso
        App::forgetInstance('current_condominium');

        $user = $request->user();

        if ($user && $user->condominium_id) {
            $condominium = Condominium::find($user->condominium_id);

            if ($condominium) {
                // Disponible globalmente via app container para los controladores
                App::instance('current_condominium', $condominium);
            }
        }

        return $next($request);
    }
}

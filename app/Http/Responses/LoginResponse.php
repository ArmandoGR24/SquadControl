<?php

namespace App\Http\Responses;

use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $role = $request->user()?->role;
        $target = route('tareas.mis');

        if ($role === 'Lider de Cuadrilla') {
            $target = route('tareas.mis');
        }

        if (in_array($role, ['RH', 'Supervisor', 'Admin'], true)) {
            $target = route('dashboard');
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location($target);
        }

        return redirect()->intended($target);
    }
}

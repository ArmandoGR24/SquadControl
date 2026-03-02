<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;

class PasswordResetResponse implements PasswordResetResponseContract
{
    public function toResponse($request)
    {
        return redirect()->route('login')->with('status', 'Tu contraseña se restableció correctamente. Ya puedes iniciar sesión.');
    }
}

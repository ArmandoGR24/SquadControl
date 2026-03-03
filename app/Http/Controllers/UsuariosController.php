<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UsuariosController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'Admin', 403);
    }

    public function index()
    {
        $this->ensureAdmin(request());

        $usuarios = User::query()
            ->select(['id', 'name', 'email', 'role', 'status'])
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'email' => $user->email,
                    'rol' => $user->role ?? '',
                    'estado' => $user->status ?? 'Inactivo',
                ];
            })
            ->all();

        return Inertia::render('Usuarios', [
            'usuarios' => $usuarios,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'string', Rule::in(['Activo', 'Inactivo'])],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        User::create($validated);

        return redirect()->back();
    }

    public function update(Request $request, User $user)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'string', Rule::in(['Activo', 'Inactivo'])],
        ]);

        $user->update($validated);

        return redirect()->back();
    }

    public function destroy(User $user)
    {
        $this->ensureAdmin(request());

        $user->delete();

        return redirect()->back();
    }
}

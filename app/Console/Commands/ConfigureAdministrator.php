<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class ConfigureAdministrator extends Command
{
    protected $signature = 'neva:admin {email : Correo de la cuenta administrativa}';

    protected $description = 'Crear o habilitar explícitamente la única cuenta administrativa';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        if (Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
            $this->error('El correo no es válido.');

            return self::FAILURE;
        }
        if (User::where('is_admin', true)->where('is_active', true)->where('email', '!=', $email)->exists()) {
            $this->error('Ya existe otro administrador activo. Esta etapa admite una sola cuenta.');

            return self::FAILURE;
        }
        $user = User::where('email', $email)->first();
        if (! $user) {
            $name = $this->ask('Nombre del administrador');
            $password = $this->secret('Contraseña (mínimo 12 caracteres)');
            $check = Validator::make(compact('name', 'password'), ['name' => 'required|string|max:255', 'password' => 'required|string|min:12']);
            if ($check->fails()) {
                $this->error($check->errors()->first());

                return self::FAILURE;
            }
            $user = new User(compact('name', 'email', 'password'));
        }
        $user->forceFill(['is_admin' => true, 'is_active' => true])->save();
        $this->info('Cuenta administrativa habilitada. Las contraseñas existentes no se modifican.');

        return self::SUCCESS;
    }
}

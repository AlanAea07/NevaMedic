<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ConfigureAccount extends Command
{
    protected $signature = 'neva:account {email} {role : admin o publisher} {--name=} {--generate-password} {--reset-password}';

    protected $description = 'Crear una cuenta de administrador o publicador y configurar su contraseña';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $role = $this->argument('role');
        $name = $this->option('name') ?: $this->ask('Nombre');
        $check = Validator::make(compact('email', 'role', 'name'), ['email' => 'required|email|max:255', 'role' => 'required|in:admin,publisher', 'name' => 'required|string|max:255']);
        if ($check->fails()) {
            $this->error($check->errors()->first());

            return self::FAILURE;
        }
        $account = User::where('email', $email)->first();
        if ($account && ! $this->option('reset-password')) {
            $this->error('La cuenta ya existe. Usa --reset-password solo si deseas cambiar su contraseña.');

            return self::FAILURE;
        }
        $password = $this->option('generate-password') ? Str::password(20, symbols: false) : $this->secret('Contraseña (mínimo 12 caracteres)');
        if (! is_string($password) || strlen($password) < 12) {
            $this->error('La contraseña debe tener al menos 12 caracteres.');

            return self::FAILURE;
        }
        $account ??= new User;
        $account->forceFill(compact('name', 'email', 'role', 'password') + ['is_admin' => $role === 'admin', 'is_active' => true, 'remember_token' => null])->save();
        $this->info('Cuenta lista: '.$email.' ('.$role.')');
        if ($this->option('generate-password')) {
            $this->line('Contraseña temporal: '.$password);
        }

        return self::SUCCESS;
    }
}

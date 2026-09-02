<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateOwner extends Command
{
    protected $signature = 'app:create-owner
        {--name= : Nombre del propietario}
        {--email= : Correo electrónico del propietario}
        {--password= : Contraseña; prefiera --password-stdin en automatización}
        {--password-stdin : Lee la contraseña desde la entrada estándar}';

    protected $description = 'Crea el propietario inicial de la aplicación privada';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Nombre')));
        $email = Str::lower(trim((string) ($this->option('email') ?: $this->ask('Correo electrónico'))));
        $password = $this->resolvePassword();

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            $this->error('No se pudo crear el propietario. Revisa nombre, correo y requisitos de contraseña.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info('Propietario creado correctamente.');

        return self::SUCCESS;
    }

    private function resolvePassword(): string
    {
        if ((bool) $this->option('password-stdin')) {
            return trim((string) stream_get_contents(STDIN));
        }

        return (string) ($this->option('password') ?: $this->secret('Contraseña'));
    }
}

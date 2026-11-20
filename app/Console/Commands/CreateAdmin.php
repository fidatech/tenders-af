<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {--name=} {--email=} {--password=} {--sync : When the email already exists, give that administrator this name and password instead of failing}';

    protected $description = 'Create a system administrator who signs in at /admin';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: $this->ask('Full name'),
            'email' => strtolower((string) ($this->option('email') ?: $this->ask('Email address'))),
            'password' => $this->option('password') ?: $this->secret('Password (at least 8 characters)'),
        ];

        // used by the deploy: the administrator from the server's setup always exists and has its password
        if ($this->option('sync') && ($admin = Admin::where('email', $data['email'])->first())) {
            $admin->update(['name' => $data['name'], 'password' => $data['password'], 'role' => 'super', 'is_active' => true]);
            $this->info('Administrator '.$data['email'].' is up to date.');

            return self::SUCCESS;
        }

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:admins,email'],
            'password' => ['required', Password::min(8)],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        Admin::create($data);
        $this->info('Administrator created. Sign in at '.url('/admin/login'));

        return self::SUCCESS;
    }
}

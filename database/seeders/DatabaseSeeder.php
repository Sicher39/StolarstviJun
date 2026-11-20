<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                // The User model has a `hashed` cast, so the generated secret is never stored in plaintext.
                'password' => Str::password(),
            ]
        );

        $this->call([
            ContactSeeder::class,
            LegalDocumentSeeder::class,
            SeoPagesSeeder::class,
            PreviewSceneSeeder::class,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Contact;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        // Only fills an empty table, so re-seeding never overwrites data edited in the administration.
        Contact::current();
    }
}

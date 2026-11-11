<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('company_name');
            $table->string('street');
            $table->string('city');
            $table->string('contact_person');
            $table->string('email');
            $table->string('phone');
            $table->string('registration_number');
            $table->string('vat_number');
            $table->string('data_box');
            $table->string('bank_account');
            $table->string('registry_note');
            $table->string('google_maps_url');
            $table->string('waze_url');
            $table->string('seznam_maps_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

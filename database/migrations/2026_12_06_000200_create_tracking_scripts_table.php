<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Tracking scripty
|--------------------------------------------------------------------------
|
| The cookies and services the site may load. An entry of the necessary category describes a
| technical first-party cookie and carries no code; an entry of an optional category carries the
| snippet itself and is executed by the browser only after the matching consent.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_scripts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('position')->default('body_end');
            $table->text('description')->nullable();
            $table->string('provider_name')->nullable();
            $table->string('provider_privacy_url')->nullable();
            // The snippet is written by the operator; the application never ships a vendor snippet.
            $table->longText('code')->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('requires_consent')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();

            $table->index(['enabled', 'requires_consent', 'category']);
            $table->index(['position', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_scripts');
    }
};

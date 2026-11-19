<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mailbox the application sends its e-mail through.
 *
 * A single row: the site has one mailbox, so the outgoing (SMTP) server and the incoming (POP3) server are kept next to
 * each other and the office maintains them both in the admin panel. Nothing is read from `.env`, which is what lets the
 * settings survive a deployment and be changed without the server.
 *
 * The usernames and the passwords are plain text columns on purpose: the model encrypts them through its casts, so the
 * database never sees a readable credential and the model hides both passwords from every array representation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table): void {
            $table->id();

            // The mailbox stays off until the office switches it on, so storing the settings alone changes
            // nothing about how the site works today.
            $table->boolean('enabled')->default(false);

            $table->string('from_name')->nullable();
            $table->string('from_address')->nullable();

            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            // "tls" is STARTTLS on the usual 587, "ssl" is implicit TLS on 465 and "none" is a plain connection.
            $table->string('smtp_encryption', 10)->default('tls');
            $table->text('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();

            // The receiving side. Nothing in the application opens a POP3 session today, the values are stored
            // for the office and for a future inbox import.
            $table->string('pop3_host')->nullable();
            $table->unsignedSmallInteger('pop3_port')->nullable();
            $table->string('pop3_encryption', 10)->default('ssl');
            $table->text('pop3_username')->nullable();
            $table->text('pop3_password')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};

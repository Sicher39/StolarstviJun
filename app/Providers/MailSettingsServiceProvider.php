<?php

namespace App\Providers;

use App\Services\Mail\MailSettingsMailer;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;

/**
 * Makes the mailbox maintained in the admin panel the source of the outgoing mail configuration.
 *
 * The settings live in the database, so they cannot be read while the configuration is loaded - that would query the
 * database on every request, including the ones that send nothing, and it would break a fresh installation whose
 * migration has not run yet. The hook below therefore runs when the mail manager is first resolved, which happens only
 * when something really sends, and writes the stored mailbox into `mail.mailers.mailbox` and makes it the default.
 *
 * A switched-off or incomplete mailbox leaves the configuration untouched, so a fresh installation and the local `log`
 * mailer keep working exactly as before.
 */
class MailSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->afterResolving(MailManager::class, function (MailManager $manager): void {
            app(MailSettingsMailer::class)->register($manager);
        });
    }
}

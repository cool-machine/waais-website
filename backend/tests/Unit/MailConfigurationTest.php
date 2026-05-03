<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Locks in the production-mailer contract documented in
 * AZURE_PRODUCTION.md and the production App Service settings:
 * MAIL_MAILER=azure_communication_services routes through an SMTP
 * transport pointed at Azure Communication Services with credentials
 * coming from ACS_MAIL_USERNAME / ACS_MAIL_PASSWORD (NOT the generic
 * MAIL_USERNAME / MAIL_PASSWORD). Drift here would silently send
 * production email through whatever credentials happen to be in the
 * generic block.
 */
class MailConfigurationTest extends TestCase
{
    #[Test]
    public function azure_communication_services_mailer_uses_smtp_defaults(): void
    {
        $mailer = config('mail.mailers.azure_communication_services');

        $this->assertSame('smtp', $mailer['transport']);
        $this->assertSame('smtp.azurecomm.net', $mailer['host']);
        $this->assertSame(587, $mailer['port']);
    }

    #[Test]
    public function azure_communication_services_mailer_reads_credentials_from_acs_env_not_generic_mail_env(): void
    {
        // Both the generic and the ACS env names exist in the runtime,
        // but the production mailer must consult the ACS-prefixed ones.
        // If a future config refactor accidentally pointed the
        // username/password at MAIL_USERNAME/MAIL_PASSWORD, production
        // sends would silently fail or — worse — succeed using the
        // wrong identity.
        config()->set('app.env', 'testing');
        putenv('MAIL_USERNAME=should-not-be-used');
        putenv('MAIL_PASSWORD=should-not-be-used');
        putenv('ACS_MAIL_USERNAME=acs-username-from-env');
        putenv('ACS_MAIL_PASSWORD=acs-password-from-env');

        try {
            // Re-evaluate the mailers config block with the current env.
            $mailers = require __DIR__.'/../../config/mail.php';

            $this->assertSame(
                'acs-username-from-env',
                $mailers['mailers']['azure_communication_services']['username'],
            );
            $this->assertSame(
                'acs-password-from-env',
                $mailers['mailers']['azure_communication_services']['password'],
            );
        } finally {
            putenv('MAIL_USERNAME');
            putenv('MAIL_PASSWORD');
            putenv('ACS_MAIL_USERNAME');
            putenv('ACS_MAIL_PASSWORD');
        }
    }

    #[Test]
    public function azure_mailer_is_a_recognised_default_choice(): void
    {
        // Production sets MAIL_MAILER=azure_communication_services. If
        // someone ever renames that key in config/mail.php without
        // updating App Service settings, every production send will
        // fail with "Mailer [azure_communication_services] is not
        // defined." Catch that here.
        $mailers = config('mail.mailers');

        $this->assertArrayHasKey('azure_communication_services', $mailers);
    }

    #[Test]
    public function mail_default_and_from_address_are_env_driven(): void
    {
        // Production needs to switch the default mailer and the from
        // address through App Service settings without a code release
        // — moving to the custom mail.whartonai.studio sender, for
        // example, must be a single appsettings change. A static
        // source-level check is more robust here than a runtime
        // re-evaluation, because phpunit.xml seeds $_ENV with test
        // values that putenv() cannot dislodge mid-test.
        $source = (string) file_get_contents(__DIR__.'/../../config/mail.php');

        $this->assertStringContainsString(
            "env('MAIL_MAILER'",
            $source,
            'Default mailer must come from MAIL_MAILER env so production can pick azure_communication_services.',
        );
        $this->assertStringContainsString(
            "env('MAIL_FROM_ADDRESS'",
            $source,
            'Global from-address must come from MAIL_FROM_ADDRESS env so production can swap senders without a code release.',
        );
        $this->assertStringContainsString(
            "env('ACS_MAIL_USERNAME')",
            $source,
            'Azure mailer must read username from ACS_MAIL_USERNAME, not the generic MAIL_USERNAME.',
        );
        $this->assertStringContainsString(
            "env('ACS_MAIL_PASSWORD')",
            $source,
            'Azure mailer must read password from ACS_MAIL_PASSWORD, not the generic MAIL_PASSWORD.',
        );
    }
}

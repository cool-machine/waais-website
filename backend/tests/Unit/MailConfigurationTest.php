<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
}

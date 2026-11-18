<?php

namespace Tests\Feature;

use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * En production, Render (offre gratuite) bloque les ports SMTP sortants : les e-mails
 * partent par l'API HTTP de Resend (MAIL_MAILER=resend, RESEND_API_KEY).
 */
class MailResendTest extends TestCase
{
    public function test_le_mailer_resend_utilise_l_api_http(): void
    {
        config(['services.resend.key' => 're_test_cle_factice']);

        $transport = Mail::mailer('resend')->getSymfonyTransport();

        $this->assertInstanceOf(ResendTransport::class, $transport);
        $this->assertSame('resend', (string) $transport);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ContactFormTest extends TestCase
{
    /** @return array<string,string> */
    private function validPayload(): array
    {
        return [
            'name' => 'Facundo Pérez',
            'phone' => '092 000 086',
            'email' => 'facundo@example.com',
            'reason' => 'modelos',
            'message' => 'Quiero consultar por un modelo de 500W.',
            'website' => '',
        ];
    }

    public function test_contact_form_builds_safe_whatsapp_redirect(): void
    {
        config()->set('storefront_content.contact.whatsapp_number', '59892000086');

        $response = $this->post(route('contacto.store'), $this->validPayload());

        $response->assertRedirectContains('https://wa.me/59892000086?text=');
        self::assertStringContainsString('Facundo%20P%C3%A9rez', (string) $response->headers->get('Location'));
    }

    public function test_contact_form_rejects_invalid_payload(): void
    {
        $this->from('/contacto')->post(route('contacto.store'), [
            'name' => '<script>alert(1)</script>',
            'phone' => "1' OR 1=1 --",
            'reason' => 'invalid',
            'message' => 'corto',
        ])->assertRedirect('/contacto')->assertSessionHasErrors(['phone', 'reason', 'message']);
    }

    public function test_contact_form_is_rate_limited(): void
    {
        config()->set('storefront_content.contact.whatsapp_number', '59892000086');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('contacto.store'), $this->validPayload())->assertRedirect();
        }

        $this->post(route('contacto.store'), $this->validPayload())->assertStatus(429);
    }
}

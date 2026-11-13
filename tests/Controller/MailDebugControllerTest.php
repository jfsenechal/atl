<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MailDebugControllerTest extends WebTestCase
{
    public function testPageRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/mail-debug/');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', $client->getResponse()->headers->get('Location'));
    }

    public function testConsumeOnlyAcceptsPost(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/mail-debug/consume');

        self::assertResponseStatusCodeSame(405);
    }
}

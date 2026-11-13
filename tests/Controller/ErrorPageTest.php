<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use Twig\Environment;

final class ErrorPageTest extends WebTestCase
{
    public function testCustomTemplatesOverrideTheDefaultErrorPages(): void
    {
        self::bootKernel();
        $loader = self::getContainer()->get(Environment::class)->getLoader();

        self::assertTrue($loader->exists('@Twig/Exception/error404.html.twig'));
        self::assertTrue($loader->exists('@Twig/Exception/error500.html.twig'));
        self::assertTrue($loader->exists('@Twig/Exception/error.html.twig'));
    }

    public function testUnknownPageShowsCustom404(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('GET', '/this-page-does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Page non trouvée');
    }

    public function testServerErrorShowsCustom500WithoutLeakingTheMessage(): void
    {
        self::bootKernel(['debug' => false]);
        $renderer = self::getContainer()->get('error_renderer.html');
        \assert($renderer instanceof ErrorRendererInterface);

        $html = $renderer->render(new \RuntimeException('SQLSTATE secret password'))->getAsString();

        self::assertStringContainsString('Problème technique', $html);
        self::assertStringNotContainsString('SQLSTATE secret password', $html);
    }
}

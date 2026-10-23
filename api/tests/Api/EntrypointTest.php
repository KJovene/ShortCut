<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EntrypointTest extends WebTestCase
{
    public function testApiEntrypointRepondEnJsonLd(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('application/ld+json', (string) $client->getResponse()->headers->get('content-type'));
    }
}

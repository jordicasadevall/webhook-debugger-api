<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class WebhookDebuggerTestCase extends WebTestCase
{
    protected function resetDatabase(EntityManagerInterface $em): void
    {
        $em->getConnection()->executeStatement('TRUNCATE event, inbox RESTART IDENTITY CASCADE');

        // Rate limiter state is filesystem-backed and persists across test
        // methods; clear it so one test's requests don't exhaust another's quota.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }
}

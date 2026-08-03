<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class WebhookDebuggerTestCase extends WebTestCase
{
    protected function resetDatabase(EntityManagerInterface $em): void
    {
        $em->getConnection()->executeStatement('TRUNCATE event, inbox RESTART IDENTITY CASCADE');
    }
}

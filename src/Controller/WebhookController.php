<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Inbox;
use App\Exception\ApiException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class WebhookController
{
    private const MAX_BODY_BYTES = 1_000_000;

    public function __construct(
        #[Autowire(service: 'limiter.webhook_receive_ip')]
        private readonly RateLimiterFactory $ipLimiter,
        #[Autowire(service: 'limiter.webhook_receive_inbox')]
        private readonly RateLimiterFactory $inboxLimiter,
    ) {
    }

    #[Route('/in/{inbox}', name: 'webhook_receive', requirements: ['inbox' => Requirement::UUID])]
    public function receive(
        #[MapEntity(mapping: ['inbox' => 'id'])] Inbox $inbox,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (!$this->ipLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()
            || !$this->inboxLimiter->create((string) $inbox->getId())->consume()->isAccepted()) {
            throw ApiException::tooManyRequests('rate_limited', 'Too many requests, slow down');
        }

        $body = $request->getContent();

        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw ApiException::payloadTooLarge('payload_too_large', 'Request body exceeds maximum allowed size');
        }

        $bodyJson = null;
        if ('' !== $body && str_contains((string) $request->headers->get('Content-Type'), 'json')) {
            $decoded = json_decode($body, true);
            if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
                $bodyJson = $decoded;
            }
        }

        $event = new Event(
            inbox: $inbox,
            method: $request->getMethod(),
            url: $request->getUri(),
            headers: $request->headers->all(),
            queryParams: $request->query->all(),
            bodyRaw: '' !== $body ? $body : null,
            bodyJson: $bodyJson,
            clientIp: $request->getClientIp(),
        );

        $em->persist($event);
        $em->flush();

        return new JsonResponse(['status' => 'received', 'event_id' => (string) $event->getId()]);
    }
}

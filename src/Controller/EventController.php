<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Inbox;
use App\Exception\ApiException;
use App\Repository\EventRepository;
use App\Service\WebhookReplayer;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class EventController
{
    public function __construct(
        #[Autowire(service: 'limiter.webhook_replay')]
        private readonly RateLimiterFactory $replayLimiter,
    ) {
    }

    #[Route('/inboxes/{inbox}/events', name: 'event_list', methods: ['GET'], requirements: ['inbox' => Requirement::UUID])]
    public function list(
        #[MapEntity(mapping: ['inbox' => 'id'])] Inbox $inbox,
        Request $request,
        EventRepository $events,
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));

        $qb = $events->createQueryBuilder('e')
            ->where('e.inbox = :inbox')
            ->setParameter('inbox', $inbox->getId())
            ->orderBy('e.receivedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb);

        return new JsonResponse([
            'page' => $page,
            'limit' => $limit,
            'total' => count($paginator),
            'events' => array_map($this->serializeSummary(...), iterator_to_array($paginator)),
        ]);
    }

    #[Route('/inboxes/{inbox}/events/{event}', name: 'event_show', methods: ['GET'], requirements: ['inbox' => Requirement::UUID, 'event' => Requirement::UUID])]
    public function show(
        #[MapEntity(mapping: ['inbox' => 'id'])] Inbox $inbox,
        #[MapEntity(mapping: ['event' => 'id'])] Event $event,
    ): JsonResponse {
        $this->assertBelongsToInbox($event, $inbox);

        return new JsonResponse($this->serializeDetail($event));
    }

    #[Route('/inboxes/{inbox}/events/{event}/replay', name: 'event_replay', methods: ['POST'], requirements: ['inbox' => Requirement::UUID, 'event' => Requirement::UUID])]
    public function replay(
        #[MapEntity(mapping: ['inbox' => 'id'])] Inbox $inbox,
        #[MapEntity(mapping: ['event' => 'id'])] Event $event,
        Request $request,
        WebhookReplayer $replayer,
    ): JsonResponse {
        $this->assertBelongsToInbox($event, $inbox);

        if (!$this->replayLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            throw ApiException::tooManyRequests('rate_limited', 'Too many replay requests, slow down');
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $targetUrl = is_string($data['target_url'] ?? null) ? trim($data['target_url']) : '';

        if ('' === $targetUrl || false === filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            throw ApiException::unprocessable('invalid_target_url', 'target_url must be a valid URL');
        }

        return new JsonResponse($replayer->replay($event, $targetUrl));
    }

    private function assertBelongsToInbox(Event $event, Inbox $inbox): void
    {
        if (!$event->getInbox()->getId()->equals($inbox->getId())) {
            throw ApiException::notFound('not_found', 'Event not found');
        }
    }

    private function serializeSummary(Event $event): array
    {
        return [
            'id' => (string) $event->getId(),
            'method' => $event->getMethod(),
            'url' => $event->getUrl(),
            'client_ip' => $event->getClientIp(),
            'received_at' => $event->getReceivedAt()->format(DATE_ATOM),
        ];
    }

    private function serializeDetail(Event $event): array
    {
        return [
            'id' => (string) $event->getId(),
            'method' => $event->getMethod(),
            'url' => $event->getUrl(),
            'headers' => $event->getHeaders(),
            'query_params' => $event->getQueryParams(),
            'body_raw' => $event->getBodyRaw(),
            'body_json' => $event->getBodyJson(),
            'client_ip' => $event->getClientIp(),
            'received_at' => $event->getReceivedAt()->format(DATE_ATOM),
        ];
    }
}

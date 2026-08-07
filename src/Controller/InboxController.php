<?php

namespace App\Controller;

use App\Entity\Inbox;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InboxController
{
    #[Route('/inboxes', name: 'inbox_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];
        $name = is_string($data['name'] ?? null) ? trim($data['name']) : null;

        $inbox = new Inbox('' !== $name ? $name : null);
        $em->persist($inbox);
        $em->flush();

        return new JsonResponse($this->serialize($inbox, $urlGenerator), 201);
    }

    #[Route('/inboxes/{inbox}', name: 'inbox_show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['inbox' => 'id'])] Inbox $inbox, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        return new JsonResponse($this->serialize($inbox, $urlGenerator));
    }

    private function serialize(Inbox $inbox, UrlGeneratorInterface $urlGenerator): array
    {
        return [
            'id' => (string) $inbox->getId(),
            'name' => $inbox->getName(),
            'url' => $urlGenerator->generate('webhook_receive', ['inbox' => $inbox->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'created_at' => $inbox->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}

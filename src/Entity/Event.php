<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Index(columns: ['received_at'], name: 'idx_event_received_at')]
class Event
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Inbox::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Inbox $inbox;

    #[ORM\Column(length: 10)]
    private string $method;

    #[ORM\Column(type: 'text')]
    private string $url;

    #[ORM\Column(type: 'json')]
    private array $headers;

    #[ORM\Column(type: 'json')]
    private array $queryParams;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyRaw = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $bodyJson = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $clientIp = null;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    public function __construct(
        Inbox $inbox,
        string $method,
        string $url,
        array $headers,
        array $queryParams,
        ?string $bodyRaw,
        ?array $bodyJson,
        ?string $clientIp,
    ) {
        $this->id = Uuid::v4();
        $this->inbox = $inbox;
        $this->method = $method;
        $this->url = $url;
        $this->headers = $headers;
        $this->queryParams = $queryParams;
        $this->bodyRaw = $bodyRaw;
        $this->bodyJson = $bodyJson;
        $this->clientIp = $clientIp;
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getInbox(): Inbox
    {
        return $this->inbox;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getBodyRaw(): ?string
    {
        return $this->bodyRaw;
    }

    public function getBodyJson(): ?array
    {
        return $this->bodyJson;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }
}

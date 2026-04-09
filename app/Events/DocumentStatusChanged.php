<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $submissionId,
        public string $documentId,
        public ?string $statusFrom,
        public string $statusTo,
        public string $eventType,
        public string $submissionStatus,
        public int $documentsCount,
        public int $processedDocumentsCount,
        public int $failedDocumentsCount,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('submission.'.$this->submissionId),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        return [
            'submissionId' => $this->submissionId,
            'documentId' => $this->documentId,
            'statusFrom' => $this->statusFrom,
            'statusTo' => $this->statusTo,
            'eventType' => $this->eventType,
            'submissionStatus' => $this->submissionStatus,
            'documentsCount' => $this->documentsCount,
            'processedDocumentsCount' => $this->processedDocumentsCount,
            'failedDocumentsCount' => $this->failedDocumentsCount,
        ];
    }

    public function broadcastAs(): string
    {
        return 'document.status-changed';
    }
}

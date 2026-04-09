<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubmissionStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $submissionId,
        public ?string $statusFrom,
        public string $statusTo,
        public int $documentsCount,
        public int $processedDocumentsCount,
        public int $failedDocumentsCount,
        public ?string $completedAt,
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
            'statusFrom' => $this->statusFrom,
            'statusTo' => $this->statusTo,
            'documentsCount' => $this->documentsCount,
            'processedDocumentsCount' => $this->processedDocumentsCount,
            'failedDocumentsCount' => $this->failedDocumentsCount,
            'completedAt' => $this->completedAt,
        ];
    }

    public function broadcastAs(): string
    {
        return 'submission.status-changed';
    }
}

<?php

namespace App\Events;

use App\Http\Resources\VisaApplicantFileResource;
use App\Models\VisaApplicantFile;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

class VisaApplicantFileStored implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public VisaApplicantFile $visaApplicantFile)
    {
        $this->visaApplicantFile->loadMissing(['category', 'visaApplication']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('visa-applications.'.$this->visaApplicantFile->visa_application_id),
        ];
    }

    public function broadcastWith(): array
    {
        $resource = VisaApplicantFileResource::make($this->visaApplicantFile);

        return [
            'status' => 'stored',
            'file' => $resource->toArray(new Request()),
        ];
    }
}

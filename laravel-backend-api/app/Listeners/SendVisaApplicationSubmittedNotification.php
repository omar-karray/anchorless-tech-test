<?php

namespace App\Listeners;

use App\Events\VisaApplicationSubmitted;
use App\Mail\VisaApplicationSubmittedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendVisaApplicationSubmittedNotification implements ShouldQueue
{
    public string $queue = 'emails';

    /**
     * Handle the event.
     */
    public function handle(VisaApplicationSubmitted $event): void
    {
        $user = $event->visaApplication->applicant;

        if (! $user?->email) {
            return;
        }

        Mail::to($user->email)->queue(
            (new VisaApplicationSubmittedMail($event->visaApplication))->onQueue('emails')
        );
    }
}

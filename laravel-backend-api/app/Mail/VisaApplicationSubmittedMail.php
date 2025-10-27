<?php

namespace App\Mail;

use App\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VisaApplicationSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public VisaApplication $visaApplication)
    {
        //
    }

    public function build(): self
    {
        return $this->subject('Your visa application was submitted')
            ->view('emails.visa_application_submitted', [
                'visaApplication' => $this->visaApplication,
            ]);
    }
}

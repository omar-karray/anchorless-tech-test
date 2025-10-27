<?php

namespace App\Events;

use App\Models\VisaApplication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VisaApplicationSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public VisaApplication $visaApplication)
    {
        //
    }
}

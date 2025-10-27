<?php

use App\Models\VisaApplication;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('visa-applications.{visaApplication}', function ($user, VisaApplication $visaApplication) {
    return $user->can('view', $visaApplication);
});

<?php

use App\Models\VisaApplication;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('visa-applications.{visaApplicationId}', function ($user, $visaApplicationId) {
    $visaApplication = VisaApplication::find($visaApplicationId);
    
    if (!$visaApplication) {
        return false;
    }
    
    return $user->can('view', $visaApplication);
});

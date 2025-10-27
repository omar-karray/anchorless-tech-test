<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Auth\Access\Response;

class VisaApplicationPolicy
{
    /**
     * Determine whether the user can view the visa application.
     */
    public function view(User $user, VisaApplication $visaApplication): Response
    {
        return $visaApplication->applicant_id === $user->id
            ? Response::allow()
            : Response::deny('You are not allowed to access this visa application.');
    }

    /**
     * Determine whether the user can update the visa application.
     */
    public function update(User $user, VisaApplication $visaApplication): Response
    {
        return $this->view($user, $visaApplication);
    }

    /**
     * Determine whether the user can delete the visa application.
     */
    public function delete(User $user, VisaApplication $visaApplication): Response
    {
        return $this->view($user, $visaApplication);
    }
}

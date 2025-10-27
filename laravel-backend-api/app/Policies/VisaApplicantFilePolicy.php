<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisaApplicantFile;
use Illuminate\Auth\Access\Response;

class VisaApplicantFilePolicy
{
    /**
     * Determine whether the user can view the visa applicant file.
     */
    public function view(User $user, VisaApplicantFile $visaApplicantFile): Response
    {
        return $visaApplicantFile->applicant_id === $user->id
            ? Response::allow()
            : Response::deny('You are not allowed to access this visa application.');
    }

    /**
     * Determine whether the user can delete the visa applicant file.
     */
    public function delete(User $user, VisaApplicantFile $visaApplicantFile): Response
    {
        return $visaApplicantFile->applicant_id === $user->id
            ? Response::allow()
            : Response::deny('You are not allowed to delete this file.');
    }
}

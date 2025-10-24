<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get all visa applications for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function visaApplications()
    {
        return $this->hasMany(VisaApplication::class, 'applicant_id');
    }

    /**
     * Get all files uploaded by this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function visaApplicantFiles()
    {
        return $this->hasMany(VisaApplicantFile::class, 'applicant_id');
    }
}

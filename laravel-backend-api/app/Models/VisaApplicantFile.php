<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VisaApplicantFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'visa_application_id',
        'applicant_id',
        'file_category_id',
        'original_name',
        'stored_name',
        'mime_type',
        'size_bytes',
        'path',
        'disk',
    ];

    /**
     * Get the visa application this file belongs to.
     */
    public function visaApplication()
    {
        return $this->belongsTo(VisaApplication::class);
    }

    /**
     * Get the applicant (user) who uploaded this file.
     */
    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /**
     * Get the file category for this file.
     */
    public function category()
    {
        return $this->belongsTo(FileCategory::class, 'file_category_id');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_applicant_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_application_id')->constrained('visa_applications');
            $table->foreignId('applicant_id')->constrained('users');
            $table->foreignId('file_category_id')->constrained('file_categories');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('path');
            $table->string('disk');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_applicant_files');
    }
};

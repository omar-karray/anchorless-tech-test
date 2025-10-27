<p>Hello {{ $visaApplication->applicant?->name ?? 'there' }},</p>
<p>Your visa application (ID: {{ $visaApplication->id }}) for {{ $visaApplication->country }} was submitted successfully.</p>
<p>Status: {{ ucfirst($visaApplication->status) }}</p>
<p>Thank you!</p>

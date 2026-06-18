<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'kommo_lead_id',
        'q10_consecutivo',
        'status',
        'kommo_payload',
        'q10_payload',
        'error_message',
        'attempts',
    ];

    protected $casts = [
        'kommo_payload' => 'array',
        'q10_payload'   => 'array',
    ];

    public function markSuccess(string $consecutivo, array $q10Payload): void
    {
        $this->update([
            'status'        => 'success',
            'q10_consecutivo' => $consecutivo,
            'q10_payload'   => $q10Payload,
            'error_message' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $error,
            'attempts'      => $this->attempts + 1,
        ]);
    }
}
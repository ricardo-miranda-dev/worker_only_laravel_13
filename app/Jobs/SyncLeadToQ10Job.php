<?php

namespace App\Jobs;

use App\Models\SyncLog;
use App\Services\KommoService;
use App\Services\Q10Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncLeadToQ10Job implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;
    public int $backoff = 30;

    public function __construct(
        public readonly int $leadId
    ) {}

    public function handle(KommoService $kommo, Q10Service $q10): void
    {
        Log::info("SyncLeadToQ10Job iniciado", ['lead_id' => $this->leadId]);

        $syncLog = SyncLog::firstOrCreate(
            ['kommo_lead_id' => $this->leadId],
            ['status' => 'pending', 'attempts' => 0]
        );

        $syncLog->update(['attempts' => $syncLog->attempts + 1]);

        // 1. Obtener lead de Kommo
        $lead = $kommo->getLead($this->leadId);

        if (!$lead) {
            $syncLog->markFailed("No se pudo obtener el lead {$this->leadId} de Kommo");
            $this->fail("Lead {$this->leadId} no encontrado en Kommo");
            return;
        }

        $syncLog->update(['kommo_payload' => $lead]);

        // 2. Obtener contacto principal del lead
        $contactData = [];
        $contacts    = $lead['_embedded']['contacts'] ?? [];

        if (!empty($contacts)) {
            $contactId   = $contacts[0]['id'];
            $contact     = $kommo->getContact($contactId);
            $contactData = $this->extractContactData($contact ?? []);
        }

        // 3. Construir payload para Q10
        $oportunidadData = $this->buildOportunidadData($lead, $contactData);

        // 4. Registrar oportunidad en Q10
        $consecutivo = $q10->registrarOportunidad($oportunidadData);

        if (!$consecutivo) {
            $syncLog->markFailed("Q10 no devolvió consecutivo para lead {$this->leadId}");
            $this->fail("Error registrando oportunidad en Q10");
            return;
        }

        Log::info("Oportunidad registrada en Q10", [
            'lead_id'      => $this->leadId,
            'consecutivo'  => $consecutivo,
        ]);

        // 5. Registrar contacto en Q10
        $contactoConsecutivo = $q10->registrarContacto($consecutivo, array_merge($contactData, [
            'nombres'   => $contactData['nombres'] ?? $lead['name'] ?? 'Sin nombre',
            'apellidos' => $contactData['apellidos'] ?? '',
            'celular'   => $contactData['celular'] ?? '',
            'email'     => $contactData['email'] ?? '',
        ]));

        if (!$contactoConsecutivo) {
            Log::warning("Oportunidad creada pero contacto falló", [
                'lead_id'     => $this->leadId,
                'consecutivo' => $consecutivo,
            ]);
        }

        // 6. Marcar como exitoso
        $syncLog->markSuccess((string) $consecutivo, [
            'consecutivo_oportunidad' => $consecutivo,
            'consecutivo_contacto'    => $contactoConsecutivo,
            'payload_enviado'         => $oportunidadData,
        ]);

        Log::info("SyncLeadToQ10Job completado", [
            'lead_id'     => $this->leadId,
            'consecutivo' => $consecutivo,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SyncLeadToQ10Job falló definitivamente", [
            'lead_id' => $this->leadId,
            'error'   => $exception->getMessage(),
        ]);

        SyncLog::where('kommo_lead_id', $this->leadId)
            ->latest()
            ->first()
            ?->markFailed($exception->getMessage());
    }

    private function buildOportunidadData(array $lead, array $contactData): array
    {
        $customFields = $this->extractCustomFields($lead['custom_fields_values'] ?? []);

        return [
            'nombre'              => $lead['name'] ?? 'Sin nombre',
            'email'               => $contactData['email'] ?? $customFields['email'] ?? '',
            'celular'             => $contactData['celular'] ?? $customFields['phone'] ?? '',
            'telefono'            => $contactData['telefono'] ?? '',
            'numero_identificacion' => $customFields['identificacion'] ?? (string) $lead['id'],
            'tipo_identificacion' => $customFields['tipo_identificacion'] ?? null,
            'direccion'           => $customFields['direccion'] ?? '',
            'municipio'           => $customFields['municipio'] ?? null,
            'barrio'              => $customFields['barrio'] ?? null,
            'asesor'              => $customFields['asesor'] ?? null,
            'como_se_entero'      => $customFields['como_se_entero'] ?? null,
            'medio_contacto'      => $customFields['medio_contacto'] ?? null,
            'campos_personalizados' => [],
        ];
    }

    private function extractContactData(array $contact): array
    {
        $data   = [];
        $name   = $contact['name'] ?? '';
        $parts  = explode(' ', $name, 2);

        $data['nombres']   = $parts[0] ?? '';
        $data['apellidos'] = $parts[1] ?? '';

        foreach ($contact['custom_fields_values'] ?? [] as $field) {
            $fieldCode = strtolower($field['field_code'] ?? '');
            $value     = $field['values'][0]['value'] ?? '';

            match ($fieldCode) {
                'email' => $data['email']   = $value,
                'phone' => $data['celular'] = $value,
                default => null,
            };
        }

        return $data;
    }

    private function extractCustomFields(array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            $fieldCode = strtolower($field['field_code'] ?? $field['field_name'] ?? '');
            $value     = $field['values'][0]['value'] ?? '';

            $result[$fieldCode] = $value;
        }

        return $result;
    }
}
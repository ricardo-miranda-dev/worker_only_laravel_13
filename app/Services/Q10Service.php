<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Q10Service
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.q10.api_url');
        $this->apiKey  = config('services.q10.api_key');
    }

    public function registrarOportunidad(array $data): ?int
    {
        $payload = $this->buildOportunidadPayload($data);

        $response = Http::withHeaders([
            'Api-Key'       => $this->apiKey,
            'Cache-Control' => 'no-cache',
        ])->post("{$this->baseUrl}/oportunidades", $payload);

        if ($response->failed()) {
            Log::error('Q10 registrarOportunidad failed', [
                'payload'  => $payload,
                'response' => $response->json(),
            ]);
            return null;
        }

        return $response->json('Consecutivo_oportunidad');
    }

    public function registrarContacto(int $consecutivoOportunidad, array $data): ?int
    {
        $payload = $this->buildContactoPayload($consecutivoOportunidad, $data);

        $response = Http::withHeaders([
            'Api-Key'       => $this->apiKey,
            'Cache-Control' => 'no-cache',
        ])->post("{$this->baseUrl}/contactos", $payload);

        if ($response->failed()) {
            Log::error('Q10 registrarContacto failed', [
                'payload'  => $payload,
                'response' => $response->json(),
            ]);
            return null;
        }

        return $response->json('Consecutivo');
    }

    private function buildOportunidadPayload(array $data): array
    {
        $defaults = config('services.q10.defaults');

        return [
            'Nombre_oportunidad'                     => $data['nombre'] ?? 'Sin nombre',
            'Codigo_tipo_identificacion_oportunidad' => $data['tipo_identificacion'] ?? $defaults['tipo_identificacion'],
            'Numero_identificacion_oportunidad'      => $data['numero_identificacion'] ?? '0000000000',
            'Correo_electronico'                     => $data['email'] ?? '',
            'Celular'                                => $data['celular'] ?? '',
            'Telefono'                               => $data['telefono'] ?? '',
            'Direccion'                              => $data['direccion'] ?? '',
            'Codigo_municipio'                       => $data['municipio'] ?? $defaults['municipio'],
            'Codigo_barrio'                          => $data['barrio'] ?? $defaults['barrio'],
            'Numero_identificacion_asesor'           => $data['asesor'] ?? $defaults['numero_asesor'],
            'Consecutivo_como_se_entero'             => (int) ($data['como_se_entero'] ?? $defaults['como_se_entero']),
            'Consecutivo_medio_contacto'             => (int) ($data['medio_contacto'] ?? $defaults['medio_contacto']),
            'Campos_personalizados'                  => $this->buildCamposPersonalizados($data),
        ];
    }

    private function buildContactoPayload(int $consecutivoOportunidad, array $data): array
    {
        $detalle = [];

        if (!empty($data['celular'])) {
            $detalle[] = [
                'Tipo_detalle' => 'Celular',
                'Descripcion'  => $this->formatCelular($data['celular']),
            ];
        }

        if (!empty($data['email'])) {
            $detalle[] = [
                'Tipo_detalle' => 'Email',
                'Descripcion'  => $data['email'],
            ];
        }

        if (empty($detalle)) {
            $detalle = $this->defaultDetalle();
        }

        return [
            'Consecutivo_oportunidad' => $consecutivoOportunidad,
            'Nombres'                 => $data['nombres'] ?? '',
            'Apellidos'               => $data['apellidos'] ?? '',
            'Detalle'                 => $detalle,
            'Campos_personalizados'   => $data['campos_personalizados'] ?? [],
        ];
    }

    private function defaultDetalle(): array
    {
        return [
            [
                'Tipo_detalle' => 'Celular',
                'Descripcion'  => 'Sin información',
            ],
        ];
    }
    
    private function formatCelular(string $celular): string
    {
        // Quitar +, espacios y guiones
        $clean = preg_replace('/[\s\-\+]/', '', $celular);
        
        // Si empieza con 593 (Ecuador), quitarlo y dejar el número local
        if (str_starts_with($clean, '593')) {
            $clean = '0' . substr($clean, 3);
        }
        
        // Máximo 12 caracteres
        return substr($clean, 0, 12);
    }

    private function buildCamposPersonalizados(array $data): array
    {
        $campos = config('services.q10.campos_personalizados');

        return [
            [
                'Consecutivo_campo_personalizado'  => $campos['rango_edad']['id'],
                'Respuesta_campo_personalizado'    => $data['rango_edad'] ?? $campos['rango_edad']['default'],
            ],
        ];
    }
}

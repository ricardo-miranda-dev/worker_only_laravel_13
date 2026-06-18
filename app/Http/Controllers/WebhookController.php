<?php

namespace App\Http\Controllers;

use App\Jobs\SyncLeadToQ10Job;
use App\Services\KommoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function kommo(Request $request, KommoService $kommo): Response
    {
        Log::info('Webhook Kommo recibido', [
            'payload' => $request->all(),
            'ip'      => $request->ip(),
        ]);

        // Responder 200 inmediatamente — Kommo exige respuesta en menos de 2 segundos
        $leadId = $this->extractLeadId($request);

        if (!$leadId) {
            Log::warning('Webhook Kommo sin lead_id válido', ['payload' => $request->all()]);
            return response('', 200);
        }

        // Verificar si es un evento que nos interesa
        if (!$this->isRelevantEvent($request)) {
            Log::info('Webhook Kommo ignorado — evento no relevante', ['payload' => $request->all()]);
            return response('', 200);
        }

        // Despachar Job a la cola — procesamiento asíncrono
        SyncLeadToQ10Job::dispatch($leadId);

        Log::info('Job despachado', ['lead_id' => $leadId]);

        return response('', 200);
    }

    public function kommoExchange(Request $request, KommoService $kommo): Response
    {
        $code = $request->input('code');

        if (!$code) {
            Log::error('Kommo OAuth: código no recibido');
            return response('Código requerido', 400);
        }

        $success = $kommo->exchangeCode($code);

        if (!$success) {
            return response('Error en intercambio OAuth', 500);
        }

        Log::info('Kommo OAuth: tokens guardados correctamente');
        return response('OK', 200);
    }

    private function extractLeadId(Request $request): ?int
    {
        // Kommo envía el payload en formato x-www-form-urlencoded
        // Estructura: leads[add][0][id] o leads[update][0][id]
        $leads = $request->input('leads');

        if (!$leads) {
            return null;
        }

        // Evento de creación
        if (!empty($leads['add'][0]['id'])) {
            return (int) $leads['add'][0]['id'];
        }

        // Evento de actualización
        if (!empty($leads['update'][0]['id'])) {
            return (int) $leads['update'][0]['id'];
        }

        return null;
    }

    private function isRelevantEvent(Request $request): bool
    {
        $leads = $request->input('leads');

        if (!$leads) {
            return false;
        }

        // Solo nos interesan creaciones y actualizaciones de leads
        return !empty($leads['add']) || !empty($leads['update']);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CaseIngestService;
use App\Services\FhirBundleMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** POST /api/v1/notifications: JSON payload or FHIR R4 Bundle, idempotent through the Idempotency-Key header. */
class NotificationController extends Controller
{
    public function store(Request $request, CaseIngestService $ingest, FhirBundleMapper $fhir): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '' || strlen($key) > 80) {
            return response()->json(['error' => 'idempotency_key_required', 'message' => 'Send an Idempotency-Key header of at most 80 characters.'], 400);
        }
        $client = $request->attributes->get('api_client');
        $payload = $request->json()->all();
        if (($payload['resourceType'] ?? null) === 'Bundle') {
            $payload = $fhir->map($payload);
        }

        try {
            $res = $ingest->ingest($payload, 'api', $key, $client->orgUnit, $client);
        } catch (ValidationException $e) {
            $conflict = isset($e->errors()['idempotency_key']);

            return response()->json(['error' => $conflict ? 'idempotency_conflict' : 'validation_failed', 'errors' => $e->errors()], $conflict ? 409 : 422);
        }

        return response()->json([
            'status' => $res['duplicate'] ? 'duplicate' : 'applied',
            'reference' => $res['case']?->person?->pseudonym,
            'week_start' => $res['case']?->epi_week?->toDateString(),
        ], $res['duplicate'] ? 200 : 201);
    }
}

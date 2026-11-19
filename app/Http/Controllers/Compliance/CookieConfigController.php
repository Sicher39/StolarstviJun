<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Services\Compliance\CookieConfigPayload;
use Illuminate\Http\JsonResponse;

/**
 * The single JSON source of the cookie dialog.
 *
 * The dialog is rendered by the frontend bundle, so it can not read the administration records
 * itself; it asks this endpoint once per page load instead. The payload contains the stored texts, the
 * enabled cookies and services, the links to both legal documents and the categories that really carry
 * an optional script - without the last value the dialog could not tell whether there is anything to
 * ask for at all.
 */
class CookieConfigController extends Controller
{
    public function __invoke(CookieConfigPayload $payload): JsonResponse
    {
        return response()->json($payload->build());
    }
}

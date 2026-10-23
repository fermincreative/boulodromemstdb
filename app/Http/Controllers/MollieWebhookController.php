<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SettleOnlinePayment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Mollie posts only a payment id here; the status is always fetched from Mollie itself, so a forged call changes nothing.
 */
class MollieWebhookController extends Controller
{
    public function __invoke(Request $request, SettleOnlinePayment $settle): Response
    {
        $id = (string) $request->input('id');

        if (! preg_match('/^tr_[A-Za-z0-9]+$/', $id) || blank(config('services.mollie.key'))) {
            return response('', 200);
        }

        try {
            $settle->handle($id);
        } catch (RequestException $e) {
            // Unknown id (e.g. a test call): nothing to do. Otherwise Mollie retries later.
            if ($e->response->status() === 404) {
                return response('', 200);
            }

            Log::error('Mollie webhook failed', ['mollie_id' => $id, 'error' => $e->getMessage()]);

            return response('', 503);
        }

        return response('', 200);
    }
}

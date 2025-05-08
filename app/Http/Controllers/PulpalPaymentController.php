<?php

namespace App\Http\Controllers;

use App\Mail\PayInvoice;
use App\Services\ZohoApiService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use ShabnamYusifzada\Pulpal\Api\v1\TopUpPayment;

class PulpalPaymentController extends Controller
{
    public function mailInvoice(Request $request)
    {
        $pulpal_gate_url_list = $request->pulpal_gate_url_list;
        foreach ($pulpal_gate_url_list as $pulpal_gate_url) {
            # code...
            Mail::to($request->new_invoice['contact_persons_details'][0]['email'])->send(new PayInvoice($request));
            return redirect()->back()->with('success', 'Invoice sent successfully!');
        }
    }


    public function updateInvoiceCustomField(string $pulpal_gate_url, string $invoiceId)
    {
        $shortened_url = route('pulpal.short', [
            'short_id' => DB::table('shortened_urls')->insertGetId([
                'original' => $pulpal_gate_url
            ])
        ]);
        try {
            $updateData = [
                'custom_fields' => [
                    [
                        'customfield_id' => env('PULPAL_URL_FIELD_ID'),
                        'value' => $shortened_url,
                    ]
                ],
            ];

            // Construct the API endpoint URL
            $endpoint = "/books/v3/invoices/$invoiceId";
            $zohoService = new ZohoApiService();
            $response = $zohoService->makeApiCall(
                'PUT',
                $endpoint,
                $updateData
            );
            return $response;

        }
        catch (ValidationException $e) {
            $errors = collect($e->errors())->map(function ($messages, $field) {
                return ['field' => $field, 'messages' => $messages];
            })->values();

            return response()->json([
                'success' => false,
                'error' => 'Validation Error',
                'message' => 'Please check your input data',
                'errors' => $errors
            ], 422);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Zoho API Error',
                'message' => 'An unexpected error occurred while updating the custom field',
                'system_error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function markAsSent(string $invoice_id)
    {
        try {
            // Construct the API endpoint URL
            $endpoint = "books/v3/invoices/$invoice_id/status/sent";
            $zohoService = new ZohoApiService();
            $response = $zohoService->makeApiCall(
                'POST',
                $endpoint,
            );
            return $response;
        }
        catch (ValidationException $e) {
            $errors = collect($e->errors())->map(function ($messages, $field) {
                return ['field' => $field, 'messages' => $messages];
            })->values();

            return response()->json([
                'success' => false,
                'error' => 'Validation Error',
                'message' => 'Please check your input data',
                'errors' => $errors
            ], 422);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Zoho API Error',
                'message' => 'An unexpected error occurred while updating the custom field',
                'system_error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function embedPaymentURL(Request $request)
    {
        $pulpal_gate_url_list = $request->pulpal_gate_url_list;
        foreach ($pulpal_gate_url_list as $invoice_id => $pulpal_gate_url) {
            $response = self::updateInvoiceCustomField($pulpal_gate_url, $invoice_id);
            if (!$response->successful()) {
                $errorResponse = $response->json();
                return response()->json([
                    'success' => false,
                    'error' => 'Zoho API request failed',
                    'status' => $response->status(),
                    'zoho_error' => $errorResponse['message'] ?? $response->body(),
                    'code' => $errorResponse['code'] ?? null
                ], $response->status());
            }

            $response = self::markAsSent($invoice_id);
            if (!$response->successful()) {
                $errorResponse = $response->json();
                return response()->json([
                    'success' => false,
                    'error' => 'Zoho API request failed',
                    'status' => $response->status(),
                    'zoho_error' => $errorResponse['message'] ?? $response->body(),
                    'code' => $errorResponse['code'] ?? null
                ], $response->status());
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Pulpal URL Field Updated Successfully',
        ], 200);
    }
}

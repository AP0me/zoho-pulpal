<?php

namespace App\Http\Middleware;

use App\Services\ZohoApiService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class NewInvoice
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Validate the incoming request data
            $validatedData = $request->validate([
                'customer_id' => 'required|string',
                'line_item.item_id' => 'required|string',
                'line_item.quantity' => 'required|numeric|min:0.01',
                'line_item.rate' => 'required|numeric|min:0.01',
            ]);

            // Instantiate the Zoho service
            $zohoService = new ZohoApiService();

            // Prepare the invoice data with dynamic values from request
            $invoiceData = [
                'customer_id' => $validatedData['customer_id'],
                'date' => date('Y-m-d'),
                'line_items' => [$validatedData['line_item']],
                // Additional optional fields
                'reference_number' => 'INV-' . time(), // Auto-generated reference
                'notes' => 'Created via API', // Default note
            ];

            // Make the API call to create the invoice
            $response = $zohoService->makeApiCall(
                'POST',
                '/books/v3/invoices',
                $invoiceData,
            );

            // Check if the request was successful
            if ($response->successful()) {
                $data = $response->json();

                $request->merge([
                    'new_invoice' => $data['invoice'],
                ]);
                return $next($request);
            } else {
                $errorResponse = $response->json();
                return response()->json([
                    'success' => false,
                    'error' => 'Zoho API request failed',
                    'status' => $response->status(),
                    'zoho_error' => $errorResponse['message'] ?? $response->body(),
                    'code' => $errorResponse['code'] ?? null
                ], $response->status());
            }
        } catch (ValidationException $e) {
            // Handle validation errors with detailed field messages
            $errors = collect($e->errors())->map(function ($messages, $field) {
                return ['field' => $field, 'messages' => $messages];
            })->values();

            return response()->json([
                'success' => false,
                'error' => 'Validation Error',
                'message' => 'Please check your input data',
                'errors' => $errors
            ], 422);
        } catch (Exception $e) {
            // Log the full exception for debugging
            Log::error('Zoho Invoice Creation Error: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Zoho API Error',
                'message' => 'An unexpected error occurred while creating the invoice',
                'system_error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

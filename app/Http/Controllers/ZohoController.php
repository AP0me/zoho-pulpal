<?php

namespace App\Http\Controllers;

use App\Services\ZohoApiService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ZohoController extends Controller
{
    public function listCustomers(Request $request)
    {
        return $request->customers;
    }

    public function listItems(Request $request)
    {
        return $request->items;
    }

    public function newItem(Request $request)
    {
        try {
            // Validate the incoming request data
            $validatedData = $request->validate([
                'name' => 'required|string',
                'rate' => 'required|numeric|min:0.01',
            ]);

            // Instantiate the Zoho service
            $zohoService = new ZohoApiService();

            // Prepare the invoice data with dynamic values from request
            $itemData = [
                'name' => $validatedData['name'],
                'rate' => $validatedData['rate'],
            ];

            // Make the API call to create the invoice
            $response = $zohoService->makeApiCall(
                'POST',
                '/books/v3/items',
                $itemData,
            );

            // Check if the request was successful
            if ($response->successful()) {
                $data = $response->json();

                return response()->json([
                    'success' => true,
                    'message' => 'Item created successfully',
                    'data' => $data,
                ], 201);
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
            return response()->json([
                'success' => false,
                'error' => 'Zoho API Error',
                'message' => 'An unexpected error occurred while creating the item',
                'system_error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function newInvoice(Request $request)
    {
        return $request->new_invoice;
    }

    public function listInvoices(Request $request)
    {
        return $request->invoices;
    }

    public function payInvoice(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'paid_invoice' => 'required',
            ]);

            // Instantiate the Zoho service
            $zohoService = new ZohoApiService();

            // Prepare the payment data
            $paid_invoice = $validatedData['paid_invoice'];
            $paymentData = [
                'customer_id' => $paid_invoice['customer_id'],
                'invoices' => [
                    [
                        'invoice_id' => $paid_invoice['invoice_id'],
                        'amount_applied' => $paid_invoice['total']
                    ]
                ],
                'payment_mode' => 'Other',
                'date' => date('Y-m-d'),
                'amount' => $paid_invoice['total'],
                'account_id' => env('ZOHO_ACCOUNT_ID'),
                'exchange_rate' => 1,
            ];

            Log::info(['paymentData' => $paymentData]);

            // Make the API call to create the payment
            $response = $zohoService->makeApiCall(
                'POST',
                '/books/v3/customerpayments',
                $paymentData,
            );

            // Check if the request was successful
            Log::info(['response' => $response]);
            
            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'message' => 'Invoice payment processed successfully',
                    'payment' => $data
                ], 201);
            } else {
                return response()->json([
                    'error' => 'Zoho API request failed',
                    'status' => $response->status(),
                    'response' => $response->body()
                ], $response->status());
            }
        } catch (ValidationException $e) {
            // Handle validation errors
            return response()->json([
                'error' => 'Validation Error',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            // Handle other exceptions
            return response()->json([
                'error' => 'Zoho API Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

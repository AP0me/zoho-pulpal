<?php

namespace App\Http\Middleware;

use App\Services\ZohoApiService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ListInvoices
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Instantiate the Zoho service
            $zohoService = new ZohoApiService();

            // Get query parameters for filtering (optional)
            $params = [
                // 'page' => $request->query('page', 1),
                // 'per_page' => $request->query('per_page', 25),
                // 'sort_column' => $request->query('sort', 'date'),
                // 'sort_order' => $request->query('order', 'desc'),
                // 'invoice_number_contains' => $request->query('search'),
                // 'status' => $request->query('status'), // draft, sent, paid, etc.
            ];

            // Remove null parameters
            $params = array_filter($params);

            // Make the API call to get invoices
            $response = $zohoService->makeApiCall(
                'GET',
                '/books/v3/invoices',
                $params
            );

            // Check if the request was successful
            if ($response->successful()) {
                $data = $response->json();

                $request->merge([
                    'invoices' => $data['invoices'],
                ]);
            } else {
                return response()->json([
                    'error' => 'Zoho API request failed',
                    'status' => $response->status(),
                    'response' => $response->body()
                ], $response->status());
            }
        } catch (Exception $e) {
            // Handle exceptions
            return response()->json([
                'error' => 'Zoho API Error',
                'message' => $e->getMessage()
            ], 500);
        }
        return $next($request);
    }
}
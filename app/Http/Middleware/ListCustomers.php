<?php

namespace App\Http\Middleware;

use App\Services\ZohoApiService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ListCustomers
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Instantiate the service
            $zohoService = new ZohoApiService();

            // Example: Get a list of invoices from Zoho Books
            $response = $zohoService->makeApiCall(
                'GET',
                '/books/v3/customers',
            );

            // Check if the request was successful
            if ($response->successful()) {
                $data = $response->json();
                $request->merge([
                    'customers' => $data['contacts'],
                ]);
                return $next($request);
            } else {
                return response()->json([
                    'error' => 'Zoho API request failed',
                    'status' => $response->status(),
                    'response' => $response->body()
                ], $response->status());
            }
        } catch (Exception $e) {
            // Handle exceptions (e.g., token refresh failure)
            return response()->json([
                'error' => 'Zoho API Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

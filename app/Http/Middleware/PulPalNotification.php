<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PulPalNotification
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $validatedData = $request->validate([
            'ExternalId' => 'required',
            'Price' => 'required|integer|min:0',
            'invoices' => 'required',
        ]);
        
        $invoiceId = $validatedData['ExternalId'];
        $payedAmount = $validatedData['Price'];

        $invoice = null;
        $invoices = $request->invoices;
        for ($i=0; $i < count($invoices); $i++){
            if($invoiceId === $invoices[$i]['invoice_id']){
                $invoice = $invoices[$i];
            }
        }
        if($invoice===null){
            return response()->json(['status' => 'error', 'message' => 'invalid invoiceId from pulpal'], 500);
        }
        $expectedAmount = $invoice['total'];

        Log::info(['payedAmount' => $payedAmount, 'expectedAmount' => $expectedAmount]);
        if ($payedAmount !== $expectedAmount) {
            $request->merge([
                'paid_invoice' => $invoice,
            ]);

        } else {
            // Return a success response
            return response()->json([
                'status' => 'success',
                'message' => 'Payment successfully processed.'
            ], 200);
        }
        return $next($request);
    }
}

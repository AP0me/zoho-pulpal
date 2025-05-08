<?php

namespace App\Http\Middleware;

use App\Services\PulpalPaymentService;
use App\Services\ZohoApiService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class InitPayment
{
    public static function findOrGenProduct($zohoItems, $invoice_id)
    {
        $result = [];
        foreach ($zohoItems as $zohoItem) {
            // dd($zohoItem);
            $result = [
                'price' => ($result['price'] ?? 0) + (int)($zohoItem['rate'] * 100 * $zohoItem['quantity']),
                'externalId' => $invoice_id,
                'name' => [
                    'az' => ($result['name']['az'] ?? '') . ', ' . $zohoItem['name'],
                    'ru' => ($result['name']['ru'] ?? '') . ', ' . $zohoItem['name'],
                    'en' => ($result['name']['en'] ?? '') . ', ' . $zohoItem['name'],
                ],
                'description' => [
                    'az' => ($result['description']['az'] ?? '') . ', ' . $zohoItem['description'],
                    'ru' => ($result['description']['ru'] ?? '') . ', ' . $zohoItem['description'],
                    'en' => ($result['description']['en'] ?? '') . ', ' . $zohoItem['description'],
                ],
            ];
        }
        return $result;
    }

    public static function getLineItems($invoice_id)
    {
        try {
            // Instantiate the Zoho service
            $zohoService = new ZohoApiService();

            // Get query parameters for filtering (optional)
            // $params = [
            //     'recurring_invoice_id' => (array)$template_id_list,
            // ];
            // $params = array_filter($params);
            // dd("/books/v3/invoices/$invoice_id");
            $response = $zohoService->makeApiCall(
                'GET',
                "/books/v3/invoices/$invoice_id",
                // $params
            );

            if ($response->successful()) {
                $data = $response->json();
                // dd($data);
                return [
                    'error' => false,
                    'line_items' => $data['invoice']['line_items'],
                ];
            } else {
                return [
                    'error' => true,
                    'message' => $response,
                ];
            }
        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    public function handle(Request $request, Closure $next): Response
    {
        $request->validate([
            'invoices' => 'required|array',
            'invoices.*.total' => 'required|numeric|min:0',
            'invoices.*.customer_name' => 'required|string',
            'invoices.*.email' => 'required|email',
            'invoices.*.invoice_id' => 'required|string',
        ]);

        try {
            $dueInvoices = [];
            foreach ($request->invoices as $invoice) {
                if($invoice['status']==='draft'){
                    $dueInvoices[] = $invoice;
                }
            }

            $invoice_id_list = [];
            foreach ($dueInvoices as $dueInvoice) {
                $invoice_id_list[$dueInvoice['invoice_id']] = true;
            }
            $invoice_id_list = array_values(array_keys($invoice_id_list));

            $zohoItemsByInvoiceId = [];
            foreach ($invoice_id_list as $invoice_id) {
                $line_items = self::getLineItems($invoice_id);
                if ($line_items['error']) {
                    return response($line_items);
                }
                $zohoItemsByInvoiceId[$invoice_id] = $line_items['line_items'];
            }

            $paymentUrlBase64List = [];
            foreach ($dueInvoices as $dueInvoice) {
                $invoice_id  = $dueInvoice['invoice_id'];
                $zohoItems = $zohoItemsByInvoiceId[$invoice_id];
                $orderDetails = self::findOrGenProduct($zohoItems, $invoice_id);

                try {
                    $pulpalPayment = new PulPalPayment();
                    $paymentUrlBase64List[$invoice_id] = $pulpalPayment->generatePaymentUrlPaymentData($orderDetails);
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Error generating payment URL: " . $e->getMessage() . "\n",
                        'error' => config('app.debug') ? $e->getMessage() : null
                    ], 500);
                }
            }

            $request->merge([
                'pulpal_gate_url_list' => $paymentUrlBase64List,
            ]);
            return $next($request);
        } catch (\Exception $e) {
            Log::error('Payment initialization failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Payment initialization failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

class PulPalPayment
{
    private $config;

    public function __construct()
    {
        // Assuming you are in a framework like Laravel and config() helper is available
        // If not, you'll need to manually load your pulpal.php config file.
        $this->config = config('pulpal'); // Or include 'pulpal.php' and use the returned array
    }

    /**
     * Generates the payment URL using the DEPRECATED PaymentData (Base64) format.
     * WARNING: This format is deprecated and should be avoided for new integrations.
     * Use generatePaymentUrlQueryString instead if possible.
     *
     * @param array $paymentDetails An array containing payment details.
     * Required keys:
     * 'price' (int) - Amount in cents
     * 'externalId' (string) - Product/Order ID in your system
     * 'name' (array) - Product name in format ['az' => '...', 'ru' => '...', 'en' => '...']
     * 'description' (array) - Product description in format ['az' => '...', 'ru' => '...', 'en' => '...']
     * Optional keys:
     * 'expiresAt' (DateTimeInterface|string|int|null) - Expiry date for the unique code
     * 'customMerchantName' (string|null) - Custom merchant name to display
     * 'additionalPrices' (array|null) - Prices for taxit methods, e.g., ['bolkart6' => 100]
     * 'productUniqueCode' (string|null) - If set, other product details are ignored by PulPal.
     *
     * @return string The generated payment URL.
     * @throws \Exception If required configuration or payment details are missing.
     */
    public function generatePaymentUrlPaymentData(array $paymentDetails): string
    {
        // Validate configuration
        if (empty($this->config['merchant_id'])) {
            throw new \Exception("PulPal merchant_id is not configured.");
        }
        // Assuming api_private_key is the 'Salt' for signature2
        if (empty($this->config['api_private_key'])) {
            // signature2 is required if Salt is set in PulPal settings.
            // If Salt is NOT set in PulPal, then signature2 is optional according to doc.
            // We'll proceed, but warn if Salt is likely needed based on the doc.
            error_log("Warning: PulPal api_private_key (Salt) is not configured. signature2 might be missing.");
            $salt = ''; // Use empty string if salt is not configured
        } else {
            $salt = $this->config['api_private_key'];
        }

        // Determine the correct payment page host based on the API host config
        // Assuming 'payment-api-dev' or 'payment-api' corresponds to 'pay-dev' or 'pay'
        $paymentHost = 'https://pay.pulpal.az'; // Default to production
        if (strpos($this->config['host'], '-dev') !== false) {
            $paymentHost = 'https://pay-dev.pulpal.az';
        }

        // --- Build the Product Structure Array ---
        $productStructure = [
            'merchantId' => (int) $this->config['merchant_id'], // Ensure it's a number
        ];

        // Add details from input, prioritizing productUniqueCode
        if (!empty($paymentDetails['productUniqueCode'])) {
            $productStructure['productUniqueCode'] = (string) $paymentDetails['productUniqueCode'];
            // According to the doc, if productUniqueCode is set, other fields are ignored by PulPal.
            // So, we technically don't need to add other fields or calculate signature2 here
            // IF we rely on PulPal ignoring them. However, the signature2 formula *includes* these fields.
            // To be safe and follow the signature formula even if productUniqueCode is present,
            // we will add other fields if available and calculate the signature.
            // A real integration might skip signature2 if productUniqueCode is used,
            // but the document's sections seem slightly contradictory on this.
            // Let's proceed assuming signature2 is *always* calculated if Salt is present.
        } else {
            // Required fields if productUniqueCode is NOT used
            if (empty($paymentDetails['price']) || !is_int($paymentDetails['price']) || $paymentDetails['price'] <= 0) {
                throw new \Exception("Required payment detail 'price' (int > 0) is missing or invalid.");
            }
            if (empty($paymentDetails['externalId'])) {
                throw new \Exception("Required payment detail 'externalId' is missing.");
            }
            if (empty($paymentDetails['name']) || !is_array($paymentDetails['name'])) {
                throw new \Exception("Required payment detail 'name' (array {az, ru, en}) is missing or invalid.");
            }
            if (empty($paymentDetails['description']) || !is_array($paymentDetails['description'])) {
                throw new \Exception("Required payment detail 'description' (array {az, ru, en}) is missing or invalid.");
            }

            $productStructure['price'] = (int) $paymentDetails['price']; // Ensure it's a number
            $productStructure['externalId'] = (string) $paymentDetails['externalId']; // Ensure it's a string
            // Ensure name and description are objects with az, ru, en keys, even if empty
            $productStructure['name'] = [
                'az' => $paymentDetails['name']['az'] ?? '',
                'ru' => $paymentDetails['name']['ru'] ?? '',
                'en' => $paymentDetails['name']['en'] ?? ''
            ];
            $productStructure['description'] = [
                'az' => $paymentDetails['description']['az'] ?? '',
                'ru' => $paymentDetails['description']['ru'] ?? '',
                'en' => $paymentDetails['description']['en'] ?? ''
            ];

            // Optional fields
            if (isset($paymentDetails['expiresAt'])) {
                try {
                    // Try to convert various date formats to ISO 8601
                    if ($paymentDetails['expiresAt'] instanceof \DateTimeInterface) {
                        $expiresAt = $paymentDetails['expiresAt']->format('Y-m-d\TH:i:s\Z');
                    } elseif (is_numeric($paymentDetails['expiresAt'])) { // Assume unix timestamp
                        $dt = new \DateTimeImmutable();
                        $dt = $dt->setTimestamp($paymentDetails['expiresAt']);
                        $expiresAt = $dt->format('Y-m-d\TH:i:s\Z');
                    } else { // Assume string, try to parse
                        $dt = new \DateTimeImmutable($paymentDetails['expiresAt']);
                        $expiresAt = $dt->format('Y-m-d\TH:i:s\Z');
                    }
                    $productStructure['expiresAt'] = $expiresAt;
                } catch (\Exception $e) {
                    error_log("Warning: Could not parse 'expiresAt' date: " . $e->getMessage());
                    // Do not add expiresAt if parsing fails
                }
            }
            if (!empty($paymentDetails['customMerchantName'])) {
                $productStructure['customMerchantName'] = (string) $paymentDetails['customMerchantName'];
            }
            if (!empty($paymentDetails['additionalPrices']) && is_array($paymentDetails['additionalPrices'])) {
                $productStructure['additionalPrices'] = $paymentDetails['additionalPrices'];
            }
        }


        // --- Calculate signature2 ---
        // signature2 is required if Salt is set in PulPal settings.
        if (!empty($salt)) {
            // Calculate FromEpoch: integer part of (milliseconds since 1970 / 300000)
            $millisecondsSinceEpoch = floor(microtime(true) * 1000);
            $fromEpoch = floor($millisecondsSinceEpoch / 300000);

            // Calculate ExpiresAtEpoch: milliseconds since 1970 if expiresAt is set, otherwise empty string
            $expiresAtEpoch = "";
            if (isset($productStructure['expiresAt'])) {
                try {
                    $dt = new \DateTimeImmutable($productStructure['expiresAt']);
                    $expiresAtEpoch = floor($dt->getTimestamp() * 1000);
                } catch (\Exception $e) {
                    error_log("Warning: Could not calculate ExpiresAtEpoch: " . $e->getMessage());
                    $expiresAtEpoch = ""; // Fallback to empty string if date parsing failed earlier too
                }
            }


            // Concatenate components in the exact order specified in the document
            // Note: Ensure all parts are treated as strings for concatenation
            $signatureString =
                ($productStructure['name']['en'] ?? '') .
                ($productStructure['name']['az'] ?? '') .
                ($productStructure['name']['ru'] ?? '') .
                ($productStructure['description']['en'] ?? '') .
                ($productStructure['description']['ru'] ?? '') .
                ($productStructure['description']['az'] ?? '') .
                (string) ($productStructure['merchantId'] ?? '') . // Cast numbers to string
                (string) ($productStructure['externalId'] ?? '') . // Cast numbers to string
                (string) ($productStructure['price'] ?? '') . // Cast numbers to string
                (string) $expiresAtEpoch . // Already number or empty string
                (string) $fromEpoch . // Already number
                $salt; // The salt string

            $signature2 = sha1($signatureString);
            $productStructure['signature2'] = $signature2;
        }

        // --- Final Encoding and URL Construction ---
        $jsonString = json_encode($productStructure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonString === false) {
            throw new \Exception("Failed to encode payment details to JSON: " . json_last_error_msg());
        }

        $base64Encoded = base64_encode($jsonString);

        // Use rawurlencode which is closer to encodeURI than urlencode
        $paymentDataEncoded = rawurlencode($base64Encoded);

        // Build the final URL
        $paymentUrl = $paymentHost . '/payment?paymentdata=' . $paymentDataEncoded;

        // Add language parameter if configured
        if (!empty($this->config['lang'])) {
            $paymentUrl .= '&lang=' . rawurlencode($this->config['lang']);
        }

        return $paymentUrl;
    }

    /**
     * Generates the payment URL using the recommended Query String format.
     * This is the preferred method according to the documentation.
     *
     * @param array $paymentDetails An array containing payment details.
     * Required keys:
     * 'price' (int) - Amount in cents
     * 'externalId' (string) - Product/Order ID in your system
     * 'name' (array) - Product name in format ['az' => '...', 'ru' => '...', 'en' => '...']
     * 'description' (array) - Product description in format ['az' => '...', 'ru' => '...', 'en' => '...']
     * Optional keys:
     * 'expiresAt' (DateTimeInterface|string|int|null) - Expiry date for the unique code
     * 'customMerchantName' (string|null) - Custom merchant name to display
     * // Note: additionalPrices format is different for QueryString vs Base64
     * // For QueryString, it's individual price_PROVIDER params like 'price_bolkart6' => 100
     * 'additionalPricesQs' (array|null) - Prices for taxit methods in QueryString format e.g., ['price_bolkart6' => 100]
     * 'productUniqueCode' (string|null) - If set, values of other fields (except merchantId and lang) are ignored by PulPal.
     * 'repeatable' (bool|null) - Is it possible to pay for this ExternalId more than once
     *
     * @return string The generated payment URL.
     * @throws \Exception If required configuration or payment details are missing.
     */
    public function generatePaymentUrlQueryString(array $paymentDetails): string
    {
        // Validate configuration
        if (empty($this->config['merchant_id'])) {
            throw new \Exception("PulPal merchant_id is not configured.");
        }
        // Assuming api_private_key is the 'Salt' for signature2
        if (empty($this->config['api_private_key'])) {
            // signature2 is required if Salt is set in PulPal settings.
            // If Salt is NOT set in PulPal, then signature2 is optional according to doc.
            // We'll proceed, but warn if Salt is likely needed based on the doc.
            error_log("Warning: PulPal api_private_key (Salt) is not configured. signature2 might be missing for QueryString.");
            $salt = ''; // Use empty string if salt is not configured
        } else {
            $salt = $this->config['api_private_key'];
        }

        // Determine the correct payment page host based on the API host config
        $paymentHost = 'https://pay.pulpal.az'; // Default to production
        if (strpos($this->config['host'], '-dev') !== false) {
            $paymentHost = 'https://pay-dev.pulpal.az';
        }

        // --- Build Query Parameters Array ---
        $queryParams = [
            'merchantId' => (int) $this->config['merchant_id'], // Ensure it's a number
        ];

        // Add product details, prioritizing productUniqueCode
        if (!empty($paymentDetails['productUniqueCode'])) {
            $queryParams['productUniqueCode'] = (string) $paymentDetails['productUniqueCode'];
            // According to the doc, if productUniqueCode is set, other fields are ignored by PulPal.
            // So, we might not need to add other fields or calculate signature2 IF we rely on PulPal ignoring them.
            // However, the signature2 formula *includes* these fields.
            // To be safe and follow the signature formula even if productUniqueCode is present,
            // we will add other fields if available and calculate the signature.
            // A real integration might skip signature2 if productUniqueCode is used,
            // but the document's sections seem slightly contradictory on this.
            // Let's proceed assuming signature2 is *always* calculated if Salt is present.
        } else {
            // Required fields if productUniqueCode is NOT used
            if (empty($paymentDetails['price']) || !is_int($paymentDetails['price']) || $paymentDetails['price'] <= 0) {
                throw new \Exception("Required payment detail 'price' (int > 0) is missing or invalid.");
            }
            if (empty($paymentDetails['externalId'])) {
                throw new \Exception("Required payment detail 'externalId' is missing.");
            }
            if (empty($paymentDetails['name']) || !is_array($paymentDetails['name'])) {
                throw new \Exception("Required payment detail 'name' (array {az, ru, en}) is missing or invalid.");
            }
            if (empty($paymentDetails['description']) || !is_array($paymentDetails['description'])) {
                throw new \Exception("Required payment detail 'description' (array {az, ru, en}) is missing or invalid.");
            }

            $queryParams['price'] = (int) $paymentDetails['price']; // Ensure it's a number
            $queryParams['externalId'] = (string) $paymentDetails['externalId']; // Ensure it's a string
            // Add name and description languages individually
            $queryParams['name_az'] = $paymentDetails['name']['az'] ?? '';
            $queryParams['name_ru'] = $paymentDetails['name']['ru'] ?? '';
            $queryParams['name_en'] = $paymentDetails['name']['en'] ?? '';
            $queryParams['description_az'] = $paymentDetails['description']['az'] ?? '';
            $queryParams['description_ru'] = $paymentDetails['description']['ru'] ?? '';
            $queryParams['description_en'] = $paymentDetails['description']['en'] ?? '';


            // Optional fields
            if (isset($paymentDetails['expiresAt'])) {
                try {
                    // Try to convert various date formats to ISO 8601
                    if ($paymentDetails['expiresAt'] instanceof \DateTimeInterface) {
                        $expiresAt = $paymentDetails['expiresAt']->format('Y-m-d\TH:i:s\Z');
                    } elseif (is_numeric($paymentDetails['expiresAt'])) { // Assume unix timestamp
                        $dt = new \DateTimeImmutable();
                        $dt = $dt->setTimestamp($paymentDetails['expiresAt']);
                        $expiresAt = $dt->format('Y-m-d\TH:i:s\Z');
                    } else { // Assume string, try to parse
                        $dt = new \DateTimeImmutable($paymentDetails['expiresAt']);
                        $expiresAt = $dt->format('Y-m-d\TH:i:s\Z');
                    }
                    $queryParams['expiresAt'] = $expiresAt;
                } catch (\Exception $e) {
                    error_log("Warning: Could not parse 'expiresAt' date: " . $e->getMessage());
                    // Do not add expiresAt if parsing fails
                }
            }
            if (!empty($paymentDetails['customMerchantName'])) {
                $queryParams['customMerchantName'] = (string) $paymentDetails['customMerchantName'];
            }
            // Add additional prices for QueryString format (price_PROVIDER=value)
            if (!empty($paymentDetails['additionalPricesQs']) && is_array($paymentDetails['additionalPricesQs'])) {
                foreach ($paymentDetails['additionalPricesQs'] as $key => $value) {
                    if (strpos($key, 'price_') === 0 && is_numeric($value)) {
                        $queryParams[$key] = (int) $value; // Ensure it's an integer price
                    } else {
                        error_log("Warning: Invalid key-value pair in additionalPricesQs: $key => $value");
                    }
                }
            }
            if (isset($paymentDetails['repeatable'])) {
                $queryParams['repeatable'] = (bool) $paymentDetails['repeatable'];
            }
        }

        // --- Calculate signature2 for Query String ---
        // signature2 is required if Salt is set in PulPal settings.
        if (!empty($salt)) {
            // Calculate FromEpoch: integer part of (milliseconds since 1970 / 300000)
            $millisecondsSinceEpoch = floor(microtime(true) * 1000);
            $fromEpoch = floor($millisecondsSinceEpoch / 300000);

            // Calculate ExpiresAtEpoch: milliseconds since 1970 if expiresAt is set, otherwise empty string
            $expiresAtEpoch = "";
            if (isset($queryParams['expiresAt'])) {
                try {
                    $dt = new \DateTimeImmutable($queryParams['expiresAt']);
                    $expiresAtEpoch = floor($dt->getTimestamp() * 1000);
                } catch (\Exception $e) {
                    error_log("Warning: Could not calculate ExpiresAtEpoch for QueryString signature: " . $e->getMessage());
                    $expiresAtEpoch = ""; // Fallback to empty string if date parsing failed
                }
            }

            // Concatenate components for QueryString signature2
            // The documentation formula for QueryString signature2 seems to be the *same* as Base64 signature2.
            // Let's follow that exact formula and field order.
            $signatureString =
                ($queryParams['name_en'] ?? '') .
                ($queryParams['name_az'] ?? '') .
                ($queryParams['name_ru'] ?? '') .
                ($queryParams['description_en'] ?? '') .
                ($queryParams['description_ru'] ?? '') .
                ($queryParams['description_az'] ?? '') .
                (string) ($queryParams['merchantId'] ?? '') . // Cast numbers to string
                (string) ($queryParams['externalId'] ?? '') . // Cast numbers to string
                (string) ($queryParams['price'] ?? '') . // Cast numbers to string
                (string) $expiresAtEpoch . // Already number or empty string
                (string) $fromEpoch . // Already number
                $salt; // The salt string

            $signature2 = sha1($signatureString);
            $queryParams['signature2'] = $signature2;
        }


        // --- Build the final URL ---
        // Add language parameter first if configured
        if (!empty($this->config['lang'])) {
            $queryParams['lang'] = rawurlencode($this->config['lang']);
        }

        // Build the query string from the parameters
        $queryString = http_build_query($queryParams);

        $paymentUrl = $paymentHost . '/payment?' . $queryString;

        return $paymentUrl;
    }
}

// --- Example Usage (assuming you have the PulPalPayment class defined) ---

/*
// If NOT in a framework, load config manually:
$config = include 'pulpal.php';
// Then instantiate the class
*/
// If in Laravel/framework with config() helper:

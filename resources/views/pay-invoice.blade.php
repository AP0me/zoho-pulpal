<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pay Invoice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .payment-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .status-message {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 4px;
            display: none;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container payment-container">
        <h2 class="mb-4">Pay Invoice</h2>
        
        <form id="paymentForm">
            <div class="mb-3">
                <label for="invoiceId" class="form-label">Invoice ID</label>
                <input type="text" class="form-control" id="invoiceId" value="{{ $invoiceId ?? '' }}" required>
            </div>
            
            <div class="mb-3">
                <label for="customerId" class="form-label">Customer ID</label>
                <input type="text" class="form-control" id="customerId" value="{{ $customerId ?? '' }}" required>
            </div>
            
            <div class="mb-3">
                <label for="amount" class="form-label">Amount</label>
                <input type="number" step="0.01" class="form-control" id="amount" required>
            </div>
            
            <div class="mb-3">
                <label for="paymentMode" class="form-label">Payment Method</label>
                <select class="form-select" id="paymentMode" required>
                    <option value="">Select payment method</option>
                    <option value="Cash">Cash</option>
                    <option value="Check">Check</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="PayPal">PayPal</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="paymentDate" class="form-label">Payment Date</label>
                <input type="date" class="form-control" id="paymentDate" required>
            </div>
            
            <div class="mb-3">
                <label for="accountId" class="form-label">Account ID</label>
                <input type="text" class="form-control" id="accountId" required>
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Notes</label>
                <textarea class="form-control" id="notes" rows="2"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary" id="payButton">
                <span id="buttonText">Process Payment</span>
                <span id="spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
            </button>
        </form>
        
        <div id="statusMessage" class="status-message"></div>
    </div>

    <script>
        document.getElementById('paymentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Get form values
            const paymentData = {
                invoice_id: document.getElementById('invoiceId').value,
                customer_id: document.getElementById('customerId').value,
                amount: parseFloat(document.getElementById('amount').value),
                payment_mode: document.getElementById('paymentMode').value,
                date: document.getElementById('paymentDate').value,
                account_id: document.getElementById('accountId').value,
                notes: document.getElementById('notes').value
            };
            
            // Validate
            if (!paymentData.invoice_id || !paymentData.customer_id || !paymentData.amount || 
                !paymentData.payment_mode || !paymentData.date || !paymentData.account_id) {
                showStatus('Please fill all required fields', 'error');
                return;
            }
            
            // Show loading state
            const button = document.getElementById('payButton');
            const buttonText = document.getElementById('buttonText');
            const spinner = document.getElementById('spinner');
            
            button.disabled = true;
            buttonText.textContent = 'Processing...';
            spinner.classList.remove('d-none');
            
            try {
                // Make API call
                const response = await fetch({{ route('pulpal.payment-notification') }}, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(paymentData)
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    showStatus('Payment processed successfully!', 'success');
                    // You could redirect or update UI here
                    console.log('Payment details:', data.payment);
                } else {
                    const errorMsg = data.error || data.message || 'Payment failed';
                    showStatus(errorMsg, 'error');
                    console.error('Payment error:', data);
                }
            } catch (error) {
                showStatus('Network error. Please try again.', 'error');
                console.error('Fetch error:', error);
            } finally {
                // Reset button state
                button.disabled = false;
                buttonText.textContent = 'Process Payment';
                spinner.classList.add('d-none');
            }
        });
        
        function showStatus(message, type) {
            const statusElement = document.getElementById('statusMessage');
            statusElement.textContent = message;
            statusElement.className = `status-message ${type}`;
            statusElement.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                statusElement.style.display = 'none';
            }, 5000);
        }
        
        // Set default date to today
        document.getElementById('paymentDate').valueAsDate = new Date();
    </script>
</body>
</html>

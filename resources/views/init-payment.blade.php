<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="zoho-invoice-form">
    <form class="zoho-form" onsubmit="event.preventDefault(); newInvoice()">
        <div class="form-group">
            <label for="customer_id">Customer</label>
            <select class="form-control customer_id" name="customer_id" required onselect="updateIfEmail()">
                @for($i = 0; $i < count($customers); $i++)
                    @php
                        $customer = $customers[$i];
                    @endphp
                    <option email="{{ $customer['email'] }}" value="{{ $customer['contact_id'] }}">{{ $customer['contact_name'] }}</option>
                @endfor
            </select>
        </div>

        <div class="form-group">
            <label for="customer_email">Customer Email</label>
            <input type="text" class="form-control customer_email" name="customer_email" required>
        </div>
        
        <div class="form-group">
            <label for="item_id">Item ID</label>
            <select class="form-control item_id" name="item_id" required>
                @for($i = 0; $i < count($items); $i++)
                    @php
                        $item = $items[$i];
                    @endphp
                    <option value="{{ $item['item_id'] }}">{{ $item['item_name'] }}</option>
                @endfor
            </select>
        </div>
        
        <div class="form-group">
            <label for="quantity">Quantity</label>
            <input class="form-control quantity" type="number" step="0.01" name="quantity" required>
        </div>
        
        <div class="form-group">
            <label for="rate">Rate</label>
            <input class="form-control rate" type="number" step="0.01" name="rate" required>
        </div>
        
        <button type="submit" class="btn btn-primary" id="submitBtn">
            <span id="btnText">Create Invoice</span>
            <span id="spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
        </button>
        
        <div id="responseMessage" class="mt-3 alert d-none"></div>
    </form>
</div>

<script>
    async function newInvoice() {
        // Get form elements
        const form = document.querySelector('.zoho-form');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const spinner = document.getElementById('spinner');
        const responseMsg = document.getElementById('responseMessage');
        
        // Show loading state
        submitBtn.disabled = true;
        btnText.textContent = 'Processing...';
        spinner.classList.remove('d-none');
        responseMsg.classList.add('d-none');
        
        try {
            // Prepare data
            const formData = {
                customer_id: form.querySelector('.customer_id').value,
                line_item: {
                    item_id: form.querySelector('.item_id').value,
                    quantity: parseFloat(form.querySelector('.quantity').value),
                    rate: parseFloat(form.querySelector('.rate').value)
                }
            };
            
            // Make API call
            const response = await fetch("{{ route('pulpal.initiate') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.text();
            
            // Handle response
            if (response.ok) {
                showResponse('Invoice created successfully!', 'success');
                console.log('Invoice details:', data);
                // Optional: Reset form or redirect
                // form.reset();
            } else {
                const errorMsg = data.error || data.message || 'Failed to create invoice';
                showResponse(errorMsg, 'danger');
                console.error('Error:', data);
            }
        } catch (error) {
            showResponse('Network error. Please try again.', 'danger');
            console.error('Fetch error:', error);
        } finally {
            // Reset button state
            submitBtn.disabled = false;
            btnText.textContent = 'Create Invoice';
            spinner.classList.add('d-none');
        }
    }
    
    function showResponse(message, type) {
        const responseMsg = document.getElementById('responseMessage');
        responseMsg.textContent = message;
        responseMsg.className = `mt-3 alert alert-${type}`;
        responseMsg.classList.remove('d-none');
    }

    function updateIfEmail(){
        const customerEmail = document.querySelector('.customer_id>option:checked').getAttribute('email');
        document.querySelector('.customer_email').value = customerEmail;
    }
    updateIfEmail();
</script>

<style>
    .zoho-invoice-form {
        max-width: 500px;
        margin: 20px auto;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 5px;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-control {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .d-none {
        display: none;
    }
</style>

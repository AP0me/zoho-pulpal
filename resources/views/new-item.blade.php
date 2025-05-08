<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create New Item</title>
    <style>
        body {
            font-family: sans-serif;
        }

        .zoho-item-form-container {
            /* Renamed container class for clarity */
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            /* Slightly larger padding */
            border: 1px solid #ccc;
            /* Slightly darker border */
            border-radius: 4px;
            box-sizing: border-box;
            /* Include padding and border in element's total width/height */
        }

        textarea.form-control {
            /* Style textarea specifically */
            min-height: 80px;
            resize: vertical;
            /* Allow vertical resizing */
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            color: #212529;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            user-select: none;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .btn-primary {
            color: #fff;
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            color: #fff;
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .spinner-border {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            vertical-align: text-bottom;
            border: 0.2em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border .75s linear infinite;
        }

        .spinner-border-sm {
            width: 1em;
            /* Adjusted size */
            height: 1em;
            /* Adjusted size */
            border-width: .2em;
            /* Adjusted border */
            margin-left: 5px;
            /* Add space between text and spinner */
        }

        @keyframes spinner-border {
            to {
                transform: rotate(360deg);
            }
        }

        .mt-3 {
            margin-top: 1rem !important;
        }

        .alert {
            position: relative;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .d-none {
            display: none !important;
            /* Use !important to override potential conflicts */
        }
    </style>
</head>

<body>

    <div class="zoho-item-form-container">
        <h2>Create New Item</h2>
        <form class="zoho-item-form" onsubmit="event.preventDefault(); newItem()">
            <div class="form-group">
                <label for="item_name">Item Name</label>
                <input class="form-control item_name" id="item_name" type="text" name="name" required>
            </div>

            <div class="form-group">
                <label for="item_rate">Rate (Selling Price)</label>
                <input class="form-control item_rate" id="item_rate" type="number" step="0.01" name="rate"
                    required>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn">
                <span id="btnText">Create Item</span> <span id="spinner"
                    class="spinner-border spinner-border-sm d-none" role="status"></span>
            </button>

            <div id="responseMessage" class="mt-3 alert d-none"></div>
        </form>
    </div>

    <script>
        async function newItem() { // Renamed function
            // Get form elements
            const form = document.querySelector('.zoho-item-form'); // Updated selector
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const spinner = document.getElementById('spinner');
            const responseMsg = document.getElementById('responseMessage');

            // Show loading state
            submitBtn.disabled = true;
            btnText.textContent = 'Processing...';
            spinner.classList.remove('d-none');
            responseMsg.classList.add('d-none'); // Hide previous messages
            responseMsg.textContent = ''; // Clear previous message text
            responseMsg.className = 'mt-3 alert d-none'; // Reset classes

            try {
                // Prepare data for a new item
                const formData = {
                    name: form.querySelector('.item_name').value,
                    rate: parseFloat(form.querySelector('.item_rate').value)
                    // Add other fields if included in the form, e.g.:
                    // sku: form.querySelector('.item_sku').value
                };

                // Validate Rate
                if (isNaN(formData.rate)) {
                    throw new Error("Rate must be a valid number.");
                }

                // Make API call to the new item endpoint
                // *** IMPORTANT: Replace 'zoho-new-item' with your actual route name ***
                const response = await fetch("{{ route('zoho-new-item') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        // Include CSRF token if your backend requires it
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                // Handle response
                if (response.ok) {
                    showResponse('Item created successfully!', 'success');
                    console.log('Item details:', data.item || data); // Adjust based on your API response structure
                    form.reset(); // Optionally reset the form on success
                } else {
                    const errorMsg = data.error || data.message ||
                    `Failed to create item (Status: ${response.status})`; // Improved error message
                    showResponse(errorMsg, 'danger');
                    console.error('Error creating item:', data);
                }
            } catch (error) {
                // Handle both validation errors and fetch errors
                showResponse(`An error occurred: ${error.message || 'Network error. Please try again.'}`, 'danger');
                console.error('Operation error:', error);
            } finally {
                // Reset button state regardless of success or failure
                submitBtn.disabled = false;
                btnText.textContent = 'Create Item'; // Reset to original text
                spinner.classList.add('d-none');
            }
        }

        function showResponse(message, type) {
            const responseMsg = document.getElementById('responseMessage');
            responseMsg.textContent = message;
            // Ensure base classes are present and add the type-specific class
            responseMsg.className = `mt-3 alert alert-${type}`;
            // No need to call remove('d-none') here as className assignment overwrites it
        }
    </script>

</body>

</html>

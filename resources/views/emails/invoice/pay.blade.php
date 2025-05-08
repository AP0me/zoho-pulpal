<!DOCTYPE html>
<html>
<head>
    <title>You have an invoice.</title>
</head>
<body>
    <h1>You can pay your invoice</h1>
    <p>Dear Customer,</p>
    <p>This is your invoice for service {{ $request->new_invoice['line_items'][0]['name'] }}. Amount {{ $request->new_invoice['total']/100 }} AZN is to be paid</p>
    <a href="{{ $request->pulpal_gate_url }}">
        <button>Pay</button>
    </a>
    <p>Thank you for your purchase!</p>
</body>
</html>
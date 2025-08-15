<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## About the project
This Laravel project aims to be a bridge between pulpal payment gateway and ZohoBooks CRM. When new invoices are created in ZohoBooks, they are extracted for data like user's email, amount due, type of service. Using this information, payment url is generated for each invoice. Information about the invoice is then sent to the user's email with the payment link. When payment is finished, ZohoBooks invoice is marked as paid.


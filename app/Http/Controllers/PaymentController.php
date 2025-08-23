<?php

// app/Http/Controllers/PaymentController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shoot; // Make sure you have a Shoot model
use Square\SquareClient;
use Square\Models\CreateCheckoutRequest;
use Square\Models\CreateOrderRequest;
use Square\Models\Order;
use Square\Models\OrderLineItem;
use Square\Models\Money;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function createCheckoutLink(Request $request, Shoot $shoot)
    {
        // Initialize Square Client
        $client = new SquareClient([
            'accessToken' => config('services.square.access_token')
        ]);

        // Calculate amount to be paid in cents
        $amountToPay = ($shoot->total_quote - $shoot->total_paid) * 100;
        
        // Prevent creating payment links for fully paid shoots
        if ($amountToPay <= 0) {
            return response()->json(['error' => 'This shoot is already fully paid.'], 400);
        }

        // Create an Order for the payment
        $money = new Money();
        $money->setAmount($amountToPay);
        $money->setCurrency('USD'); // Or your desired currency

        $lineItem = new OrderLineItem('1'); // Quantity of 1
        $lineItem->setName('Payment for Shoot at ' . $shoot->address);
        $lineItem->setBasePriceMoney($money);

        $order = new Order(config('services.square.location_id'));
        $order->setLineItems([$lineItem]);
        // Add a reference ID to link back to your shoot
        $order->setReferenceId((string)$shoot->id); 

        $createOrderRequest = new CreateOrderRequest();
        $createOrderRequest->setOrder($order);
        // Use a unique key to prevent duplicate orders on retries
        $createOrderRequest->setIdempotencyKey(Str::uuid()->toString());

        $orderResponse = $client->getOrdersApi()->createOrder($createOrderRequest);
        $createdOrder = $orderResponse->getResult()->getOrder();

        // Create the Checkout Request
        $checkoutRequest = new CreateCheckoutRequest(
            Str::uuid()->toString(), // Unique idempotency key
            $createdOrder // The order created above
        );

        // Define redirect URL after payment
        $checkoutRequest->setRedirectUrl('http://your-frontend-app.com/shoot-payment-success');

        $checkoutResponse = $client->getCheckoutApi()->createCheckout(
            config('services.square.location_id'),
            $checkoutRequest
        );
        
        $checkout = $checkoutResponse->getResult()->getCheckout();

        // Return the checkout URL to the frontend
        return response()->json([
            'checkoutUrl' => $checkout->getCheckoutPageUrl()
        ]);
    }
}

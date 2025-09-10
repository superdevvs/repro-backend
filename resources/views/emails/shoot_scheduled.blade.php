<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Shoot Scheduled</title>
</head>
<body>
    <p>Subject: New Shoot Scheduled for {{ $shoot->location }}</p>

    <p>Good Morning, {{ $user->firstname }} !</p>

    <p>A new photo shoot has been scheduled under your account!</p>

    <p>You can find the shoot listed under Scheduled Shoots after logging into  
        <a href="https://pro.reprophotos.com">https://pro.reprophotos.com</a>
    </p>

    <p><strong>Here is a summary of the shoot that was scheduled:</strong></p>

    <p>
        Location: {{ $shoot->location }} <br>
        Scheduled Shoot Date: {{ $shoot->date }} <br>
        Photographer: {{ $shoot->photographer }}
    </p>

    <p>
        {{-- List of packages dynamically --}}
        @foreach($shoot->packages as $package)
            * {{ $package['name'] }}, [${{ number_format($package['price'], 2) }}] <br>
        @endforeach
    </p>

    <p>
        Shoot total: ${{ number_format($shoot->total, 2) }} <br>
        Tax: ${{ number_format($shoot->tax, 2) }} ({{ $shoot->tax_rate }}%) <br>
        Total Payment: ${{ number_format($shoot->grand_total, 2) }} <br>
        Total Due: ${{ number_format($shoot->grand_total, 2) }}
    </p>

    <p>
        <strong>Shoot Notes:</strong><br>
        {{ $shoot->notes ?? 'N/A' }}
    </p>

    <p>
        To ensure a smooth shoot process, please have the property ready.  
        Here is a link to getting your property ready for the shoot:  
        <a href="https://reprophotos.com/tips-to-get-your-property-camera-ready/">
            Tips to Get Your Property Camera Ready
        </a>
    </p>

    <p>
        For your convenience, you can pay without logging in by clicking the following link:  
        <a href="{{ $paymentLink }}">{{ $paymentLink }}</a>
    </p>

    <p>
        Payment may be made at any time throughout the shoot process. Although the image proofs will be posted to your account prior to payment being made, your final images will not be accessible until payment has been received in full.
    </p>

    <p>
        If you have any questions about this photo shoot please feel free to contact us, or email  
        <a href="mailto:contact@reprophotos.com">contact@reprophotos.com</a> directly.
    </p>

    <p>
        <strong>Our Cancellation Policy:</strong> If an appointment is cancelled on-site, a cancellation fee of $60 will be charged. This helps us cover time, travel and administration costs. We ask that you please reschedule or cancel at least 6 hours before the beginning of your appointment.
    </p>

    <p>Thanks for scheduling, we appreciate your business!</p>

    <p>
        Customer Service Team <br>
        R/E Pro Photos <br>
        202-868-1663 <br>
        <a href="mailto:contact@reprophotos.com">contact@reprophotos.com</a> <br>
        <a href="https://reprophotos.com">https://reprophotos.com</a> <br>
        Pro Dashboard: <a href="https://pro.reprophotos.com">https://pro.reprophotos.com</a>
    </p>

    <p>
        We would love your feedback:  
        <a href="https://www.google.com/maps/place/R%2FE+Pro+Photos/reviews" target="_blank">
            Post a review on Google
        </a>.
    </p>
</body>
</html>

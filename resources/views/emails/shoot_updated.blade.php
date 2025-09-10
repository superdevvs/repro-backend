<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Scheduled Photo Shoot Updated</title>
</head>
<body>
    <p>Subject: Scheduled Photo Shoot for {{ $shoot->location }} Updated</p>

    <p>Good Morning, {{ $user->firstname }} !</p>

    <p>
        One of your scheduled photo shoots has been updated. 
        Here is a summary of the latest information regarding the shoot that was updated:
    </p>

    <p>
        Location: {{ $shoot->location }} <br>
        Scheduled Shoot Date: {{ $shoot->date }} <br>
        Photographer: {{ $shoot->photographer }}
    </p>

    <p>
        {{-- List updated packages --}}
        @foreach($shoot->packages as $package)
            * {{ $package['name'] }}, [${{ number_format($package['price'], 2) }}] <br>
        @endforeach
    </p>

    <p>
        <strong>Shoot Notes:</strong><br>
        {{ $shoot->notes ?? 'N/A' }}
    </p>

    <p>
        Visit <a href="https://pro.reprophotos.com">https://pro.reprophotos.com</a> to manage your shoots.
    </p>

    <p>
        To ensure a smooth shoot process, please have the property ready.  
        Here is a link to getting your property ready for the shoot:  
        <a href="https://reprophotos.com/tips-to-get-your-property-camera-ready/">
            Tips to Get Your Property Camera Ready
        </a>
    </p>

    <p>
        <strong>Our Cancellation Policy:</strong> If an appointment is cancelled on-site, 
        a cancellation fee of $60 will be charged. This helps us cover time, travel and administration costs.  
        We ask that you please reschedule or cancel at least 6 hours before the beginning of your appointment.
    </p>

    <p>
        If you have any questions about this photo shoot please feel free to reply to this email, 
        or email <a href="mailto:contact@reprophotos.com">contact@reprophotos.com</a> directly.
    </p>

    <p>Thank you!</p>

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

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your Photos Are Ready!</title>
</head>
<body>
    <p>Subject: Your Photo Shoot Images Are Ready for Download!</p>

    <p>Good Morning, {{ $user->firstname }} {{ $user->lastname }} !</p>

    <p>
        Your real estate photos have been completed and are now available for download.  
        You can log into your account to access them anytime at:  
        <a href="https://pro.reprophotos.com">https://pro.reprophotos.com</a>
    </p>

    <p>
        <strong>Shoot Summary:</strong><br>
        Location: {{ $shoot->location }} <br>
        Shoot Date: {{ $shoot->date }} <br>
        Photographer: {{ $shoot->photographer }}
    </p>

    <p>
        {{-- List delivered packages --}}
        @foreach($shoot->packages as $package)
            * {{ $package['name'] }}, [${{ number_format($package['price'], 2) }}] <br>
        @endforeach
    </p>

    <p>
        <strong>Shoot Notes:</strong><br>
        {{ $shoot->notes ?? 'N/A' }}
    </p>

    <p>
        Please log in to your dashboard to preview, download, and manage your final images.  
        Pro Dashboard: <a href="https://pro.reprophotos.com">https://pro.reprophotos.com</a>
    </p>

    <p>
        If you have any questions about this shoot or need further assistance,  
        please reply to this email or contact us at  
        <a href="mailto:contact@reprophotos.com">contact@reprophotos.com</a>.
    </p>

    <p>Thank you for choosing R/E Pro Photos!</p>

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

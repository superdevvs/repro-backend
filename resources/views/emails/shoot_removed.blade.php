<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Photo Shoot Removed from Schedule</title>
</head>
<body>
    <p>Subject: Photo Shoot Removed from Schedule</p>

    <p>Good Morning, {{ $user->firstname }}!</p>

    <p>
        One of your Real Estate photo shoots has been removed from the schedule 
        due to a cancellation or a re-schedule.
    </p>

    <p>
        Location: {{ $shoot->location }} <br>
        Date: {{ $shoot->date }} <br>
        Photographer: {{ $shoot->photographer }}
    </p>

    <p>
        {{-- List removed packages --}}
        @foreach($shoot->packages as $package)
            * {{ $package['name'] }}, [${{ number_format($package['price'], 2) }}] <br>
        @endforeach
    </p>

    <p>
        <strong>Shoot Notes:</strong><br>
        {{ $shoot->notes ?? 'N/A' }}
    </p>

    <p>
        If you need real estate photography services for this property in the future, 
        please feel free to reply to this email, or email  
        <a href="mailto:contact@reprophotos.com">contact@reprophotos.com</a> directly.
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

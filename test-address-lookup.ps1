# Address Lookup Testing Script
# This script tests the address lookup functionality

Write-Host "=== Address Lookup Testing ===" -ForegroundColor Cyan
Write-Host ""

# Check if server is running
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8000/api/ping" -Method GET -TimeoutSec 5
    Write-Host "✓ Laravel server is running" -ForegroundColor Green
} catch {
    Write-Host "✗ Laravel server is not running" -ForegroundColor Red
    Write-Host "  Please start the server with: php artisan serve" -ForegroundColor Yellow
    exit 1
}

# Check Google Places API configuration
Write-Host "Checking Google Places API configuration..." -ForegroundColor Yellow

$envContent = Get-Content ".env" -Raw
if ($envContent -match "GOOGLE_PLACES_API_KEY=(.+)") {
    $apiKey = $matches[1].Trim()
    if ($apiKey -eq "your_google_places_api_key_here" -or $apiKey -eq "") {
        Write-Host "⚠ Google Places API key not configured" -ForegroundColor Yellow
        Write-Host "  Please update GOOGLE_PLACES_API_KEY in .env file" -ForegroundColor Gray
        Write-Host "  See GOOGLE_PLACES_SETUP.md for instructions" -ForegroundColor Gray
        $skipApiTests = $true
    } else {
        Write-Host "✓ Google Places API key found" -ForegroundColor Green
        $skipApiTests = $false
    }
} else {
    Write-Host "✗ GOOGLE_PLACES_API_KEY not found in .env" -ForegroundColor Red
    $skipApiTests = $true
}

Write-Host ""

if (-not $skipApiTests) {
    Write-Host "=== Testing Address Search ===" -ForegroundColor Cyan
    
    # Test address search
    Write-Host "Testing address search..." -NoNewline
    try {
        $searchResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/address/search?query=123%20Main%20Street" -Method GET
        
        if ($searchResponse.success) {
            Write-Host " ✓ SUCCESS" -ForegroundColor Green
            Write-Host "  Found $($searchResponse.count) suggestions" -ForegroundColor Gray
            
            if ($searchResponse.count -gt 0) {
                Write-Host "  Sample suggestion: $($searchResponse.data[0].description)" -ForegroundColor Gray
                $samplePlaceId = $searchResponse.data[0].place_id
            }
        } else {
            Write-Host " ✗ FAILED" -ForegroundColor Red
            Write-Host "  Error: $($searchResponse.error)" -ForegroundColor Gray
        }
    } catch {
        Write-Host " ✗ ERROR" -ForegroundColor Red
        Write-Host "  Exception: $($_.Exception.Message)" -ForegroundColor Gray
    }

    Write-Host ""

    # Test address details (if we have a place_id)
    if ($samplePlaceId) {
        Write-Host "=== Testing Address Details ===" -ForegroundColor Cyan
        Write-Host "Testing address details..." -NoNewline
        
        try {
            $detailsResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/address/details?place_id=$samplePlaceId" -Method GET
            
            if ($detailsResponse.success) {
                Write-Host " ✓ SUCCESS" -ForegroundColor Green
                $details = $detailsResponse.data
                Write-Host "  Address: $($details.address)" -ForegroundColor Gray
                Write-Host "  City: $($details.city)" -ForegroundColor Gray
                Write-Host "  State: $($details.state)" -ForegroundColor Gray
                Write-Host "  ZIP: $($details.zip)" -ForegroundColor Gray
                if ($details.latitude -and $details.longitude) {
                    Write-Host "  Coordinates: $($details.latitude), $($details.longitude)" -ForegroundColor Gray
                }
            } else {
                Write-Host " ✗ FAILED" -ForegroundColor Red
                Write-Host "  Error: $($detailsResponse.error)" -ForegroundColor Gray
            }
        } catch {
            Write-Host " ✗ ERROR" -ForegroundColor Red
            Write-Host "  Exception: $($_.Exception.Message)" -ForegroundColor Gray
        }
        
        Write-Host ""
    }
}

Write-Host "=== Testing Address Validation ===" -ForegroundColor Cyan

# Test address validation
Write-Host "Testing address validation..." -NoNewline

$testAddress = @{
    address = "123 Main Street"
    city = "Anytown"
    state = "CA"
    zip = "12345"
} | ConvertTo-Json

try {
    $validationResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/address/validate" -Method POST -Body $testAddress -ContentType "application/json"
    
    if ($validationResponse.success) {
        Write-Host " ✓ SUCCESS" -ForegroundColor Green
        $validation = $validationResponse.data
        Write-Host "  Valid: $($validation.is_valid)" -ForegroundColor Gray
        if ($validation.errors.Count -gt 0) {
            Write-Host "  Errors: $($validation.errors -join ', ')" -ForegroundColor Yellow
        }
    } else {
        Write-Host " ✗ FAILED" -ForegroundColor Red
        Write-Host "  Error: $($validationResponse.error)" -ForegroundColor Gray
    }
} catch {
    Write-Host " ✗ ERROR" -ForegroundColor Red
    Write-Host "  Exception: $($_.Exception.Message)" -ForegroundColor Gray
}

Write-Host ""

Write-Host "=== Testing Service Area Check ===" -ForegroundColor Cyan

# Test service area check
Write-Host "Testing service area check..." -NoNewline

try {
    $serviceAreaResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/address/service-area?address=123%20Main%20St`&city=Washington`&state=DC`&zip=20001" -Method GET
    
    if ($serviceAreaResponse.success) {
        Write-Host " ✓ SUCCESS" -ForegroundColor Green
        $serviceArea = $serviceAreaResponse.data
        Write-Host "  In service area: $($serviceArea.in_service_area)" -ForegroundColor Gray
        Write-Host "  State: $($serviceArea.state)" -ForegroundColor Gray
        Write-Host "  Message: $($serviceArea.message)" -ForegroundColor Gray
    } else {
        Write-Host " ✗ FAILED" -ForegroundColor Red
        Write-Host "  Error: $($serviceAreaResponse.error)" -ForegroundColor Gray
    }
} catch {
    Write-Host " ✗ ERROR" -ForegroundColor Red
    Write-Host "  Exception: $($_.Exception.Message)" -ForegroundColor Gray
}

Write-Host ""

if (-not $skipApiTests) {
    Write-Host "=== Testing Distance Calculation ===" -ForegroundColor Cyan
    
    # Test distance calculation
    Write-Host "Testing distance calculation..." -NoNewline
    
    $distanceData = @{
        origin = @{
            address = "1600 Pennsylvania Avenue"
            city = "Washington"
            state = "DC"
            zip = "20500"
        }
        destination = @{
            address = "1 Times Square"
            city = "New York"
            state = "NY"
            zip = "10036"
        }
    } | ConvertTo-Json -Depth 3
    
    try {
        $distanceResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/address/distance" -Method POST -Body $distanceData -ContentType "application/json"
        
        if ($distanceResponse.success) {
            Write-Host " ✓ SUCCESS" -ForegroundColor Green
            $distance = $distanceResponse.data
            Write-Host "  Distance: $($distance.distance)" -ForegroundColor Gray
            Write-Host "  Duration: $($distance.duration)" -ForegroundColor Gray
        } else {
            Write-Host " ✗ FAILED" -ForegroundColor Red
            Write-Host "  Error: $($distanceResponse.error)" -ForegroundColor Gray
        }
    } catch {
        Write-Host " ✗ ERROR" -ForegroundColor Red
        Write-Host "  Exception: $($_.Exception.Message)" -ForegroundColor Gray
    }
    
    Write-Host ""
}

Write-Host "=== Summary ===" -ForegroundColor Cyan

if ($skipApiTests) {
    Write-Host "⚠ Google Places API tests skipped - API key not configured" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "To enable full testing:" -ForegroundColor White
    Write-Host "1. Get a Google Places API key (see GOOGLE_PLACES_SETUP.md)" -ForegroundColor White
    Write-Host "2. Update GOOGLE_PLACES_API_KEY in .env file" -ForegroundColor White
    Write-Host "3. Run this test script again" -ForegroundColor White
} else {
    Write-Host "✓ Address lookup functionality tested" -ForegroundColor Green
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor White
    Write-Host "1. Test the frontend component at /address-lookup-demo" -ForegroundColor White
    Write-Host "2. Integrate AddressLookup component into your Book Shoot form" -ForegroundColor White
    Write-Host "3. Customize service areas in AddressLookupController" -ForegroundColor White
}

Write-Host ""
Write-Host "=== Available Endpoints ===" -ForegroundColor Cyan
Write-Host "GET  /api/address/search?query=ADDRESS" -ForegroundColor White
Write-Host "GET  /api/address/details?place_id=PLACE_ID" -ForegroundColor White
Write-Host "POST /api/address/validate" -ForegroundColor White
Write-Host "POST /api/address/distance" -ForegroundColor White
Write-Host "GET  /api/address/service-area?address=...&city=...&state=...&zip=..." -ForegroundColor White
Write-Host "GET  /api/address/nearby-photographers" -ForegroundColor White

Write-Host ""
Write-Host "Address lookup testing completed!" -ForegroundColor Green
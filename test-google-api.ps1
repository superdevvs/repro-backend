# Google Places API Testing Script
# This script helps you test the transition from mock data to real Google Places API

Write-Host "=== Google Places API Setup Test ===" -ForegroundColor Cyan
Write-Host ""

# Check if API key is configured
$envContent = Get-Content ".env" -Raw
if ($envContent -match "GOOGLE_PLACES_API_KEY=(.+)") {
    $apiKey = $matches[1].Trim()
    if ($apiKey -eq "your_google_places_api_key_here" -or $apiKey -eq "") {
        Write-Host "❌ Google Places API key not configured" -ForegroundColor Red
        Write-Host ""
        Write-Host "To set up Google Places API:" -ForegroundColor Yellow
        Write-Host "1. Go to https://console.cloud.google.com/" -ForegroundColor White
        Write-Host "2. Create a project and enable Places API" -ForegroundColor White
        Write-Host "3. Create an API key" -ForegroundColor White
        Write-Host "4. Update GOOGLE_PLACES_API_KEY in .env file" -ForegroundColor White
        Write-Host "5. Run this script again" -ForegroundColor White
        Write-Host ""
        Write-Host "For detailed instructions, see: PRODUCTION_ADDRESS_SETUP.md" -ForegroundColor Cyan
        exit 1
    } else {
        Write-Host "✅ Google Places API key found" -ForegroundColor Green
        Write-Host "Key: $($apiKey.Substring(0, 10))..." -ForegroundColor Gray
    }
} else {
    Write-Host "❌ GOOGLE_PLACES_API_KEY not found in .env" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Test Google API directly
Write-Host "=== Testing Google Places API Directly ===" -ForegroundColor Cyan
Write-Host "Testing API key with Google..." -NoNewline

try {
    $testUrl = "https://maps.googleapis.com/maps/api/place/autocomplete/json?input=pizza&key=$apiKey"
    $response = Invoke-RestMethod -Uri $testUrl -Method GET -TimeoutSec 10
    
    if ($response.status -eq "OK") {
        Write-Host " ✅ SUCCESS" -ForegroundColor Green
        Write-Host "Found $($response.predictions.Count) suggestions for 'pizza'" -ForegroundColor Gray
    } elseif ($response.status -eq "REQUEST_DENIED") {
        Write-Host " ❌ FAILED" -ForegroundColor Red
        Write-Host "Error: $($response.error_message)" -ForegroundColor Red
        Write-Host ""
        Write-Host "Common fixes:" -ForegroundColor Yellow
        Write-Host "- Enable Places API in Google Cloud Console" -ForegroundColor White
        Write-Host "- Check API key restrictions" -ForegroundColor White
        Write-Host "- Enable billing in Google Cloud Console" -ForegroundColor White
    } else {
        Write-Host " ⚠ WARNING" -ForegroundColor Yellow
        Write-Host "Status: $($response.status)" -ForegroundColor Yellow
        if ($response.error_message) {
            Write-Host "Error: $($response.error_message)" -ForegroundColor Yellow
        }
    }
} catch {
    Write-Host " ❌ ERROR" -ForegroundColor Red
    Write-Host "Failed to connect to Google API: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Test Laravel backend
Write-Host "=== Testing Laravel Backend ===" -ForegroundColor Cyan

# Check if Laravel server is running
try {
    $pingResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/ping" -Method GET -TimeoutSec 5
    Write-Host "✅ Laravel server is running" -ForegroundColor Green
} catch {
    Write-Host "❌ Laravel server is not running" -ForegroundColor Red
    Write-Host "Please start the server with: php artisan serve" -ForegroundColor Yellow
    exit 1
}

# Test address search endpoint
Write-Host "Testing address search endpoint..." -NoNewline

try {
    $searchResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/address/search?query=pizza" -Method GET
    
    if ($searchResponse.success) {
        Write-Host " ✅ SUCCESS" -ForegroundColor Green
        Write-Host "Found $($searchResponse.count) address suggestions" -ForegroundColor Gray
        
        if ($searchResponse.count -gt 0) {
            Write-Host "Sample suggestion: $($searchResponse.data[0].description)" -ForegroundColor Gray
            
            # Test address details if we have a place_id
            $samplePlaceId = $searchResponse.data[0].place_id
            if ($samplePlaceId -and -not $samplePlaceId.StartsWith("mock_")) {
                Write-Host ""
                Write-Host "Testing address details..." -NoNewline
                
                try {
                    $detailsResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/address/details?place_id=$samplePlaceId" -Method GET
                    
                    if ($detailsResponse.success) {
                        Write-Host " ✅ SUCCESS" -ForegroundColor Green
                        $details = $detailsResponse.data
                        Write-Host "Address: $($details.address)" -ForegroundColor Gray
                        Write-Host "City: $($details.city)" -ForegroundColor Gray
                        Write-Host "State: $($details.state)" -ForegroundColor Gray
                        Write-Host "ZIP: $($details.zip)" -ForegroundColor Gray
                        
                        Write-Host ""
                        Write-Host "🎉 REAL GOOGLE DATA IS WORKING!" -ForegroundColor Green
                        Write-Host "Your address lookup is now using live Google Places API" -ForegroundColor Green
                    } else {
                        Write-Host " ❌ FAILED" -ForegroundColor Red
                        Write-Host "Error: $($detailsResponse.error)" -ForegroundColor Red
                    }
                } catch {
                    Write-Host " ❌ ERROR" -ForegroundColor Red
                    Write-Host "Exception: $($_.Exception.Message)" -ForegroundColor Red
                }
            } elseif ($samplePlaceId.StartsWith("mock_")) {
                Write-Host ""
                Write-Host "⚠ Still using mock data" -ForegroundColor Yellow
                Write-Host "The backend is falling back to mock data" -ForegroundColor Yellow
                Write-Host "This usually means the Google API is not responding" -ForegroundColor Yellow
            }
        }
    } else {
        Write-Host " ❌ FAILED" -ForegroundColor Red
        Write-Host "Error: $($searchResponse.error)" -ForegroundColor Red
    }
} catch {
    Write-Host " ❌ ERROR" -ForegroundColor Red
    Write-Host "Exception: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Test service area check
Write-Host "=== Testing Service Area Check ===" -ForegroundColor Cyan
Write-Host "Testing service area validation..." -NoNewline

try {
    $serviceAreaResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/address/service-area?address=123%20Main%20St&city=Washington&state=DC&zip=20001" -Method GET
    
    if ($serviceAreaResponse.success) {
        Write-Host " ✅ SUCCESS" -ForegroundColor Green
        Write-Host "In service area: $($serviceAreaResponse.data.in_service_area)" -ForegroundColor Gray
        Write-Host "Message: $($serviceAreaResponse.data.message)" -ForegroundColor Gray
    } else {
        Write-Host " ❌ FAILED" -ForegroundColor Red
        Write-Host "Error: $($serviceAreaResponse.error)" -ForegroundColor Red
    }
} catch {
    Write-Host " ❌ ERROR" -ForegroundColor Red
    Write-Host "Exception: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Summary and next steps
Write-Host "=== Summary ===" -ForegroundColor Cyan

if ($response.status -eq "OK" -and $searchResponse.success) {
    Write-Host "🎉 SUCCESS! Your Google Places API is working correctly" -ForegroundColor Green
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor White
    Write-Host "1. Test the frontend at: http://localhost:5173/test-client-form" -ForegroundColor White
    Write-Host "2. Try typing real addresses (not the mock ones)" -ForegroundColor White
    Write-Host "3. Deploy to production with the same API key" -ForegroundColor White
    Write-Host "4. Set up domain restrictions for security" -ForegroundColor White
} else {
    Write-Host "⚠ Issues detected with Google Places API setup" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Troubleshooting steps:" -ForegroundColor White
    Write-Host "1. Check your API key in Google Cloud Console" -ForegroundColor White
    Write-Host "2. Ensure Places API is enabled" -ForegroundColor White
    Write-Host "3. Verify billing is enabled" -ForegroundColor White
    Write-Host "4. Check API key restrictions" -ForegroundColor White
    Write-Host ""
    Write-Host "For detailed help, see: PRODUCTION_ADDRESS_SETUP.md" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "=== API Usage Monitoring ===" -ForegroundColor Cyan
Write-Host "Monitor your API usage at:" -ForegroundColor White
Write-Host "https://console.cloud.google.com/apis/api/places-backend.googleapis.com/quotas" -ForegroundColor Cyan

Write-Host ""
Write-Host "Google Places API testing completed!" -ForegroundColor Green
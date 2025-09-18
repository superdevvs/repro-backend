# Google Places API Setup Guide

## Overview
The address lookup feature uses Google Places API to provide:
- Address autocomplete suggestions
- Address validation and standardization
- Geocoding (latitude/longitude coordinates)
- Distance calculations
- Service area verification

## Getting Started

### 1. Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Note your project ID

### 2. Enable Required APIs

Enable these APIs in your Google Cloud project:

1. **Places API** - For address autocomplete and details
2. **Maps JavaScript API** - For frontend map integration (optional)
3. **Geocoding API** - For address validation
4. **Distance Matrix API** - For distance calculations

**Steps:**
1. Go to "APIs & Services" > "Library"
2. Search for each API and click "Enable"

### 3. Create API Key

1. Go to "APIs & Services" > "Credentials"
2. Click "Create Credentials" > "API Key"
3. Copy the generated API key
4. Click "Restrict Key" for security

### 4. Configure API Key Restrictions

**Application Restrictions:**
- For development: None (or IP addresses)
- For production: HTTP referrers (websites)

**API Restrictions:**
Select only the APIs you need:
- Places API
- Geocoding API
- Distance Matrix API
- Maps JavaScript API (if using maps)

### 5. Update Environment Configuration

Add to your `.env` file:
```env
GOOGLE_PLACES_API_KEY=your_api_key_here
GOOGLE_MAPS_API_KEY=your_api_key_here
```

## API Usage and Pricing

### Free Tier (Monthly)
- **Places API**: $0 for first 1,000 requests, then $17/1,000
- **Geocoding API**: $0 for first 40,000 requests, then $5/1,000
- **Distance Matrix API**: $0 for first 40,000 requests, then $5/1,000

### Cost Optimization Tips

1. **Enable Caching**: Results are cached for 5 minutes (search) and 1 hour (details)
2. **Restrict API Key**: Limit to only required APIs and domains
3. **Set Usage Quotas**: Prevent unexpected charges
4. **Use Autocomplete Efficiently**: Only search after 3+ characters

## Testing the Setup

### 1. Test API Key
```bash
curl "https://maps.googleapis.com/maps/api/place/autocomplete/json?input=123%20Main%20St&key=YOUR_API_KEY"
```

### 2. Test Backend Endpoints
```bash
# Search addresses
curl "http://localhost:8000/api/address/search?query=123%20Main%20Street"

# Get address details
curl "http://localhost:8000/api/address/details?place_id=PLACE_ID_HERE"

# Validate address
curl -X POST "http://localhost:8000/api/address/validate" \
  -H "Content-Type: application/json" \
  -d '{"address":"123 Main St","city":"Anytown","state":"CA","zip":"12345"}'
```

### 3. Test Frontend Component
1. Start your React development server
2. Navigate to the address lookup demo
3. Try typing an address to see autocomplete suggestions

## Security Best Practices

### 1. API Key Security
- Never commit API keys to version control
- Use environment variables
- Restrict API key to specific domains/IPs
- Rotate keys regularly

### 2. Rate Limiting
- Implement request throttling
- Cache results to reduce API calls
- Set usage quotas in Google Cloud Console

### 3. Error Handling
- Handle API failures gracefully
- Provide fallback options
- Log errors for monitoring

## Production Deployment

### 1. Environment-Specific Keys
```env
# Development
GOOGLE_PLACES_API_KEY=dev_key_here

# Production
GOOGLE_PLACES_API_KEY=prod_key_here
```

### 2. Domain Restrictions
For production, restrict API key to your domains:
- `https://yourdomain.com/*`
- `https://www.yourdomain.com/*`
- `https://api.yourdomain.com/*`

### 3. Monitoring
Set up monitoring for:
- API usage and costs
- Error rates
- Response times
- Quota limits

## Troubleshooting

### Common Issues

**"API key not valid"**
- Check if API key is correct
- Verify API is enabled
- Check key restrictions

**"This API project is not authorized"**
- Enable required APIs in Google Cloud Console
- Check billing account is active

**"REQUEST_DENIED"**
- Check API key restrictions
- Verify domain/IP restrictions
- Ensure billing is enabled

**No results returned**
- Check query format
- Verify location restrictions
- Test with known addresses

### Debug Mode
Enable debug logging in Laravel:
```env
LOG_LEVEL=debug
```

Check logs at `storage/logs/laravel.log` for API responses.

## Alternative Solutions

If Google Places API doesn't fit your needs:

### 1. Mapbox Geocoding API
- Similar features
- Different pricing structure
- Good international coverage

### 2. HERE Geocoding API
- Enterprise-focused
- Batch processing capabilities
- Offline options available

### 3. OpenStreetMap Nominatim
- Free and open source
- Self-hostable
- Limited commercial support

## Integration Examples

### Basic Address Lookup
```javascript
// Frontend usage
const handleAddressSelect = (address) => {
  console.log('Selected address:', address);
  // Use address data in your form
};

<AddressLookup 
  onAddressSelect={handleAddressSelect}
  placeholder="Enter property address..."
  required
/>
```

### Service Area Validation
```php
// Backend usage
$addressService = new AddressLookupService();
$validation = $addressService->validateAddress([
    'address' => '123 Main St',
    'city' => 'Anytown',
    'state' => 'CA',
    'zip' => '12345'
]);

if ($validation['is_valid']) {
    // Proceed with booking
}
```

## Support

For issues with:
- **Google APIs**: Check Google Cloud Console documentation
- **Implementation**: Review Laravel logs and browser console
- **Billing**: Contact Google Cloud Support

The address lookup system is now ready for production use with proper API key configuration!
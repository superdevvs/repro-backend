# Production Address Lookup Setup Guide

## Overview
To get real address data in production, you need to configure Google Places API. This will replace the mock data with actual address suggestions from Google's database.

## Step-by-Step Setup

### 1. Create Google Cloud Project
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Note your project ID

### 2. Enable Required APIs
Enable these APIs in your Google Cloud project:
- **Places API** (for address autocomplete)
- **Geocoding API** (for address validation)
- **Distance Matrix API** (for distance calculations)

**How to enable:**
1. Go to "APIs & Services" > "Library"
2. Search for each API and click "Enable"

### 3. Create API Key
1. Go to "APIs & Services" > "Credentials"
2. Click "Create Credentials" > "API Key"
3. Copy the generated API key
4. Click "Restrict Key" for security

### 4. Configure API Key Restrictions

**For Development:**
- Application restrictions: None (or IP addresses)
- API restrictions: Select only the APIs you need

**For Production:**
- Application restrictions: HTTP referrers (websites)
- Add your domains:
  - `https://yourdomain.com/*`
  - `https://www.yourdomain.com/*`
  - `https://api.yourdomain.com/*`

### 5. Update Backend Configuration

**Update your `.env` file:**
```env
# Replace with your actual API key
GOOGLE_PLACES_API_KEY=AIzaSyBvOkBo-qLzb6X-mBmXMjgtgNgjHO00000

# Optional: separate key for maps if needed
GOOGLE_MAPS_API_KEY=AIzaSyBvOkBo-qLzb6X-mBmXMjgtgNgjHO00000
```

### 6. Test the Setup

**Test API key with curl:**
```bash
curl "https://maps.googleapis.com/maps/api/place/autocomplete/json?input=123%20Main%20St&key=YOUR_API_KEY"
```

**Test backend endpoints:**
```bash
# Test address search
curl "http://localhost:8000/api/address/search?query=123%20Main%20Street"

# Test address details
curl "http://localhost:8000/api/address/details?place_id=PLACE_ID_HERE"
```

## What Changes in Production

### Before (Mock Data)
```javascript
// Limited to 5 predefined addresses
const mockAddresses = [
  '123 Main Street, Washington, DC',
  '456 Oak Avenue, New York, NY',
  // ... 3 more mock addresses
];
```

### After (Real Google Data)
```javascript
// Unlimited real addresses from Google Places
// Examples of what users will see:
- "123 Main St, Anytown, CA, USA"
- "456 Oak Ave, Springfield, IL, USA"  
- "789 Pine Rd, Austin, TX, USA"
// + millions more real addresses
```

## API Usage and Costs

### Google Places API Pricing (2024)
- **Autocomplete**: $2.83 per 1,000 requests
- **Place Details**: $17 per 1,000 requests
- **Geocoding**: $5 per 1,000 requests

### Free Tier
- **$200 monthly credit** (covers ~70,000 autocomplete requests)
- **No charge** for first $200 of usage each month

### Cost Optimization
1. **Caching**: Results cached for 5 minutes (search) and 1 hour (details)
2. **Debouncing**: 300ms delay reduces unnecessary requests
3. **Fallback**: Falls back to manual entry if API fails

## Security Best Practices

### 1. API Key Security
```env
# ✅ Good - Use environment variables
GOOGLE_PLACES_API_KEY=your_key_here

# ❌ Bad - Never hardcode in source code
const API_KEY = "AIzaSyBvOkBo-qLzb6X-mBmXMjgtgNgjHO00000";
```

### 2. Domain Restrictions
- Restrict API key to your specific domains
- Use different keys for development and production
- Monitor usage in Google Cloud Console

### 3. Rate Limiting
```php
// Backend automatically handles rate limiting
// Set quotas in Google Cloud Console to prevent overuse
```

## Testing Production Setup

### 1. Verify API Key Works
```bash
# Test with your actual API key
curl "https://maps.googleapis.com/maps/api/place/autocomplete/json?input=pizza&key=YOUR_ACTUAL_KEY"
```

### 2. Test Frontend Integration
1. Update backend `.env` with real API key
2. Restart Laravel server: `php artisan serve`
3. Go to `http://localhost:5173/test-client-form`
4. Type any real address (e.g., "1600 Pennsylvania Ave")
5. You should see real Google suggestions

### 3. Monitor Usage
- Check Google Cloud Console for API usage
- Set up billing alerts
- Monitor error rates

## Deployment Checklist

### Backend Deployment
- [ ] Add `GOOGLE_PLACES_API_KEY` to production environment
- [ ] Verify API key has correct restrictions
- [ ] Test API endpoints work in production
- [ ] Set up monitoring and logging

### Frontend Deployment  
- [ ] No changes needed (automatically uses real API when available)
- [ ] Test address lookup works on production domain
- [ ] Verify auto-fill functionality works

### Security Checklist
- [ ] API key restricted to production domains
- [ ] Separate keys for dev/staging/production
- [ ] Usage quotas set in Google Cloud Console
- [ ] Billing alerts configured

## Troubleshooting

### Common Issues

**"API key not valid"**
- Check if API key is correct in `.env`
- Verify Places API is enabled
- Check key restrictions match your domain

**"REQUEST_DENIED"**
- Enable billing in Google Cloud Console
- Check API key restrictions
- Verify domain restrictions

**No suggestions appearing**
- Check browser console for errors
- Verify backend server is running
- Test API key with curl command

**High API costs**
- Check usage in Google Cloud Console
- Verify caching is working
- Consider implementing usage limits

## Alternative Solutions

If Google Places API doesn't fit your budget:

### 1. Mapbox Geocoding
- Similar functionality
- Different pricing structure
- Good international coverage

### 2. HERE Geocoding
- Enterprise-focused
- Batch processing capabilities
- Competitive pricing for high volume

### 3. Hybrid Approach
- Use Google for US addresses
- Use alternative service for international
- Implement smart fallbacks

## Expected User Experience

### With Real Google Data
1. **User types**: "1600 Penn"
2. **Sees suggestions**: 
   - "1600 Pennsylvania Avenue NW, Washington, DC, USA"
   - "1600 Pennsylvania St, Denver, CO, USA"
   - "1600 Penn Ave, Pittsburgh, PA, USA"
3. **Selects address**: Auto-fills with real, validated data
4. **Gets coordinates**: Latitude/longitude for mapping
5. **Service area check**: Real distance calculations

### Benefits Over Mock Data
- ✅ **Unlimited addresses** worldwide
- ✅ **Real validation** and standardization  
- ✅ **Accurate coordinates** for mapping
- ✅ **International support** (if needed)
- ✅ **Always up-to-date** address database

## Quick Start Commands

```bash
# 1. Get your Google API key from console.cloud.google.com
# 2. Update backend .env file
echo "GOOGLE_PLACES_API_KEY=your_key_here" >> .env

# 3. Restart Laravel server
php artisan serve

# 4. Test the integration
curl "http://localhost:8000/api/address/search?query=your_real_address"
```

Your address lookup will now use real Google data instead of mock data!
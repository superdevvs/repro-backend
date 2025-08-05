// Update your DropboxCallback.tsx component to work with Laravel

import React, { useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';

const DropboxCallback: React.FC = () => {
  const [searchParams] = useSearchParams();

  useEffect(() => {
    const handleCallback = async () => {
      const code = searchParams.get('code');
      const error = searchParams.get('error');

      if (error) {
        // Send error message to parent window
        if (window.opener) {
          window.opener.postMessage({
            type: 'DROPBOX_AUTH_ERROR',
            error: error
          }, window.location.origin);
        }
        return;
      }

      if (code) {
        try {
          // Get auth token for API calls
          const authToken = localStorage.getItem('authToken');
          
          // Exchange code for access token using Laravel API
          const response = await fetch(`${import.meta.env.VITE_API_URL}/api/auth/dropbox/token`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${authToken}`, // Include your app's auth token if needed
              'Accept': 'application/json'
            },
            body: JSON.stringify({ code })
          });

          if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to exchange code for token');
          }

          const data = await response.json();
          
          // Send success message to parent window
          if (window.opener) {
            window.opener.postMessage({
              type: 'DROPBOX_AUTH_SUCCESS',
              accessToken: data.access_token,
              tokenType: data.token_type,
              expiresIn: data.expires_in
            }, window.location.origin);
          }
        } catch (error) {
          console.error('Token exchange error:', error);
          if (window.opener) {
            window.opener.postMessage({
              type: 'DROPBOX_AUTH_ERROR',
              error: error instanceof Error ? error.message : 'Failed to complete authentication'
            }, window.location.origin);
          }
        }
      }
    };

    handleCallback();
  }, [searchParams]);

  return (
    <div className="flex items-center justify-center min-h-screen bg-background">
      <div className="text-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
        <p className="text-muted-foreground">Completing Dropbox authentication...</p>
      </div>
    </div>
  );
};

export default DropboxCallback;

// Also update your FileUploader component's connectDropbox function:

const connectDropbox = async () => {
  setDropboxAuth(prev => ({ ...prev, isConnecting: true }));
  
  try {
    // Your Dropbox App Key (get this from Dropbox App Console)
    const APP_KEY = import.meta.env.VITE_DROPBOX_APP_KEY || 'your_dropbox_app_key';
    const REDIRECT_URI = `${window.location.origin}/dropbox-callback`;
    
    // Create authorization URL
    const authUrl = `https://www.dropbox.com/oauth2/authorize?client_id=${APP_KEY}&response_type=code&redirect_uri=${encodeURIComponent(REDIRECT_URI)}&token_access_type=offline`;
    
    // Open popup window for authentication
    const popup = window.open(
      authUrl,
      'dropbox-auth',
      'width=600,height=700,scrollbars=yes,resizable=yes'
    );
    
    // Listen for the callback
    const handleCallback = (event: MessageEvent) => {
      if (event.origin !== window.location.origin) return;
      
      if (event.data.type === 'DROPBOX_AUTH_SUCCESS') {
        const { accessToken, tokenType, expiresIn } = event.data;
        localStorage.setItem('dropbox_access_token', accessToken);
        if (expiresIn) {
          localStorage.setItem('dropbox_token_expires', (Date.now() + (expiresIn * 1000)).toString());
        }
        
        setDropboxAuth({
          isAuthenticated: true,
          accessToken,
          isConnecting: false
        });
        popup?.close();
        loadDropboxFiles();
        
        toast({
          title: "Dropbox Connected",
          description: "Successfully connected to Dropbox. Loading your files...",
        });
      } else if (event.data.type === 'DROPBOX_AUTH_ERROR') {
        setDropboxAuth(prev => ({ ...prev, isConnecting: false }));
        toast({
          title: "Connection Failed",
          description: event.data.error || "Failed to connect to Dropbox. Please try again.",
          variant: "destructive"
        });
        popup?.close();
      }
    };
    
    window.addEventListener('message', handleCallback);
    
    // Clean up if popup is closed manually
    const checkClosed = setInterval(() => {
      if (popup?.closed) {
        clearInterval(checkClosed);
        window.removeEventListener('message', handleCallback);
        setDropboxAuth(prev => ({ ...prev, isConnecting: false }));
      }
    }, 1000);
    
  } catch (error) {
    console.error('Dropbox connection error:', error);
    setDropboxAuth(prev => ({ ...prev, isConnecting: false }));
    toast({
      title: "Connection Error",
      description: "An error occurred while connecting to Dropbox.",
      variant: "destructive"
    });
  }
};

// Optional: Add token refresh functionality
const refreshDropboxToken = async () => {
  const refreshToken = localStorage.getItem('dropbox_refresh_token');
  if (!refreshToken) return false;

  try {
    const authToken = localStorage.getItem('authToken');
    
    const response = await fetch(`${import.meta.env.VITE_API_URL}/api/auth/dropbox/refresh`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authToken}`,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ refresh_token: refreshToken })
    });

    if (!response.ok) return false;

    const data = await response.json();
    localStorage.setItem('dropbox_access_token', data.access_token);
    if (data.expires_in) {
      localStorage.setItem('dropbox_token_expires', (Date.now() + (data.expires_in * 1000)).toString());
    }

    setDropboxAuth(prev => ({
      ...prev,
      accessToken: data.access_token
    }));

    return true;
  } catch (error) {
    console.error('Token refresh failed:', error);
    return false;
  }
};

// Check if token is expired and refresh if needed
const checkTokenExpiry = async () => {
  const expiresAt = localStorage.getItem('dropbox_token_expires');
  if (!expiresAt) return;

  const expiryTime = parseInt(expiresAt);
  const now = Date.now();
  
  // Refresh token 5 minutes before expiry
  if (now >= (expiryTime - 5 * 60 * 1000)) {
    const refreshed = await refreshDropboxToken();
    if (!refreshed) {
      // Refresh failed, disconnect
      disconnectDropbox();
      toast({
        title: "Session Expired",
        description: "Your Dropbox session has expired. Please reconnect.",
        variant: "destructive"
      });
    }
  }
};
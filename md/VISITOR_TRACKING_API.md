# Visitor Tracking API - Client Endpoints Documentation

## Overview

The Visitor Tracking API allows frontend applications to track visitor analytics including page views, device information, geolocation, and session data. This data is used for analytics, user behavior tracking, and improving the application experience.

**Base URL:** `/api/v1`

**Authentication:** Not required (public endpoints)

---

## Endpoints

### 1. Track Visitor / Page View

Track a visitor or page view. This endpoint should be called on every page load or route change.

#### Endpoint
```http
POST /api/v1/visitors/track
```

#### Headers
```http
Content-Type: application/json
X-Session-ID: visitor_abc123... (optional, will be auto-generated if not provided)
User-Agent: (automatically captured from browser)
```

#### Request Body (All fields are optional)

```json
{
  "session_id": "visitor_abc123def456_1234567890",
  "country": "US",
  "country_name": "United States",
  "region": "California",
  "city": "San Francisco",
  "latitude": 37.7749,
  "longitude": -122.4194,
  "timezone": "America/Los_Angeles",
  "language": "en-US",
  "screen_resolution": "1920x1080",
  "referrer": "https://example.com",
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "summer_sale",
  "utm_term": "keyword",
  "utm_content": "ad_variant",
  "metadata": {
    "custom_field": "custom_value",
    "page_name": "homepage"
  }
}
```

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | No | Unique session identifier. If not provided, one will be generated. |
| `country` | string | No | ISO country code (e.g., "US", "ET", "GB") |
| `country_name` | string | No | Full country name (e.g., "United States") |
| `region` | string | No | State/Province name |
| `city` | string | No | City name |
| `latitude` | number | No | Latitude coordinate |
| `longitude` | number | No | Longitude coordinate |
| `timezone` | string | No | Timezone (e.g., "America/Los_Angeles") |
| `language` | string | No | Browser language (e.g., "en-US") |
| `screen_resolution` | string | No | Screen resolution (e.g., "1920x1080") |
| `referrer` | string | No | HTTP referrer URL |
| `utm_source` | string | No | UTM source parameter |
| `utm_medium` | string | No | UTM medium parameter |
| `utm_campaign` | string | No | UTM campaign parameter |
| `utm_term` | string | No | UTM term parameter |
| `utm_content` | string | No | UTM content parameter |
| `metadata` | object | No | Custom metadata object |

#### Success Response (200 OK)

```json
{
  "success": true,
  "session_id": "visitor_abc123def456_1234567890",
  "message": "Visitor tracked successfully"
}
```

#### Error Response (500 Internal Server Error)

```json
{
  "success": false,
  "message": "Failed to track visitor"
}
```

#### Example: JavaScript/Fetch

```javascript
// Generate or retrieve session ID
let sessionId = localStorage.getItem('visitor_session_id');
if (!sessionId) {
  sessionId = 'visitor_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now();
  localStorage.setItem('visitor_session_id', sessionId);
}

// Track page view
async function trackVisitor() {
  try {
    // Extract UTM parameters from URL
    const urlParams = new URLSearchParams(window.location.search);
    
    // Get geolocation if available (optional)
    let locationData = {};
    if (navigator.geolocation) {
      // Note: This requires user permission
      // For production, consider using an IP geolocation service
    }

    const response = await fetch('/api/v1/visitors/track', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Session-ID': sessionId,
      },
      body: JSON.stringify({
        session_id: sessionId,
        country: navigator.language.split('-')[1] || null,
        screen_resolution: `${screen.width}x${screen.height}`,
        language: navigator.language,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        referrer: document.referrer || null,
        utm_source: urlParams.get('utm_source'),
        utm_medium: urlParams.get('utm_medium'),
        utm_campaign: urlParams.get('utm_campaign'),
        utm_term: urlParams.get('utm_term'),
        utm_content: urlParams.get('utm_content'),
        metadata: {
          page_name: window.location.pathname,
          page_title: document.title,
        },
      }),
    });

    const data = await response.json();
    
    if (data.success) {
      // Update stored session ID if server generated a new one
      if (data.session_id && data.session_id !== sessionId) {
        localStorage.setItem('visitor_session_id', data.session_id);
      }
      console.log('Visitor tracked successfully');
    }
  } catch (error) {
    console.error('Failed to track visitor:', error);
    // Don't throw - tracking failures shouldn't break the app
  }
}

// Call on page load
trackVisitor();
```

#### Example: React Hook

```javascript
import { useEffect, useRef } from 'react';

function useVisitorTracking() {
  const sessionIdRef = useRef(null);

  useEffect(() => {
    // Get or create session ID
    let sessionId = localStorage.getItem('visitor_session_id');
    if (!sessionId) {
      sessionId = 'visitor_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now();
      localStorage.setItem('visitor_session_id', sessionId);
    }
    sessionIdRef.current = sessionId;

    // Track page view
    const trackPageView = async () => {
      try {
        const urlParams = new URLSearchParams(window.location.search);
        
        await fetch('/api/v1/visitors/track', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Session-ID': sessionId,
          },
          body: JSON.stringify({
            session_id: sessionId,
            screen_resolution: `${window.screen.width}x${window.screen.height}`,
            language: navigator.language,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            referrer: document.referrer || null,
            utm_source: urlParams.get('utm_source'),
            utm_medium: urlParams.get('utm_medium'),
            utm_campaign: urlParams.get('utm_campaign'),
            metadata: {
              page_name: window.location.pathname,
              page_title: document.title,
            },
          }),
        });
      } catch (error) {
        console.error('Visitor tracking error:', error);
      }
    };

    trackPageView();
  }, []); // Run once on mount

  return sessionIdRef.current;
}

// Usage in component
function MyComponent() {
  const sessionId = useVisitorTracking();
  // ... rest of component
}
```

#### Example: Vue.js

```javascript
// In your main.js or plugin
export default {
  install(app) {
    // Get or create session ID
    let sessionId = localStorage.getItem('visitor_session_id');
    if (!sessionId) {
      sessionId = 'visitor_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now();
      localStorage.setItem('visitor_session_id', sessionId);
    }

    // Track on route change
    app.config.globalProperties.$trackVisitor = async (route) => {
      try {
        const urlParams = new URLSearchParams(window.location.search);
        
        await fetch('/api/v1/visitors/track', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Session-ID': sessionId,
          },
          body: JSON.stringify({
            session_id: sessionId,
            screen_resolution: `${screen.width}x${screen.height}`,
            language: navigator.language,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            referrer: document.referrer || null,
            utm_source: urlParams.get('utm_source'),
            utm_medium: urlParams.get('utm_medium'),
            utm_campaign: urlParams.get('utm_campaign'),
            metadata: {
              page_name: route.path,
              page_title: route.meta?.title || document.title,
            },
          }),
        });
      } catch (error) {
        console.error('Visitor tracking error:', error);
      }
    };
  },
};

// In router
router.afterEach((to) => {
  app.config.globalProperties.$trackVisitor(to);
});
```

---

### 2. Update Visitor Activity

Update visitor activity to track session duration. This should be called periodically (e.g., every 30 seconds) while the user is active.

#### Endpoint
```http
POST /api/v1/visitors/activity
```

#### Headers
```http
Content-Type: application/json
X-Session-ID: visitor_abc123... (required)
```

#### Request Body

```json
{
  "session_id": "visitor_abc123def456_1234567890"
}
```

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | Yes | Unique session identifier |

#### Success Response (200 OK)

```json
{
  "success": true
}
```

#### Error Response (400 Bad Request)

```json
{
  "success": false,
  "message": "Session ID required"
}
```

#### Error Response (500 Internal Server Error)

```json
{
  "success": false
}
```

#### Example: JavaScript

```javascript
// Update activity every 30 seconds
let activityInterval = null;

function startActivityTracking(sessionId) {
  // Clear existing interval if any
  if (activityInterval) {
    clearInterval(activityInterval);
  }

  // Update activity immediately
  updateActivity(sessionId);

  // Then update every 30 seconds
  activityInterval = setInterval(() => {
    updateActivity(sessionId);
  }, 30000); // 30 seconds
}

async function updateActivity(sessionId) {
  try {
    await fetch('/api/v1/visitors/activity', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Session-ID': sessionId,
      },
      body: JSON.stringify({
        session_id: sessionId,
      }),
    });
  } catch (error) {
    console.error('Activity update failed:', error);
    // Don't throw - activity tracking failures shouldn't break the app
  }
}

// Start tracking when page loads
const sessionId = localStorage.getItem('visitor_session_id');
if (sessionId) {
  startActivityTracking(sessionId);
}

// Stop tracking when page unloads
window.addEventListener('beforeunload', () => {
  if (activityInterval) {
    clearInterval(activityInterval);
  }
});
```

#### Example: React Hook with Activity Tracking

```javascript
import { useEffect, useRef } from 'react';

function useVisitorActivityTracking(sessionId) {
  const intervalRef = useRef(null);

  useEffect(() => {
    if (!sessionId) return;

    const updateActivity = async () => {
      try {
        await fetch('/api/v1/visitors/activity', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Session-ID': sessionId,
          },
          body: JSON.stringify({ session_id: sessionId }),
        });
      } catch (error) {
        console.error('Activity update failed:', error);
      }
    };

    // Update immediately
    updateActivity();

    // Then update every 30 seconds
    intervalRef.current = setInterval(updateActivity, 30000);

    // Cleanup on unmount
    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, [sessionId]);
}

// Usage
function MyComponent() {
  const sessionId = useVisitorTracking();
  useVisitorActivityTracking(sessionId);
  // ... rest of component
}
```

---

## Complete Integration Example

### React Component Example

```javascript
import { useEffect, useState } from 'react';

function VisitorTracker() {
  const [sessionId, setSessionId] = useState(null);

  useEffect(() => {
    // Initialize session ID
    let sid = localStorage.getItem('visitor_session_id');
    if (!sid) {
      sid = 'visitor_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now();
      localStorage.setItem('visitor_session_id', sid);
    }
    setSessionId(sid);

    // Track initial page view
    trackPageView(sid);

    // Start activity tracking
    const activityInterval = setInterval(() => {
      updateActivity(sid);
    }, 30000);

    // Cleanup
    return () => {
      clearInterval(activityInterval);
    };
  }, []);

  const trackPageView = async (sid) => {
    try {
      const urlParams = new URLSearchParams(window.location.search);
      
      const response = await fetch('/api/v1/visitors/track', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Session-ID': sid,
        },
        body: JSON.stringify({
          session_id: sid,
          screen_resolution: `${window.screen.width}x${window.screen.height}`,
          language: navigator.language,
          timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
          referrer: document.referrer || null,
          utm_source: urlParams.get('utm_source'),
          utm_medium: urlParams.get('utm_medium'),
          utm_campaign: urlParams.get('utm_campaign'),
          metadata: {
            page_name: window.location.pathname,
            page_title: document.title,
          },
        }),
      });

      const data = await response.json();
      if (data.success && data.session_id && data.session_id !== sid) {
        localStorage.setItem('visitor_session_id', data.session_id);
        setSessionId(data.session_id);
      }
    } catch (error) {
      console.error('Visitor tracking error:', error);
    }
  };

  const updateActivity = async (sid) => {
    try {
      await fetch('/api/v1/visitors/activity', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Session-ID': sid,
        },
        body: JSON.stringify({ session_id: sid }),
      });
    } catch (error) {
      console.error('Activity update error:', error);
    }
  };

  return null; // This component doesn't render anything
}

export default VisitorTracker;
```

### Vue.js Plugin Example

```javascript
// visitor-tracking.js
export default {
  install(app) {
    // Get or create session ID
    let sessionId = localStorage.getItem('visitor_session_id');
    if (!sessionId) {
      sessionId = 'visitor_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now();
      localStorage.setItem('visitor_session_id', sessionId);
    }

    // Track page view
    const trackPageView = async (route) => {
      try {
        const urlParams = new URLSearchParams(window.location.search);
        
        const response = await fetch('/api/v1/visitors/track', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Session-ID': sessionId,
          },
          body: JSON.stringify({
            session_id: sessionId,
            screen_resolution: `${screen.width}x${screen.height}`,
            language: navigator.language,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            referrer: document.referrer || null,
            utm_source: urlParams.get('utm_source'),
            utm_medium: urlParams.get('utm_medium'),
            utm_campaign: urlParams.get('utm_campaign'),
            metadata: {
              page_name: route.path,
              page_title: route.meta?.title || document.title,
            },
          }),
        });

        const data = await response.json();
        if (data.success && data.session_id && data.session_id !== sessionId) {
          sessionId = data.session_id;
          localStorage.setItem('visitor_session_id', sessionId);
        }
      } catch (error) {
        console.error('Visitor tracking error:', error);
      }
    };

    // Update activity
    const updateActivity = async () => {
      try {
        await fetch('/api/v1/visitors/activity', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Session-ID': sessionId,
          },
          body: JSON.stringify({ session_id: sessionId }),
        });
      } catch (error) {
        console.error('Activity update error:', error);
      }
    };

    // Start activity tracking
    let activityInterval = setInterval(updateActivity, 30000);

    // Track on route change
    app.config.globalProperties.$trackVisitor = trackPageView;
    
    // Make session ID available
    app.config.globalProperties.$visitorSessionId = sessionId;

    // Cleanup on app unmount
    app.config.globalProperties.$stopVisitorTracking = () => {
      if (activityInterval) {
        clearInterval(activityInterval);
      }
    };
  },
};

// In main.js
import { createApp } from 'vue';
import VisitorTracking from './plugins/visitor-tracking';
import router from './router';

const app = createApp(App);
app.use(VisitorTracking);
app.use(router);

// Track on route change
router.afterEach((to) => {
  app.config.globalProperties.$trackVisitor(to);
});
```

---

## Best Practices

### 1. Session ID Management
- Store session ID in `localStorage` for persistence across page reloads
- Generate a unique session ID if one doesn't exist
- Use the format: `visitor_{random_string}_{timestamp}`
- Update stored session ID if server generates a new one

### 2. Tracking Frequency
- **Page Views:** Track on every page load or route change
- **Activity Updates:** Update every 30 seconds while user is active
- **Don't over-track:** Avoid tracking on every scroll or mouse movement

### 3. Error Handling
- Always wrap tracking calls in try-catch blocks
- Never let tracking failures break your application
- Log errors for debugging but don't show them to users

### 4. Performance
- Use `fetch` with async/await or promises
- Don't wait for tracking to complete before rendering the page
- Consider using `navigator.sendBeacon()` for tracking on page unload

### 5. Privacy & GDPR
- Inform users about tracking in your privacy policy
- Provide opt-out mechanism if required
- Don't track sensitive information
- Respect user's "Do Not Track" preference if applicable

### 6. UTM Parameters
- Extract UTM parameters from URL query string
- Pass them to the tracking endpoint
- Useful for marketing campaign tracking

### 7. Geolocation
- Browser geolocation requires user permission
- Consider using IP-based geolocation services for automatic tracking
- Or let the backend handle IP geolocation

---

## Rate Limiting

- **Track Endpoint:** 100 requests per minute per IP
- **Activity Endpoint:** 60 requests per minute per IP

If you exceed the rate limit, you'll receive a `429 Too Many Requests` response. The tracking system is designed to be resilient, so occasional rate limit hits won't break functionality.

---

## CORS

All endpoints support CORS and can be called from any origin. The API automatically adds necessary CORS headers to responses.

---

## Data Automatically Captured

The following data is automatically captured by the backend (you don't need to send it):

- **IP Address:** From request headers
- **User Agent:** Browser and device information
- **Device Type:** Mobile/Tablet/Desktop (detected from user agent)
- **Browser:** Chrome, Firefox, Safari, etc. (detected from user agent)
- **Operating System:** Windows, macOS, iOS, Android, etc. (detected from user agent)
- **Is Bot:** Automatic bot detection

---

## Testing

### Test Track Endpoint

```bash
curl -X POST http://your-domain.com/api/v1/visitors/track \
  -H "Content-Type: application/json" \
  -H "X-Session-ID: visitor_test123" \
  -d '{
    "session_id": "visitor_test123",
    "country": "US",
    "screen_resolution": "1920x1080",
    "language": "en-US"
  }'
```

### Test Activity Endpoint

```bash
curl -X POST http://your-domain.com/api/v1/visitors/activity \
  -H "Content-Type: application/json" \
  -H "X-Session-ID: visitor_test123" \
  -d '{
    "session_id": "visitor_test123"
  }'
```

---

## Support

For issues or questions about the Visitor Tracking API, please contact the development team or refer to the main API documentation.

---

**Last Updated:** November 30, 2025


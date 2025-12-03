# Billing History Pagination Implementation

## Overview

The billing history endpoint (`GET /api/v1/{region}/billing/history`) now supports pagination to handle large numbers of subscription records efficiently.

## API Changes

### Endpoint
```
GET /api/v1/{region}/billing/history
```

### Query Parameters
- `page` (optional, default: 1) - Page number to retrieve
- `per_page` (optional, default: 10) - Number of items per page
  - Minimum: 1
  - Maximum: 100 (automatically clamped)

### Response Format

**Success Response (200 OK):**
```json
{
  "history": [
    {
      "id": 1,
      "plan": {...},
      "start_date": "2024-01-01T00:00:00Z",
      "end_date": "2024-02-01T00:00:00Z",
      ...
    },
    ...
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 43,
    "from": 1,
    "to": 10
  }
}
```

**Meta Fields:**
- `current_page`: Current page number
- `last_page`: Total number of pages
- `per_page`: Number of items per page
- `total`: Total number of items across all pages
- `from`: Starting item number for current page
- `to`: Ending item number for current page

## Frontend Integration

The frontend can now:
1. Request specific pages: `?page=2&per_page=10`
2. Check if more pages exist: `meta.last_page > meta.current_page`
3. Display pagination controls when `meta.last_page > 1`
4. Show total count: `meta.total`

### Example Frontend Usage

```javascript
// Load first page (default)
const response = await fetch('/api/v1/intl/billing/history', {
  headers: { 'Authorization': `Bearer ${token}` }
});
const data = await response.json();

// Check if pagination is needed
if (data.meta.last_page > 1) {
  // Show pagination controls
  // Load next page: ?page=2&per_page=10
}
```

## Backward Compatibility

The response structure maintains backward compatibility:
- `history` array is still present (now paginated)
- Additional `meta` object provides pagination information
- Frontend code that expects `history` array will continue to work

## Performance

- Default page size: 10 items (prevents loading too much data at once)
- Maximum page size: 100 items (prevents performance issues)
- Efficient database queries using Laravel's pagination

## Testing

All edge cases are handled:
- ✅ Default pagination (10 items per page)
- ✅ Custom per_page values
- ✅ Large per_page values clamped to 100
- ✅ Zero/negative per_page values clamped to 1
- ✅ Invalid page numbers handled gracefully


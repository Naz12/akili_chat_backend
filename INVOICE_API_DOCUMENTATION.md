# Invoice API Documentation

## Overview

The Invoice API provides endpoints for managing and downloading invoices. Invoices are generated from Bills (preferred) or Subscriptions (fallback for backward compatibility).

## Base URL

```
/api/v1/{region}/billing/invoices
```

Where `{region}` is either `local` or `intl`.

## Authentication

All endpoints require authentication. Include the JWT token in the Authorization header:

```
Authorization: Bearer {your_jwt_token}
```

---

## Endpoints

### 1. List Invoices

Get a paginated list of all invoices for the authenticated user.

**Endpoint:** `GET /api/v1/{region}/billing/invoices`

**Query Parameters:**
- `page` (optional, default: 1) - Page number
- `per_page` (optional, default: 10) - Items per page (min: 1, max: 100)

**Example Request:**
```javascript
const response = await fetch('/api/v1/intl/billing/invoices?page=1&per_page=10', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

**Response (200 OK):**
```json
{
  "invoices": [
    {
      "id": 1,
      "bill_id": 1,
      "subscription_id": 5,
      "invoice_number": "INV-00000001",
      "plan_name": "Pro Plan",
      "amount": 30.00,
      "currency": "ETB",
      "status": "paid",
      "payment_status": "succeeded",
      "due_date": "2024-01-15T00:00:00Z",
      "paid_at": "2024-01-10T10:30:00Z",
      "period_start": "2024-01-01T00:00:00Z",
      "period_end": "2024-02-01T00:00:00Z",
      "created_at": "2024-01-01T00:00:00Z"
    }
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

**Response Fields:**
- `invoices` - Array of invoice objects
  - `id` - Invoice/Bill ID
  - `bill_id` - Bill ID (null if generated from subscription)
  - `subscription_id` - Associated subscription ID
  - `invoice_number` - Formatted invoice number (INV-XXXXXXXX)
  - `plan_name` - Name of the subscription plan
  - `amount` - Invoice amount
  - `currency` - Currency code (ETB, USD, etc.)
  - `status` - Invoice status: `paid`, `pending`, `cancelled`, `overdue`
  - `payment_status` - Payment status: `succeeded`, `pending`, `failed`, `unknown`
  - `due_date` - Due date (ISO 8601)
  - `paid_at` - Payment date (ISO 8601, null if not paid)
  - `period_start` - Billing period start (ISO 8601)
  - `period_end` - Billing period end (ISO 8601)
  - `created_at` - Invoice creation date (ISO 8601)
- `meta` - Pagination metadata
  - `current_page` - Current page number
  - `last_page` - Total number of pages
  - `per_page` - Items per page
  - `total` - Total number of invoices
  - `from` - Starting item number
  - `to` - Ending item number

**Error Responses:**
- `401 Unauthorized` - Invalid or missing authentication token
- `422 Unprocessable Entity` - Invalid pagination parameters

---

### 2. Get Single Invoice

Get detailed information about a specific invoice.

**Endpoint:** `GET /api/v1/{region}/billing/invoices/{id}`

**Path Parameters:**
- `id` - Invoice/Bill ID (or Subscription ID for backward compatibility)

**Example Request:**
```javascript
const response = await fetch('/api/v1/intl/billing/invoices/1', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

**Response (200 OK):**
```json
{
  "id": 1,
  "bill_id": 1,
  "subscription_id": 5,
  "invoice_number": "INV-00000001",
  "plan_name": "Pro Plan",
  "amount": 30.00,
  "currency": "ETB",
  "status": "paid",
  "payment_status": "succeeded",
  "due_date": "2024-01-15T00:00:00Z",
  "paid_at": "2024-01-10T10:30:00Z",
  "period_start": "2024-01-01T00:00:00Z",
  "period_end": "2024-02-01T00:00:00Z",
  "line_items": [
    {
      "description": "Subscription to Pro Plan",
      "quantity": 1,
      "unit_price": 30.00,
      "total": 30.00
    }
  ],
  "created_at": "2024-01-01T00:00:00Z"
}
```

**Response Fields:**
- All fields from list endpoint, plus:
  - `line_items` - Array of invoice line items
    - `description` - Item description
    - `quantity` - Quantity
    - `unit_price` - Price per unit
    - `total` - Total for this line item

**Error Responses:**
- `401 Unauthorized` - Invalid or missing authentication token
- `404 Not Found` - Invoice not found or doesn't belong to user

---

### 3. Download Invoice PDF

Download an invoice as a PDF document.

**Endpoint:** `GET /api/v1/{region}/billing/invoices/{id}/download`

**Path Parameters:**
- `id` - Invoice/Bill ID (or Subscription ID for backward compatibility)

**Example Request:**
```javascript
// Using fetch
const response = await fetch('/api/v1/intl/billing/invoices/1/download', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const blob = await response.blob();
const url = window.URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = url;
a.download = 'invoice-INV-00000001.pdf';
a.click();
```

**Response (200 OK):**
- Content-Type: `application/pdf`
- Content-Disposition: `attachment; filename="invoice-INV-XXXXXXXX.pdf"`
- PDF file stream

**Error Responses:**
- `401 Unauthorized` - Invalid or missing authentication token
- `404 Not Found` - Invoice not found or doesn't belong to user

---

## Frontend Implementation Examples

### React Example

```jsx
import { useState, useEffect } from 'react';

function InvoiceList() {
  const [invoices, setInvoices] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);

  useEffect(() => {
    fetchInvoices(currentPage);
  }, [currentPage]);

  const fetchInvoices = async (page) => {
    setLoading(true);
    try {
      const response = await fetch(
        `/api/v1/intl/billing/invoices?page=${page}&per_page=10`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      const data = await response.json();
      setInvoices(data.invoices);
      setMeta(data.meta);
    } catch (error) {
      console.error('Failed to fetch invoices:', error);
    } finally {
      setLoading(false);
    }
  };

  const downloadInvoice = async (invoiceId) => {
    try {
      const response = await fetch(
        `/api/v1/intl/billing/invoices/${invoiceId}/download`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `invoice-${invoiceId}.pdf`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Failed to download invoice:', error);
    }
  };

  return (
    <div>
      <h2>Invoices</h2>
      {loading ? (
        <p>Loading...</p>
      ) : (
        <>
          <table>
            <thead>
              <tr>
                <th>Invoice Number</th>
                <th>Plan</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {invoices.map((invoice) => (
                <tr key={invoice.id}>
                  <td>{invoice.invoice_number}</td>
                  <td>{invoice.plan_name}</td>
                  <td>{invoice.amount} {invoice.currency}</td>
                  <td>
                    <span className={`badge badge-${invoice.status}`}>
                      {invoice.status}
                    </span>
                  </td>
                  <td>{new Date(invoice.created_at).toLocaleDateString()}</td>
                  <td>
                    <button onClick={() => downloadInvoice(invoice.id)}>
                      Download PDF
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          
          {meta && meta.last_page > 1 && (
            <div className="pagination">
              <button
                disabled={currentPage === 1}
                onClick={() => setCurrentPage(currentPage - 1)}
              >
                Previous
              </button>
              <span>
                Page {meta.current_page} of {meta.last_page}
              </span>
              <button
                disabled={currentPage === meta.last_page}
                onClick={() => setCurrentPage(currentPage + 1)}
              >
                Next
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
```

### Vue.js Example

```vue
<template>
  <div>
    <h2>Invoices</h2>
    <table v-if="!loading">
      <thead>
        <tr>
          <th>Invoice Number</th>
          <th>Plan</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="invoice in invoices" :key="invoice.id">
          <td>{{ invoice.invoice_number }}</td>
          <td>{{ invoice.plan_name }}</td>
          <td>{{ invoice.amount }} {{ invoice.currency }}</td>
          <td>
            <span :class="`badge badge-${invoice.status}`">
              {{ invoice.status }}
            </span>
          </td>
          <td>{{ formatDate(invoice.created_at) }}</td>
          <td>
            <button @click="downloadInvoice(invoice.id)">
              Download PDF
            </button>
          </td>
        </tr>
      </tbody>
    </table>
    
    <div v-if="meta && meta.last_page > 1" class="pagination">
      <button
        :disabled="currentPage === 1"
        @click="currentPage--"
      >
        Previous
      </button>
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button
        :disabled="currentPage === meta.last_page"
        @click="currentPage++"
      >
        Next
      </button>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      invoices: [],
      meta: null,
      loading: true,
      currentPage: 1
    };
  },
  watch: {
    currentPage() {
      this.fetchInvoices();
    }
  },
  mounted() {
    this.fetchInvoices();
  },
  methods: {
    async fetchInvoices() {
      this.loading = true;
      try {
        const response = await fetch(
          `/api/v1/intl/billing/invoices?page=${this.currentPage}&per_page=10`,
          {
            headers: {
              'Authorization': `Bearer ${localStorage.getItem('token')}`
            }
          }
        );
        const data = await response.json();
        this.invoices = data.invoices;
        this.meta = data.meta;
      } catch (error) {
        console.error('Failed to fetch invoices:', error);
      } finally {
        this.loading = false;
      }
    },
    async downloadInvoice(invoiceId) {
      try {
        const response = await fetch(
          `/api/v1/intl/billing/invoices/${invoiceId}/download`,
          {
            headers: {
              'Authorization': `Bearer ${localStorage.getItem('token')}`
            }
          }
        );
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `invoice-${invoiceId}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
      } catch (error) {
        console.error('Failed to download invoice:', error);
      }
    },
    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString();
    }
  }
};
</script>
```

---

## Status Values

### Invoice Status
- `paid` - Invoice has been paid
- `pending` - Invoice is pending payment
- `cancelled` - Invoice has been cancelled
- `overdue` - Invoice is past due date

### Payment Status
- `succeeded` - Payment was successful
- `pending` - Payment is pending
- `failed` - Payment failed
- `unknown` - Payment status is unknown (no payment record found)

---

## Notes

1. **Backward Compatibility**: The API supports both Bill-based invoices (preferred) and Subscription-based invoices (fallback). If no bills exist, it falls back to subscriptions.

2. **Pagination**: The list endpoint supports pagination with a default of 10 items per page. Maximum is 100 items per page.

3. **Region Filtering**: Invoices are automatically filtered by the user's region based on the URL path (`local` or `intl`).

4. **PDF Generation**: PDFs are generated on-demand using DomPDF. The PDF includes all invoice details, line items, and formatting.

5. **Invoice Numbers**: Invoice numbers follow the format `INV-XXXXXXXX` where X is the invoice/bill ID padded to 8 digits.

---

## Error Handling

Always check the response status and handle errors appropriately:

```javascript
const response = await fetch('/api/v1/intl/billing/invoices', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

if (!response.ok) {
  if (response.status === 401) {
    // Handle authentication error
    // Redirect to login or refresh token
  } else if (response.status === 404) {
    // Handle not found
  } else {
    // Handle other errors
    const error = await response.json();
    console.error('Error:', error.message);
  }
  return;
}

const data = await response.json();
// Process data...
```

---

## Rate Limiting

Invoice endpoints are subject to standard API rate limiting. If you encounter rate limit errors (429), implement exponential backoff in your retry logic.

---

## Support

For issues or questions about the Invoice API, please contact support or refer to the main API documentation.


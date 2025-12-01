# Bill Management Frontend Integration Guide

This document outlines what needs to be updated in the frontend to support the new bill management system for subscription renewals.

## Overview

The bill management system allows users to:
- View pending bills that need payment
- Pay bills manually (for Chapa manual renewals and Stripe payment failures)
- Track bill history and status

## API Endpoints

### Base URL
- Local: `https://chat.akmicroservice.com/api/v1/local`
- International: `https://chat.akmicroservice.com/api/v1/intl`

All endpoints require authentication (Bearer token).

---

## 1. Get Pending Bills

**Endpoint:** `GET /api/v1/{region}/bills/pending`

**Description:** Get all pending bills that need payment. This is the main endpoint for the Settings → Billing page.

**Request:**
```http
GET /api/v1/local/bills/pending
Authorization: Bearer {token}
```

**Response:**
```json
{
  "pending_bills": [
    {
      "id": 1,
      "type": "renewal",
      "status": "pending",
      "amount": 30.00,
      "currency": "ETB",
      "due_date": "2025-12-03T00:00:00Z",
      "description": "Renewal for Gold Plan",
      "plan_name": "Gold Plan",
      "is_overdue": false,
      "can_pay": true,
      "days_until_due": 3,
      "created_at": "2025-11-30T14:00:00Z"
    },
    {
      "id": 2,
      "type": "renewal_failed",
      "status": "pending",
      "amount": 50.00,
      "currency": "USD",
      "due_date": "2025-12-01T00:00:00Z",
      "description": "Payment failed for Premium Plan. Please pay manually to continue.",
      "plan_name": "Premium Plan",
      "is_overdue": false,
      "can_pay": true,
      "days_until_due": 1,
      "created_at": "2025-11-30T10:00:00Z"
    }
  ]
}
```

**Bill Types:**
- `renewal` - Regular subscription renewal (Chapa manual renewal)
- `renewal_failed` - Automatic payment failed (Stripe fallback)

**Status Values:**
- `pending` - Bill needs payment
- `paid` - Bill has been paid
- `overdue` - Bill is past due date
- `cancelled` - Bill was cancelled

---

## 2. List All Bills

**Endpoint:** `GET /api/v1/{region}/bills`

**Description:** Get all bills (pending, paid, overdue) with optional status filter.

**Request:**
```http
GET /api/v1/local/bills?status=pending
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` (optional): Filter by status (`pending`, `paid`, `overdue`, `cancelled`)

**Response:**
```json
{
  "bills": [
    {
      "id": 1,
      "type": "renewal",
      "status": "pending",
      "amount": 30.00,
      "currency": "ETB",
      "due_date": "2025-12-03T00:00:00Z",
      "paid_at": null,
      "description": "Renewal for Gold Plan",
      "plan_name": "Gold Plan",
      "is_overdue": false,
      "can_pay": true,
      "created_at": "2025-11-30T14:00:00Z"
    },
    {
      "id": 3,
      "type": "renewal",
      "status": "paid",
      "amount": 30.00,
      "currency": "ETB",
      "due_date": "2025-11-28T00:00:00Z",
      "paid_at": "2025-11-28T10:30:00Z",
      "description": "Renewal for Gold Plan",
      "plan_name": "Gold Plan",
      "is_overdue": false,
      "can_pay": false,
      "created_at": "2025-11-25T14:00:00Z"
    }
  ]
}
```

---

## 3. Get Single Bill Details

**Endpoint:** `GET /api/v1/{region}/bills/{id}`

**Description:** Get detailed information about a specific bill.

**Request:**
```http
GET /api/v1/local/bills/1
Authorization: Bearer {token}
```

**Response:**
```json
{
  "bill": {
    "id": 1,
    "type": "renewal",
    "status": "pending",
    "amount": 30.00,
    "currency": "ETB",
    "due_date": "2025-12-03T00:00:00Z",
    "paid_at": null,
    "description": "Renewal for Gold Plan",
    "plan_name": "Gold Plan",
    "subscription_id": 40,
    "payment_id": null,
    "is_overdue": false,
    "can_pay": true,
    "metadata": {
      "plan_id": 3,
      "plan_name": "Gold Plan",
      "payment_method": "chapa",
      "billing_cycle": "monthly"
    },
    "created_at": "2025-11-30T14:00:00Z",
    "updated_at": "2025-11-30T14:00:00Z"
  }
}
```

---

## 4. Pay a Bill

**Endpoint:** `POST /api/v1/{region}/bills/{id}/pay`

**Description:** Initiate payment for a bill. Returns checkout URL to redirect user to payment gateway.

**Request:**
```http
POST /api/v1/local/bills/1/pay
Authorization: Bearer {token}
Content-Type: application/json

{
  "payment_method": "chapa"  // optional: override default payment method
}
```

**Response:**
```json
{
  "message": "Payment initiated successfully",
  "payment_id": 456,
  "payment_reference": "chapa_ref_123456789",
  "checkout_url": "https://checkout.chapa.co/checkout/payment/chapa_ref_123456789",
  "bill_id": 1
}
```

**Error Responses:**

**422 - Bill cannot be paid:**
```json
{
  "message": "This bill is overdue. Please contact support."
}
```

**422 - Invalid payment method:**
```json
{
  "message": "Invalid payment method."
}
```

**500 - Payment initiation failed:**
```json
{
  "message": "Failed to initiate payment: {error_message}"
}
```

---

## Frontend Implementation Guide

### 1. Settings → Billing Page Updates

#### Add Pending Bills Section

```typescript
// Example React/TypeScript implementation

interface Bill {
  id: number;
  type: 'renewal' | 'renewal_failed';
  status: 'pending' | 'paid' | 'overdue' | 'cancelled';
  amount: number;
  currency: string;
  due_date: string;
  description: string;
  plan_name: string;
  is_overdue: boolean;
  can_pay: boolean;
  days_until_due?: number;
  paid_at?: string;
}

// Fetch pending bills
const fetchPendingBills = async () => {
  const response = await fetch(`${API_BASE_URL}/bills/pending`, {
    headers: {
      'Authorization': `Bearer ${token}`,
    },
  });
  const data = await response.json();
  return data.pending_bills;
};

// Component
const PendingBillsSection = () => {
  const [bills, setBills] = useState<Bill[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchPendingBills().then(setBills).finally(() => setLoading(false));
  }, []);

  if (loading) return <LoadingSpinner />;
  if (bills.length === 0) return null;

  return (
    <div className="pending-bills-section">
      <h2>Pending Bills</h2>
      {bills.map(bill => (
        <BillCard key={bill.id} bill={bill} />
      ))}
    </div>
  );
};
```

#### Bill Card Component

```typescript
const BillCard = ({ bill }: { bill: Bill }) => {
  const handlePayNow = async () => {
    try {
      const response = await fetch(`${API_BASE_URL}/bills/${bill.id}/pay`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      
      if (data.checkout_url) {
        // Redirect to payment gateway
        window.location.href = data.checkout_url;
      }
    } catch (error) {
      console.error('Failed to initiate payment:', error);
      // Show error message to user
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
  };

  const getStatusBadge = () => {
    if (bill.is_overdue) {
      return <span className="badge badge-danger">Overdue</span>;
    }
    if (bill.status === 'pending') {
      return <span className="badge badge-warning">Pending</span>;
    }
    if (bill.status === 'paid') {
      return <span className="badge badge-success">Paid</span>;
    }
    return null;
  };

  return (
    <div className={`bill-card ${bill.is_overdue ? 'overdue' : ''}`}>
      <div className="bill-header">
        <h3>{bill.plan_name}</h3>
        {getStatusBadge()}
      </div>
      
      <div className="bill-details">
        <p className="description">{bill.description}</p>
        <p className="amount">
          {bill.currency} {bill.amount.toFixed(2)}
        </p>
        <p className="due-date">
          Due: {formatDate(bill.due_date)}
          {bill.days_until_due !== undefined && (
            <span className="days-until">
              ({bill.days_until_due} days remaining)
            </span>
          )}
        </p>
      </div>

      {bill.can_pay && (
        <button 
          onClick={handlePayNow}
          className="btn btn-primary"
          disabled={bill.is_overdue}
        >
          Pay Now
        </button>
      )}

      {bill.is_overdue && (
        <p className="error-message">
          This bill is overdue. Please contact support.
        </p>
      )}
    </div>
  );
};
```

---

### 2. Bill History Page

```typescript
const BillHistoryPage = () => {
  const [bills, setBills] = useState<Bill[]>([]);
  const [filter, setFilter] = useState<string>('all');

  useEffect(() => {
    const url = filter === 'all' 
      ? `${API_BASE_URL}/bills`
      : `${API_BASE_URL}/bills?status=${filter}`;
    
    fetch(url, {
      headers: { 'Authorization': `Bearer ${token}` },
    })
      .then(res => res.json())
      .then(data => setBills(data.bills));
  }, [filter]);

  return (
    <div className="bill-history">
      <h1>Bill History</h1>
      
      <div className="filters">
        <button onClick={() => setFilter('all')}>All</button>
        <button onClick={() => setFilter('pending')}>Pending</button>
        <button onClick={() => setFilter('paid')}>Paid</button>
        <button onClick={() => setFilter('overdue')}>Overdue</button>
      </div>

      <div className="bills-list">
        {bills.map(bill => (
          <BillHistoryCard key={bill.id} bill={bill} />
        ))}
      </div>
    </div>
  );
};
```

---

### 3. Payment Success Handling

After user completes payment and is redirected back:

```typescript
// In payment success page
useEffect(() => {
  const checkPaymentStatus = async () => {
    // Get payment reference from URL params
    const params = new URLSearchParams(window.location.search);
    const txRef = params.get('tx_ref');
    
    if (txRef) {
      // Verify payment status
      const response = await fetch(`${API_BASE_URL}/payments/verify/${txRef}`, {
        headers: { 'Authorization': `Bearer ${token}` },
      });
      
      const data = await response.json();
      
      if (data.status === 'success') {
        // Payment successful
        // Refresh pending bills to remove paid bill
        // Show success message
        showSuccessMessage('Payment successful! Your subscription has been renewed.');
      } else {
        // Payment failed
        showErrorMessage('Payment failed. Please try again.');
      }
    }
  };

  checkPaymentStatus();
}, []);
```

---

## UI/UX Recommendations

### 1. Pending Bills Display

**Location:** Settings → Billing → Pending Bills section (at the top)

**Visual Design:**
- Use warning/alert styling for pending bills
- Show count badge: "3 Pending Bills"
- Highlight overdue bills in red
- Show days until due prominently

**Example Layout:**
```
┌─────────────────────────────────────┐
│  Pending Bills (3)                  │
├─────────────────────────────────────┤
│  ⚠️ Gold Plan Renewal               │
│  ETB 30.00                          │
│  Due: Dec 3, 2025 (3 days)          │
│  [Pay Now]                          │
├─────────────────────────────────────┤
│  ❌ Premium Plan - Payment Failed   │
│  USD 50.00                          │
│  Due: Dec 1, 2025 (1 day)           │
│  [Pay Now]                          │
└─────────────────────────────────────┘
```

### 2. Bill Status Indicators

- **Pending:** Yellow/orange badge, "Pending" text
- **Overdue:** Red badge, "Overdue" text, warning icon
- **Paid:** Green badge, "Paid" text, checkmark icon
- **Cancelled:** Gray badge, "Cancelled" text

### 3. Payment Flow

1. User clicks "Pay Now" button
2. Show loading spinner
3. Redirect to `checkout_url` from API response
4. User completes payment on gateway
5. Redirect back to success page
6. Verify payment status
7. Refresh pending bills list
8. Show success/error message

### 4. Error Handling

**Common Errors:**
- `422` - Bill cannot be paid (overdue or invalid state)
- `404` - Bill not found
- `500` - Payment initiation failed

**User-Friendly Messages:**
```typescript
const getErrorMessage = (error: any) => {
  if (error.status === 422) {
    return error.message || 'This bill cannot be paid. Please contact support.';
  }
  if (error.status === 404) {
    return 'Bill not found.';
  }
  if (error.status === 500) {
    return 'Payment system error. Please try again later.';
  }
  return 'An error occurred. Please try again.';
};
```

---

## Integration Checklist

### Backend Integration
- [x] Bills table created
- [x] Bill API endpoints implemented
- [x] Payment webhook handlers updated
- [x] Subscription renewal service updated

### Frontend Integration
- [ ] Add pending bills section to Settings → Billing page
- [ ] Create BillCard component
- [ ] Create BillHistoryPage component
- [ ] Implement "Pay Now" button functionality
- [ ] Handle payment success/failure redirects
- [ ] Add bill status indicators
- [ ] Add error handling and user feedback
- [ ] Add loading states
- [ ] Test with Chapa payments
- [ ] Test with Stripe payments
- [ ] Test payment failure scenarios

---

## Example API Calls

### TypeScript/JavaScript Examples

```typescript
// API Client
class BillAPI {
  private baseURL: string;
  private token: string;

  constructor(baseURL: string, token: string) {
    this.baseURL = baseURL;
    this.token = token;
  }

  async getPendingBills(): Promise<Bill[]> {
    const response = await fetch(`${this.baseURL}/bills/pending`, {
      headers: {
        'Authorization': `Bearer ${this.token}`,
      },
    });
    const data = await response.json();
    return data.pending_bills;
  }

  async getAllBills(status?: string): Promise<Bill[]> {
    const url = status 
      ? `${this.baseURL}/bills?status=${status}`
      : `${this.baseURL}/bills`;
    
    const response = await fetch(url, {
      headers: {
        'Authorization': `Bearer ${this.token}`,
      },
    });
    const data = await response.json();
    return data.bills;
  }

  async getBill(id: number): Promise<Bill> {
    const response = await fetch(`${this.baseURL}/bills/${id}`, {
      headers: {
        'Authorization': `Bearer ${this.token}`,
      },
    });
    const data = await response.json();
    return data.bill;
  }

  async payBill(id: number, paymentMethod?: string): Promise<{ checkout_url: string }> {
    const response = await fetch(`${this.baseURL}/bills/${id}/pay`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        payment_method: paymentMethod,
      }),
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Failed to initiate payment');
    }

    const data = await response.json();
    return data;
  }
}

// Usage
const billAPI = new BillAPI('https://chat.akmicroservice.com/api/v1/local', userToken);

// Get pending bills
const pendingBills = await billAPI.getPendingBills();

// Pay a bill
try {
  const { checkout_url } = await billAPI.payBill(1);
  window.location.href = checkout_url;
} catch (error) {
  console.error('Payment initiation failed:', error);
}
```

---

## Testing Scenarios

### 1. Chapa Manual Renewal
1. Subscription expires in 3 days
2. Check Settings → Billing → Should show pending bill
3. Click "Pay Now"
4. Redirect to Chapa checkout
5. Complete payment
6. Redirect back → Bill should be marked as paid
7. Subscription should be renewed

### 2. Stripe Payment Failure
1. Stripe automatic payment fails
2. Check Settings → Billing → Should show pending bill with type "renewal_failed"
3. Click "Pay Now"
4. Redirect to Stripe checkout
5. Complete payment
6. Redirect back → Bill should be marked as paid
7. Subscription should be renewed

### 3. Overdue Bill
1. Bill passes due date
2. Check Settings → Billing → Bill should show as "overdue"
3. "Pay Now" button should be disabled or show error
4. Display message: "This bill is overdue. Please contact support."

### 4. Multiple Pending Bills
1. User has multiple pending bills
2. All should be displayed in list
3. Each should have its own "Pay Now" button
4. User can pay them individually

---

## Notes

1. **Auto-refresh:** Consider polling or WebSocket updates to refresh pending bills list when bills are paid
2. **Notifications:** Show browser notifications when new bills are created
3. **Email Notifications:** Backend sends email notifications (already implemented)
4. **Grace Period:** Bills created from payment failures have a 3-day grace period before downgrade
5. **Currency Display:** Format currency based on user's region (ETB for local, USD for intl)

---

## Support

For issues or questions:
- Check API response error messages
- Verify authentication token is valid
- Ensure region matches user's subscription region
- Check browser console for detailed error logs


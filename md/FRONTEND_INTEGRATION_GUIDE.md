# Frontend Integration Guide

Complete guide for integrating bill management, payment flows, and subscription status in the frontend.

---

## Table of Contents

1. [Display Pending Bills](#display-pending-bills)
2. [Handle Payment Flows](#handle-payment-flows)
3. [Show Subscription Status](#show-subscription-status)
4. [Complete React Components](#complete-react-components)
5. [API Integration Examples](#api-integration-examples)

---

## Display Pending Bills

### API Endpoint

```typescript
GET /api/v1/{region}/bills/pending
Authorization: Bearer {token}
```

### Response Structure

```typescript
interface PendingBill {
  id: number;
  type: 'renewal' | 'renewal_failed';
  status: 'pending';
  amount: number;
  currency: string;
  due_date: string; // ISO 8601
  description: string;
  plan_name: string;
  is_overdue: boolean;
  can_pay: boolean;
  days_until_due: number;
  created_at: string;
}
```

### React Component Example

```typescript
import React, { useState, useEffect } from 'react';
import { useAuth } from './hooks/useAuth';

interface PendingBill {
  id: number;
  type: 'renewal' | 'renewal_failed';
  status: 'pending';
  amount: number;
  currency: string;
  due_date: string;
  description: string;
  plan_name: string;
  is_overdue: boolean;
  can_pay: boolean;
  days_until_due: number;
  created_at: string;
}

const PendingBillsSection: React.FC = () => {
  const { token, region } = useAuth();
  const [bills, setBills] = useState<PendingBill[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchPendingBills();
  }, []);

  const fetchPendingBills = async () => {
    try {
      setLoading(true);
      const response = await fetch(
        `${process.env.REACT_APP_API_URL}/api/v1/${region}/bills/pending`,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        }
      );

      if (!response.ok) {
        throw new Error('Failed to fetch pending bills');
      }

      const data = await response.json();
      setBills(data.pending_bills || []);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <div className="loading-spinner">Loading bills...</div>;
  }

  if (error) {
    return <div className="error-message">Error: {error}</div>;
  }

  if (bills.length === 0) {
    return null; // No pending bills
  }

  return (
    <div className="pending-bills-section">
      <div className="section-header">
        <h2>Pending Bills</h2>
        <span className="badge badge-warning">{bills.length}</span>
      </div>

      <div className="bills-list">
        {bills.map((bill) => (
          <BillCard key={bill.id} bill={bill} onPaymentSuccess={fetchPendingBills} />
        ))}
      </div>
    </div>
  );
};

export default PendingBillsSection;
```

### Bill Card Component

```typescript
import React, { useState } from 'react';
import { useAuth } from './hooks/useAuth';

interface BillCardProps {
  bill: PendingBill;
  onPaymentSuccess: () => void;
}

const BillCard: React.FC<BillCardProps> = ({ bill, onPaymentSuccess }) => {
  const { token, region } = useAuth();
  const [paying, setPaying] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const formatCurrency = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  const getStatusBadge = () => {
    if (bill.is_overdue) {
      return <span className="badge badge-danger">Overdue</span>;
    }
    if (bill.days_until_due <= 1) {
      return <span className="badge badge-warning">Due Soon</span>;
    }
    return <span className="badge badge-info">Pending</span>;
  };

  const handlePayNow = async () => {
    try {
      setPaying(true);
      setError(null);

      const response = await fetch(
        `${process.env.REACT_APP_API_URL}/api/v1/${region}/bills/${bill.id}/pay`,
        {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        }
      );

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to initiate payment');
      }

      const data = await response.json();

      if (data.checkout_url) {
        // Redirect to payment gateway
        window.location.href = data.checkout_url;
      } else {
        throw new Error('No checkout URL received');
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Payment initiation failed');
    } finally {
      setPaying(false);
    }
  };

  return (
    <div className={`bill-card ${bill.is_overdue ? 'overdue' : ''}`}>
      <div className="bill-header">
        <div className="bill-title">
          <h3>{bill.plan_name}</h3>
          {getStatusBadge()}
        </div>
        <div className="bill-amount">
          {formatCurrency(bill.amount, bill.currency)}
        </div>
      </div>

      <div className="bill-details">
        <p className="bill-description">{bill.description}</p>
        <div className="bill-meta">
          <span className="bill-due-date">
            <i className="icon-calendar"></i>
            Due: {formatDate(bill.due_date)}
          </span>
          {bill.days_until_due >= 0 && (
            <span className="bill-days-remaining">
              {bill.days_until_due === 0
                ? 'Due today'
                : `${bill.days_until_due} days remaining`}
            </span>
          )}
        </div>
      </div>

      {error && (
        <div className="error-message">{error}</div>
      )}

      {bill.can_pay && !bill.is_overdue && (
        <button
          className="btn btn-primary btn-block"
          onClick={handlePayNow}
          disabled={paying}
        >
          {paying ? 'Processing...' : 'Pay Now'}
        </button>
      )}

      {bill.is_overdue && (
        <div className="overdue-message">
          <i className="icon-warning"></i>
          This bill is overdue. Please contact support.
        </div>
      )}
    </div>
  );
};

export default BillCard;
```

---

## Handle Payment Flows

### Payment Success Handler

```typescript
import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from './hooks/useAuth';

const PaymentSuccessPage: React.FC = () => {
  const { token, region } = useAuth();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [verifying, setVerifying] = useState(true);
  const [status, setStatus] = useState<'success' | 'failed' | 'pending'>('pending');

  useEffect(() => {
    verifyPayment();
  }, []);

  const verifyPayment = async () => {
    try {
      const txRef = searchParams.get('tx_ref') || searchParams.get('session_id');
      
      if (!txRef) {
        setStatus('failed');
        setVerifying(false);
        return;
      }

      // Verify payment status
      const response = await fetch(
        `${process.env.REACT_APP_API_URL}/api/v1/${region}/payments/verify/${txRef}`,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
          },
        }
      );

      if (!response.ok) {
        throw new Error('Payment verification failed');
      }

      const data = await response.json();

      if (data.status === 'success') {
        setStatus('success');
        // Refresh pending bills
        // Redirect to billing page after 3 seconds
        setTimeout(() => {
          navigate('/settings/billing');
        }, 3000);
      } else {
        setStatus('failed');
      }
    } catch (err) {
      setStatus('failed');
    } finally {
      setVerifying(false);
    }
  };

  if (verifying) {
    return (
      <div className="payment-verification">
        <div className="spinner"></div>
        <p>Verifying payment...</p>
      </div>
    );
  }

  return (
    <div className="payment-result">
      {status === 'success' && (
        <>
          <div className="success-icon">✓</div>
          <h2>Payment Successful!</h2>
          <p>Your subscription has been renewed.</p>
          <p>Redirecting to billing page...</p>
        </>
      )}

      {status === 'failed' && (
        <>
          <div className="error-icon">✗</div>
          <h2>Payment Failed</h2>
          <p>Please try again or contact support.</p>
          <button onClick={() => navigate('/settings/billing')}>
            Go to Billing
          </button>
        </>
      )}
    </div>
  );
};

export default PaymentSuccessPage;
```

### Payment Flow Hook

```typescript
import { useState } from 'react';
import { useAuth } from './hooks/useAuth';

interface PaymentResult {
  checkout_url: string;
  payment_id: number;
  payment_reference: string;
}

export const useBillPayment = () => {
  const { token, region } = useAuth();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const payBill = async (billId: number): Promise<PaymentResult | null> => {
    try {
      setLoading(true);
      setError(null);

      const response = await fetch(
        `${process.env.REACT_APP_API_URL}/api/v1/${region}/bills/${billId}/pay`,
        {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        }
      );

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to initiate payment');
      }

      const data = await response.json();

      if (!data.checkout_url) {
        throw new Error('No checkout URL received');
      }

      return {
        checkout_url: data.checkout_url,
        payment_id: data.payment_id,
        payment_reference: data.payment_reference,
      };
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Payment initiation failed');
      return null;
    } finally {
      setLoading(false);
    }
  };

  return { payBill, loading, error };
};
```

---

## Show Subscription Status

### Subscription Status Component

```typescript
import React, { useState, useEffect } from 'react';
import { useAuth } from './hooks/useAuth';

interface Subscription {
  id: number;
  plan: {
    id: number;
    name: string;
    monthly_price: number;
    currency: string;
    max_tokens: number;
    daily_message_limit: number;
  };
  start_date: string;
  end_date: string;
  is_active: boolean;
  auto_renew: boolean;
  tokens_used: number;
}

interface UsageStats {
  tokens_used: number;
  tokens_remaining: number;
  messages_used_today: number;
  messages_limit_today: number;
  percentage_used: number;
}

const SubscriptionStatus: React.FC = () => {
  const { token, region } = useAuth();
  const [subscription, setSubscription] = useState<Subscription | null>(null);
  const [usage, setUsage] = useState<UsageStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchSubscriptionStatus();
    const interval = setInterval(fetchSubscriptionStatus, 60000); // Refresh every minute
    return () => clearInterval(interval);
  }, []);

  const fetchSubscriptionStatus = async () => {
    try {
      // Fetch subscription
      const subResponse = await fetch(
        `${process.env.REACT_APP_API_URL}/api/v1/${region}/subscription`,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
          },
        }
      );

      if (subResponse.ok) {
        const subData = await subResponse.json();
        setSubscription(subData.subscription);
      }

      // Fetch usage stats
      const usageResponse = await fetch(
        `${process.env.REACT_APP_API_URL}/api/v1/${region}/token-usage/quota-status`,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
          },
        }
      );

      if (usageResponse.ok) {
        const usageData = await usageResponse.json();
        setUsage(usageData);
      }
    } catch (err) {
      console.error('Failed to fetch subscription status:', err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <div className="loading-spinner">Loading subscription...</div>;
  }

  if (!subscription) {
    return (
      <div className="no-subscription">
        <p>No active subscription</p>
        <button onClick={() => window.location.href = '/plans'}>
          View Plans
        </button>
      </div>
    );
  }

  const daysRemaining = Math.ceil(
    (new Date(subscription.end_date).getTime() - Date.now()) / (1000 * 60 * 60 * 24)
  );

  const renewalStatus = subscription.auto_renew
    ? 'Auto-renewal enabled'
    : 'Auto-renewal disabled';

  return (
    <div className="subscription-status">
      <div className="subscription-header">
        <h2>Current Subscription</h2>
        <span className={`status-badge ${subscription.is_active ? 'active' : 'inactive'}`}>
          {subscription.is_active ? 'Active' : 'Inactive'}
        </span>
      </div>

      <div className="subscription-details">
        <div className="plan-info">
          <h3>{subscription.plan.name}</h3>
          <p className="plan-price">
            {subscription.plan.monthly_price > 0
              ? `${subscription.plan.monthly_price} ${subscription.plan.currency}/month`
              : 'Free'}
          </p>
        </div>

        <div className="subscription-dates">
          <div className="date-item">
            <span className="label">Started:</span>
            <span className="value">
              {new Date(subscription.start_date).toLocaleDateString()}
            </span>
          </div>
          <div className="date-item">
            <span className="label">Expires:</span>
            <span className="value">
              {new Date(subscription.end_date).toLocaleDateString()}
            </span>
          </div>
          <div className="date-item">
            <span className="label">Days Remaining:</span>
            <span className={`value ${daysRemaining <= 3 ? 'warning' : ''}`}>
              {daysRemaining} days
            </span>
          </div>
        </div>

        <div className="renewal-status">
          <i className={`icon-${subscription.auto_renew ? 'check' : 'close'}`}></i>
          <span>{renewalStatus}</span>
        </div>
      </div>

      {usage && (
        <div className="usage-stats">
          <h3>Usage Statistics</h3>
          
          <div className="usage-item">
            <div className="usage-label">Tokens</div>
            <div className="usage-bar">
              <div
                className="usage-fill"
                style={{ width: `${usage.percentage_used}%` }}
              ></div>
            </div>
            <div className="usage-text">
              {usage.tokens_used.toLocaleString()} / {subscription.plan.max_tokens.toLocaleString()}
            </div>
          </div>

          <div className="usage-item">
            <div className="usage-label">Messages Today</div>
            <div className="usage-text">
              {usage.messages_used_today} / {usage.messages_limit_today}
            </div>
          </div>
        </div>
      )}

      {daysRemaining <= 3 && subscription.is_active && (
        <div className="renewal-warning">
          <i className="icon-warning"></i>
          <p>Your subscription expires in {daysRemaining} days.</p>
          <p>Check pending bills to renew.</p>
        </div>
      )}
    </div>
  );
};

export default SubscriptionStatus;
```

---

## Complete React Components

### Billing Page Component

```typescript
import React from 'react';
import PendingBillsSection from './PendingBillsSection';
import SubscriptionStatus from './SubscriptionStatus';
import BillHistory from './BillHistory';

const BillingPage: React.FC = () => {
  return (
    <div className="billing-page">
      <div className="page-header">
        <h1>Billing & Subscription</h1>
      </div>

      <div className="billing-content">
        {/* Subscription Status */}
        <section className="subscription-section">
          <SubscriptionStatus />
        </section>

        {/* Pending Bills */}
        <section className="pending-bills-section">
          <PendingBillsSection />
        </section>

        {/* Bill History */}
        <section className="bill-history-section">
          <BillHistory />
        </section>
      </div>
    </div>
  );
};

export default BillingPage;
```

### Bill History Component

```typescript
import React, { useState, useEffect } from 'react';
import { useAuth } from './hooks/useAuth';

interface Bill {
  id: number;
  type: string;
  status: 'pending' | 'paid' | 'overdue' | 'cancelled';
  amount: number;
  currency: string;
  due_date: string;
  paid_at: string | null;
  description: string;
  plan_name: string;
  created_at: string;
}

const BillHistory: React.FC = () => {
  const { token, region } = useAuth();
  const [bills, setBills] = useState<Bill[]>([]);
  const [filter, setFilter] = useState<string>('all');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchBills();
  }, [filter]);

  const fetchBills = async () => {
    try {
      setLoading(true);
      const url = filter === 'all'
        ? `${process.env.REACT_APP_API_URL}/api/v1/${region}/bills`
        : `${process.env.REACT_APP_API_URL}/api/v1/${region}/bills?status=${filter}`;

      const response = await fetch(url, {
        headers: {
          'Authorization': `Bearer ${token}`,
        },
      });

      if (response.ok) {
        const data = await response.json();
        setBills(data.bills || []);
      }
    } catch (err) {
      console.error('Failed to fetch bills:', err);
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadge = (status: string) => {
    const badges = {
      pending: 'badge-warning',
      paid: 'badge-success',
      overdue: 'badge-danger',
      cancelled: 'badge-secondary',
    };
    return <span className={`badge ${badges[status] || ''}`}>{status}</span>;
  };

  return (
    <div className="bill-history">
      <div className="section-header">
        <h2>Bill History</h2>
        <div className="filters">
          <button
            className={filter === 'all' ? 'active' : ''}
            onClick={() => setFilter('all')}
          >
            All
          </button>
          <button
            className={filter === 'pending' ? 'active' : ''}
            onClick={() => setFilter('pending')}
          >
            Pending
          </button>
          <button
            className={filter === 'paid' ? 'active' : ''}
            onClick={() => setFilter('paid')}
          >
            Paid
          </button>
        </div>
      </div>

      {loading ? (
        <div className="loading">Loading bills...</div>
      ) : bills.length === 0 ? (
        <div className="no-bills">No bills found</div>
      ) : (
        <table className="bills-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {bills.map((bill) => (
              <tr key={bill.id}>
                <td>{new Date(bill.created_at).toLocaleDateString()}</td>
                <td>{bill.description}</td>
                <td>
                  {new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: bill.currency,
                  }).format(bill.amount)}
                </td>
                <td>{getStatusBadge(bill.status)}</td>
                <td>
                  {bill.status === 'pending' && (
                    <button
                      className="btn btn-sm btn-primary"
                      onClick={() => handlePayBill(bill.id)}
                    >
                      Pay
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default BillHistory;
```

---

## API Integration Examples

### API Client Class

```typescript
class BillingAPI {
  private baseURL: string;
  private token: string;
  private region: string;

  constructor(baseURL: string, token: string, region: string) {
    this.baseURL = baseURL;
    this.token = token;
    this.region = region;
  }

  private async request(endpoint: string, options: RequestInit = {}) {
    const url = `${this.baseURL}/api/v1/${this.region}${endpoint}`;
    const response = await fetch(url, {
      ...options,
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Content-Type': 'application/json',
        ...options.headers,
      },
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'API request failed');
    }

    return response.json();
  }

  // Bills
  async getPendingBills() {
    return this.request('/bills/pending');
  }

  async getAllBills(status?: string) {
    const endpoint = status ? `/bills?status=${status}` : '/bills';
    return this.request(endpoint);
  }

  async getBill(id: number) {
    return this.request(`/bills/${id}`);
  }

  async payBill(id: number) {
    return this.request(`/bills/${id}/pay`, {
      method: 'POST',
    });
  }

  // Subscription
  async getCurrentSubscription() {
    return this.request('/subscription');
  }

  async getUsageStats() {
    return this.request('/token-usage/quota-status');
  }

  // Payment
  async verifyPayment(reference: string) {
    return this.request(`/payments/verify/${reference}`);
  }
}

export default BillingAPI;
```

---

## CSS Styling Examples

```css
/* Pending Bills Section */
.pending-bills-section {
  margin-bottom: 2rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.bills-list {
  display: grid;
  gap: 1rem;
}

.bill-card {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 1.5rem;
  background: white;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.bill-card.overdue {
  border-color: #dc3545;
  background: #fff5f5;
}

.bill-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.bill-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.bill-amount {
  font-size: 1.5rem;
  font-weight: bold;
  color: #333;
}

.badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 500;
}

.badge-warning {
  background: #fff3cd;
  color: #856404;
}

.badge-danger {
  background: #f8d7da;
  color: #721c24;
}

.badge-success {
  background: #d4edda;
  color: #155724;
}

.badge-info {
  background: #d1ecf1;
  color: #0c5460;
}

/* Subscription Status */
.subscription-status {
  background: white;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}

.status-badge.active {
  background: #d4edda;
  color: #155724;
}

.status-badge.inactive {
  background: #f8d7da;
  color: #721c24;
}

.usage-bar {
  width: 100%;
  height: 8px;
  background: #e0e0e0;
  border-radius: 4px;
  overflow: hidden;
  margin: 0.5rem 0;
}

.usage-fill {
  height: 100%;
  background: linear-gradient(90deg, #4caf50, #8bc34a);
  transition: width 0.3s ease;
}

.renewal-warning {
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 4px;
  padding: 1rem;
  margin-top: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
```

---

## Complete Integration Checklist

- [ ] Install dependencies (React, React Router, etc.)
- [ ] Set up API client with authentication
- [ ] Create `PendingBillsSection` component
- [ ] Create `BillCard` component
- [ ] Create `SubscriptionStatus` component
- [ ] Create `BillHistory` component
- [ ] Create `PaymentSuccessPage` component
- [ ] Set up routing for billing pages
- [ ] Add CSS styling
- [ ] Test with real API endpoints
- [ ] Handle error states
- [ ] Add loading states
- [ ] Test payment flows
- [ ] Test subscription status updates

---

## Next Steps

1. Copy the components to your React project
2. Install required dependencies
3. Configure API base URL in environment variables
4. Set up authentication context
5. Test with your API endpoints
6. Customize styling to match your design system

Good luck with the integration! 🚀


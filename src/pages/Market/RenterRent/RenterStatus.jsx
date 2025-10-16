import React, { useEffect, useState } from "react";
import { useParams, Link } from "react-router-dom";
import "./RenterStatus.css";

export default function RenterStatus() {
  // Change from renter_id to id to match the route parameter
  const { id } = useParams();
  const [renter, setRenter] = useState(null);
  const [payments, setPayments] = useState([]);
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(true);

  const fetchRenterDetails = async () => {
    try {
      setIsLoading(true);
      setError("");
      console.log("🔍 Fetching for renter ID:", id);

      if (!id) {
        throw new Error("No renter ID provided");
      }

      const API_URL = `http://localhost/revenue/backend/Market/RenterRent/renter_rent_details.php?renter_id=${id}`;
      console.log("🔍 API URL:", API_URL);

      const response = await fetch(API_URL);
      
      console.log("📡 Response status:", response.status);
      
      if (!response.ok) {
        throw new Error(`HTTP Error: ${response.status}`);
      }

      const data = await response.json();
      console.log("📊 API Response:", data);
      
      if (data.success && data.data && data.data.renter) {
        console.log("✅ Renter found:", data.data.renter);
        setRenter(data.data.renter);
        setPayments(data.data.payments || []);
      } else {
        setError(data.message || "Renter not found");
      }
    } catch (err) {
      console.error("💥 Fetch error:", err);
      setError("Unable to load renter details: " + err.message);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    console.log("🎯 useEffect with id:", id);
    if (id) {
      fetchRenterDetails();
    } else {
      setError("No renter ID provided");
      setIsLoading(false);
    }
  }, [id]);

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP'
    }).format(parseFloat(amount || 0));
  };

  const formatDate = (dateString) => {
    if (!dateString) return "N/A";
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  };

  const getPaymentStatusBadge = (status) => {
    switch (status?.toLowerCase()) {
      case "paid":
        return "payment-status-paid";
      case "pending":
        return "payment-status-pending";
      case "overdue":
        return "payment-status-overdue";
      default:
        return "payment-status-pending";
    }
  };

  // Calculate financial summary
  const pendingPayments = payments.filter(p => p.status === 'pending' || p.status === 'overdue');
  const paidPayments = payments.filter(p => p.status === 'paid');
  const totalPending = pendingPayments.reduce((sum, payment) => sum + parseFloat(payment.amount || 0) + parseFloat(payment.late_fee || 0), 0);
  const totalPaid = paidPayments.reduce((sum, payment) => sum + parseFloat(payment.amount || 0) + parseFloat(payment.late_fee || 0), 0);

  // Get next payment due
  const nextPayment = pendingPayments.length > 0 ? pendingPayments[0] : null;

  if (isLoading) {
    return (
      <div className="renter-status-container">
        <div className="loading-container">
          <div className="loading-spinner"></div>
          <p className="loading-text">Loading renter details...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="renter-status-container">
        <div className="error-container">
          <div className="error-icon">⚠️</div>
          <h2 className="error-title">Error Loading Renter Details</h2>
          <p className="error-message">{error}</p>
          <button
            onClick={fetchRenterDetails}
            className="retry-button"
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  if (!renter) {
    return (
      <div className="renter-status-container">
        <div className="not-found-container">
          <h2 className="not-found-title">Renter Not Found</h2>
          <p className="not-found-message">The requested renter does not exist.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="renter-status-container">
      {/* Header */}
      <div className="header-section">
        <div className="header-content">
          <h1 className="page-title">Renter Details</h1>
          <p className="renter-id">Renter ID: {renter.renter_id}</p>
        </div>
        <Link
          to="/Market/RenterRent"
          className="back-button"
        >
          ← Back to Renters
        </Link>
      </div>

      <div className="content-grid">
        {/* Renter Information Card */}
        <div className="renter-info-card">
          <h2 className="card-title">Renter Information</h2>
          <div className="info-grid">
            <div className="info-item">
              <label className="info-label">Full Name</label>
              <p className="info-value-large">{renter.full_name}</p>
            </div>
            <div className="info-item">
              <label className="info-label">Business Name</label>
              <p className="info-value-large">{renter.business_name}</p>
            </div>
            <div className="info-item">
              <label className="info-label">Contact Number</label>
              <p className="info-value">{renter.contact_number}</p>
            </div>
            <div className="info-item">
              <label className="info-label">Email</label>
              <p className="info-value">{renter.email}</p>
            </div>
            <div className="info-item">
              <label className="info-label">Market Location</label>
              <p className="info-value-emphasis">{renter.market_name}</p>
            </div>
            <div className="info-item">
              <label className="info-label">Stall Number</label>
              <p className="info-value-emphasis">{renter.stall_number}</p>
            </div>
            <div className="info-item">
              <label className="info-label">Section</label>
              <p className="info-value">{renter.section_name || 'N/A'}</p>
            </div>
            <div className="info-item">
              <label className="info-label">Class</label>
              <p className="info-value-emphasis">Class {renter.class_name}</p>
            </div>
          </div>
        </div>

        {/* Financial Summary Card */}
        <div className="financial-card">
          <h2 className="card-title">Financial Summary</h2>
          <div className="financial-content">
            {/* Monthly Rent - Highlighted */}
            <div className="monthly-rent-highlight">
              <div className="rent-header">
                <span className="rent-label">Monthly Rent:</span>
                <span className="rent-amount">
                  {formatCurrency(renter.monthly_rent)}
                </span>
              </div>
              <p className="rent-description">Base rental amount per month</p>
            </div>

            <div className="financial-item">
              <span className="financial-label">Stall Rights Fee:</span>
              <span className="financial-value">{formatCurrency(renter.stall_rights_fee)}</span>
            </div>
            <div className="financial-item">
              <span className="financial-label">Security Bond:</span>
              <span className="financial-value">{formatCurrency(renter.security_bond)}</span>
            </div>
            
            {/* Next Payment Due */}
            {nextPayment && (
              <div className="next-payment-section">
                <div className="financial-item">
                  <span className="financial-label">Next Payment Due:</span>
                  <span className="financial-value">{formatCurrency(nextPayment.amount)}</span>
                </div>
                <div className="financial-item">
                  <span className="financial-label">Due Date:</span>
                  <span className="financial-value">{formatDate(nextPayment.due_date)}</span>
                </div>
              </div>
            )}

            <div className="payment-summary">
              <div className="financial-item">
                <span className="financial-label">Pending Payments:</span>
                <span className="financial-value">{pendingPayments.length} month(s)</span>
              </div>
              <div className="financial-item">
                <span className="financial-label">Paid Payments:</span>
                <span className="financial-value">{paidPayments.length} month(s)</span>
              </div>
            </div>

            {totalPending > 0 && (
              <div className="total-due-section">
                <div className="financial-item-total">
                  <span className="total-label">Total Due:</span>
                  <span className="total-amount">{formatCurrency(totalPending)}</span>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Payment History */}
      <div className="payment-history-card">
        <h2 className="card-title">Payment History</h2>
        
        {payments.length === 0 ? (
          <div className="no-payments">
            No payment records found.
          </div>
        ) : (
          <div className="table-container">
            <table className="payments-table">
              <thead>
                <tr className="table-header">
                  <th className="table-head">Month</th>
                  <th className="table-head">Due Date</th>
                  <th className="table-head">Amount</th>
                  <th className="table-head">Late Fee</th>
                  <th className="table-head">Status</th>
                  <th className="table-head">Paid Date</th>
                  <th className="table-head">Reference</th>
                </tr>
              </thead>
              <tbody>
                {payments.map((payment) => (
                  <tr key={payment.id} className="table-row">
                    <td className="table-cell">
                      {new Date(payment.month_year + '-01').toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                    </td>
                    <td className="table-cell">{formatDate(payment.due_date)}</td>
                    <td className="table-cell">{formatCurrency(payment.amount)}</td>
                    <td className="table-cell">{formatCurrency(payment.late_fee || 0)}</td>
                    <td className="table-cell">
                      <span className={`status-badge ${getPaymentStatusBadge(payment.status)}`}>
                        {payment.status?.toUpperCase() || 'PENDING'}
                      </span>
                    </td>
                    <td className="table-cell">
                      {payment.paid_date ? formatDate(payment.paid_date) : 'N/A'}
                    </td>
                    <td className="table-cell reference">
                      {payment.reference_number || 'N/A'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
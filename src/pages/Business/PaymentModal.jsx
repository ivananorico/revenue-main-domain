import React, { useState, useEffect } from 'react';
import './styles.css';

/**
 * Payment Modal Component
 * Handles marking assessments as paid
 */
const PaymentModal = ({ isOpen, onClose, assessment, onPayment }) => {
  const [formData, setFormData] = useState({
    amount_paid: '',
    payment_method: 'Cash',
    or_number: '',
    notes: ''
  });
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    console.log('PaymentModal assessment:', assessment);
    if (assessment) {
      setFormData({
        amount_paid: assessment.total_due || '',
        payment_method: 'Cash',
        or_number: '',
        notes: ''
      });
    }
  }, [assessment]);

  const handleInputChange = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const paymentData = {
        assessment_id: assessment?.assessment_id,
        amount_paid: parseFloat(formData.amount_paid),
        payment_method: formData.payment_method,
        or_number: formData.or_number,
        notes: formData.notes
      };

      await onPayment(paymentData);
      onClose();
    } catch (error) {
      console.error('Error processing payment:', error);
      alert('Error processing payment. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h2 className="modal-title">Record Payment</h2>
          <button className="modal-close" onClick={onClose}>×</button>
        </div>

        <div className="modal-body">
          {/* Assessment Information */}
          <div className="business-card" style={{ marginBottom: '1.5rem' }}>
            <h3 style={{ color: 'var(--blue-light)', marginBottom: '1rem' }}>Assessment Details</h3>
            <div className="grid grid-cols-2">
              <div>
                <p><strong>Assessment ID:</strong> {assessment?.assessment_id || 'N/A'}</p>
                <p><strong>Year:</strong> {assessment?.year || new Date().getFullYear()}</p>
                <p><strong>Business:</strong> {assessment?.business_name || 'N/A'}</p>
              </div>
              <div>
                <p><strong>Total Due:</strong> ₱{(assessment?.total_due || 0).toLocaleString()}</p>
                <p><strong>Status:</strong> 
                  <span className={`status-badge status-${(assessment?.status || 'assessed').toLowerCase()}`} style={{ marginLeft: '0.5rem' }}>
                    {assessment?.status || 'assessed'}
                  </span>
                </p>
                <p><strong>Due Date:</strong> -</p>
              </div>
            </div>
          </div>

          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label">Amount Paid</label>
              <input
                type="number"
                className="form-input"
                value={formData.amount_paid}
                onChange={(e) => handleInputChange('amount_paid', e.target.value)}
                placeholder="Enter amount paid"
                step="0.01"
                required
              />
            </div>

            <div className="form-group">
              <label className="form-label">Payment Method</label>
              <select
                className="form-select"
                value={formData.payment_method}
                onChange={(e) => handleInputChange('payment_method', e.target.value)}
                required
              >
                <option value="Cash">Cash</option>
                <option value="Check">Check</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Online">Online</option>
              </select>
            </div>

            <div className="form-group">
              <label className="form-label">OR Number</label>
              <input
                type="text"
                className="form-input"
                value={formData.or_number}
                onChange={(e) => handleInputChange('or_number', e.target.value)}
                placeholder="Enter official receipt number"
                required
              />
            </div>

            <div className="form-group">
              <label className="form-label">Notes (Optional)</label>
              <textarea
                className="form-textarea"
                value={formData.notes}
                onChange={(e) => handleInputChange('notes', e.target.value)}
                placeholder="Additional payment notes..."
                rows="3"
              />
            </div>

            {/* Payment Summary */}
            <div className="business-card">
              <h3 style={{ color: 'var(--blue-light)', marginBottom: '1rem' }}>Payment Summary</h3>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                <span>Total Due:</span>
                <span>₱{parseFloat(assessment.total_due).toLocaleString()}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                <span>Amount Paid:</span>
                <span>₱{parseFloat(formData.amount_paid || 0).toLocaleString()}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '2px solid var(--blue-dark)', paddingTop: '0.5rem', fontWeight: 'bold' }}>
                <span>Remaining Balance:</span>
                <span>₱{(parseFloat(assessment.total_due) - parseFloat(formData.amount_paid || 0)).toLocaleString()}</span>
              </div>
            </div>
          </form>
        </div>

        <div className="modal-footer">
          <button type="button" className="btn btn-secondary" onClick={onClose}>
            Cancel
          </button>
          <button 
            type="submit" 
            className="btn btn-success" 
            onClick={handleSubmit}
            disabled={loading}
          >
            {loading ? 'Processing...' : 'Record Payment'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default PaymentModal;

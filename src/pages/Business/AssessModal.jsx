import React, { useState, useEffect } from 'react';
import './styles.css';

/**
 * Assessment Modal Component
 * Handles business assessment creation and editing
 */
const AssessModal = ({ isOpen, onClose, business, onSave, taxRates, fees }) => {
  const [formData, setFormData] = useState({
    gross_sales: '',
    tax_rate: '',
    discounts: 0,
    penalties: 0,
    selectedFees: {}
  });
  const [calculations, setCalculations] = useState({
    tax_amount: 0,
    fees_total: 0,
    total_due: 0
  });
  const [loading, setLoading] = useState(false);

  // Initialize form data when business changes
  useEffect(() => {
    if (business) {
      const defaultTaxRate = (taxRates[business.business?.business_type]?.rate_percent || 0) * 100;
      setFormData({
        gross_sales: business.business?.last_year_gross || '',
        tax_rate: defaultTaxRate,
        discounts: business.current_assessment?.discounts || 0,
        penalties: business.current_assessment?.penalties || 0,
        selectedFees: {}
      });
      
    }
  }, [business, taxRates]);
  

  // Calculate totals when form data changes
  useEffect(() => {
    calculateTotals();
  }, [formData, business, taxRates, fees]);

  const calculateTotals = () => {
    if (!business || !taxRates || !fees) return;

    const grossSales = parseFloat(formData.gross_sales) || 0;
    const taxRate = parseFloat(formData.tax_rate) || 0;
    const taxAmount = grossSales * (taxRate / 100);
    
    let feesTotal = 0;
    Object.values(formData.selectedFees).forEach(amount => {
      feesTotal += parseFloat(amount) || 0;
    });

    const discounts = parseFloat(formData.discounts) || 0;
    const penalties = parseFloat(formData.penalties) || 0;
    const totalDue = taxAmount + feesTotal + penalties - discounts;

    setCalculations({
      tax_amount: taxAmount,
      fees_total: feesTotal,
      total_due: totalDue
    });
  };

  const handleInputChange = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleFeeChange = (feeId, amount) => {
    setFormData(prev => ({
      ...prev,
      selectedFees: {
        ...prev.selectedFees,
        [feeId]: amount
      }
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const assessmentData = {
        business_id: business.business?.business_id || business.business_id,
        year: new Date().getFullYear(),
        gross_sales: parseFloat(formData.gross_sales),
        tax_amount: calculations.tax_amount,
        fees: Object.entries(formData.selectedFees)
          .filter(([_, amount]) => amount > 0)
          .map(([feeId, amount]) => ({
            fee_id: parseInt(feeId),
            amount: parseFloat(amount),
            fee_name: fees.all_fees?.find(f => f.id == feeId)?.fee_name || ''
          })),
        discounts: parseFloat(formData.discounts),
        penalties: parseFloat(formData.penalties),
        assessor_id: 1 // TODO: Get from session
      };

      await onSave(assessmentData);
      onClose();
    } catch (error) {
      console.error('Error saving assessment:', error);
      alert('Error saving assessment. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const generateBill = () => {
    if (business.current_assessment) {
      window.open(`http://localhost/revenue/api/business/generate_bill_pdf.php?assessment_id=${business.current_assessment.assessment_id}`, '_blank');
    }
  };

  if (!isOpen || !business) return null;

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <div>
            <h2 className="modal-title">Business Assessment</h2>
            <p className="business-card-subtitle">{business.business_name}</p>
          </div>
          <button className="modal-close" onClick={onClose}>×</button>
        </div>

        <div className="modal-body">
          {/* Business Information */}
          <div className="business-card" style={{ marginBottom: '1.5rem' }}>
            <h3 style={{ color: 'var(--blue-light)', marginBottom: '1rem' }}>Business Information</h3>
            <div className="grid grid-cols-2">
              <div>
                <p><strong>Owner:</strong> {business.business?.full_name || 'N/A'}</p>
                <p><strong>TIN:</strong> {business.business?.tin || 'N/A'}</p>
                <p><strong>Type:</strong> {business.business?.business_type || 'N/A'}</p>
              </div>
              <div>
                <p><strong>Barangay:</strong> {business.business?.barangay || 'N/A'}</p>
                <p><strong>Address:</strong> {business.business?.address || 'N/A'}</p>
                <p><strong>Last Year Sales:</strong> ₱{(business.business?.last_year_gross || 0).toLocaleString()}</p>
              </div>
            </div>
          </div>

          <form onSubmit={handleSubmit}>
            {/* Assessment Form */}
            <div className="grid grid-cols-2">
              <div className="form-group">
                <label className="form-label">Gross Sales (Current Year)</label>
                <input
                  type="number"
                  className="form-input"
                  value={formData.gross_sales}
                  onChange={(e) => handleInputChange('gross_sales', e.target.value)}
                  placeholder="Enter gross sales amount"
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Business Tax Rate (%)</label>
                <input
                  type="number"
                  className="form-input"
                  value={formData.tax_rate}
                  onChange={(e) => handleInputChange('tax_rate', e.target.value)}
                  placeholder="Enter tax rate percentage"
                  step="0.01"
                  min="0"
                  max="100"
                  required
                />
              </div>
            </div>

            {/* Regulatory Fees */}
            <div className="form-group">
              <label className="form-label">Regulatory Fees</label>
              <div className="business-card">
                {fees.fees_by_department && Object.keys(fees.fees_by_department).length > 0 ? (
                  Object.entries(fees.fees_by_department).map(([department, departmentFees]) => (
                    <div key={department} style={{ marginBottom: '1rem' }}>
                      <h4 style={{ color: 'var(--blue-light)', marginBottom: '0.5rem' }}>{department}</h4>
                      {departmentFees.map(fee => (
                        <div key={fee.fee_id} style={{ display: 'flex', alignItems: 'center', marginBottom: '0.5rem' }}>
                          <input
                            type="checkbox"
                            checked={formData.selectedFees[fee.fee_id] > 0}
                            onChange={(e) => handleFeeChange(fee.fee_id, e.target.checked ? fee.fee_amount : 0)}
                            style={{ marginRight: '0.5rem' }}
                          />
                          <span style={{ flex: 1 }}>{fee.fee_name}</span>
                          <span style={{ color: 'var(--text-muted)' }}>₱{fee.fee_amount.toLocaleString()}</span>
                        </div>
                      ))}
                    </div>
                  ))
                ) : fees.all_fees && fees.all_fees.length > 0 ? (
                  fees.all_fees.map(fee => (
                    <div key={fee.id} style={{ display: 'flex', alignItems: 'center', marginBottom: '0.5rem' }}>
                      <input
                        type="checkbox"
                        checked={formData.selectedFees[fee.id] > 0}
                        onChange={(e) => handleFeeChange(fee.id, e.target.checked ? fee.amount : 0)}
                        style={{ marginRight: '0.5rem' }}
                      />
                      <span style={{ flex: 1 }}>{fee.fee_name}</span>
                      <span style={{ color: 'var(--text-muted)' }}>₱{fee.amount.toLocaleString()}</span>
                    </div>
                  ))
                ) : (
                  <p style={{ color: 'var(--text-muted)', textAlign: 'center', padding: '1rem' }}>
                    No regulatory fees available
                  </p>
                )}
              </div>
            </div>

            {/* Discounts and Penalties */}
            <div className="grid grid-cols-2">
              <div className="form-group">
                <label className="form-label">Discounts</label>
                <input
                  type="number"
                  className="form-input"
                  value={formData.discounts}
                  onChange={(e) => handleInputChange('discounts', e.target.value)}
                  placeholder="0.00"
                />
              </div>

              <div className="form-group">
                <label className="form-label">Penalties</label>
                <input
                  type="number"
                  className="form-input"
                  value={formData.penalties}
                  onChange={(e) => handleInputChange('penalties', e.target.value)}
                  placeholder="0.00"
                />
              </div>
            </div>

            {/* Calculation Summary */}
            <div className="business-card">
              <h3 style={{ color: 'var(--blue-light)', marginBottom: '1rem' }}>Assessment Summary</h3>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                <span>Business Tax:</span>
                <span>₱{calculations.tax_amount.toLocaleString()}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                <span>Regulatory Fees:</span>
                <span>₱{calculations.fees_total.toLocaleString()}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                <span>Penalties:</span>
                <span>₱{formData.penalties}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                <span>Discounts:</span>
                <span>-₱{formData.discounts}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '2px solid var(--blue-dark)', paddingTop: '0.5rem', fontWeight: 'bold', fontSize: '1.125rem' }}>
                <span>Total Due:</span>
                <span>₱{calculations.total_due.toLocaleString()}</span>
              </div>
            </div>
          </form>
        </div>

        <div className="modal-footer">
          <button type="button" className="btn btn-secondary" onClick={onClose}>
            Cancel
          </button>
          {business.current_assessment && (
            <button type="button" className="btn btn-primary" onClick={generateBill}>
              Generate Bill
            </button>
          )}
          <button 
            type="submit" 
            className="btn btn-primary" 
            onClick={handleSubmit}
            disabled={loading}
          >
            {loading ? 'Saving...' : 'Save Assessment'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default AssessModal;

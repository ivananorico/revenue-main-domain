import React, { useState, useEffect } from 'react';
import AssessModal from './AssessModal';
import AssessmentHistory from './AssessmentHistory';
import PaymentModal from './PaymentModal';
import './styles.css';

/**
 * Business1 Component - Business Tax & Regulatory Assessment
 * Main assessment interface with business queue and assessment modal
 */
export default function Business1() {
  const [businesses, setBusinesses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedBusiness, setSelectedBusiness] = useState(null);
  const [showAssessModal, setShowAssessModal] = useState(false);
  const [showHistoryModal, setShowHistoryModal] = useState(false);
  const [showPaymentModal, setShowPaymentModal] = useState(false);
  const [taxRates, setTaxRates] = useState({});
  const [fees, setFees] = useState({});
  const [currentFees, setCurrentFees] = useState({});
  
  // Filters
  const [filters, setFilters] = useState({
    search: '',
    status: '',
    barangay: '',
    type: ''
  });
  const [availableFilters, setAvailableFilters] = useState({
    barangays: [],
    types: [],
    statuses: ['active', 'inactive', 'revoked']
  });
  
  // Pagination
  const [pagination, setPagination] = useState({
    current_page: 1,
    per_page: 10,
    total_records: 0,
    total_pages: 0
  });

  // Load initial data
  useEffect(() => {
    loadTaxRates();
    loadFees();
    loadBusinesses();
  }, []);

  // Load businesses when filters or pagination change
  useEffect(() => {
    loadBusinesses();
  }, [filters, pagination.current_page]);

  const loadTaxRates = async () => {
    try {
      const response = await fetch('http://localhost/revenue/api/business/tax_rates.php');
      const data = await response.json();
      if (data.success) {
        setTaxRates(data.data);
      }
    } catch (err) {
      console.error('Error loading tax rates:', err);
    }
  };

  const loadFees = async () => {
    try {
      const response = await fetch('http://localhost/revenue/api/business/fees.php');
      const data = await response.json();
      if (data.success) {
        setFees(data.data);
      }
    } catch (err) {
      console.error('Error loading fees:', err);
    }
  };

  const loadBusinesses = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams({
        page: pagination.current_page,
        per_page: pagination.per_page,
        ...filters
      });

      const response = await fetch(`http://localhost/revenue/api/business/list_businesses.php?${params}`);
      const data = await response.json();
      
      if (data.success) {
        setBusinesses(data.data.businesses);
        setPagination(data.data.pagination);
        setAvailableFilters(data.data.filters);
      } else {
        setError(data.message);
      }
    } catch (err) {
      setError('Failed to load businesses');
      console.error('Error loading businesses:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (field, value) => {
    setFilters(prev => ({
      ...prev,
      [field]: value
    }));
    setPagination(prev => ({
      ...prev,
      current_page: 1
    }));
  };

  const handlePageChange = (page) => {
    setPagination(prev => ({
      ...prev,
      current_page: page
    }));
  };

  const handleBusinessSelect = async (business) => {
    try {
      // Load full business details
      const response = await fetch(`http://localhost/revenue/api/business/business_details.php?business_id=${business.business_id}`);
      const data = await response.json();
      
      if (data.success) {
        setSelectedBusiness(data.data);
        setShowAssessModal(true);
      } else {
        alert('Error loading business details');
      }
    } catch (err) {
      console.error('Error loading business details:', err);
      alert('Error loading business details');
    }
  };

  const handleFeesUpdate = (newFees) => {
    setCurrentFees(newFees);
  };

  const handleSaveAssessment = async (assessmentData) => {
    try {
      const response = await fetch('http://localhost/revenue/api/business/save_assessment.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(assessmentData)
      });

      const data = await response.json();
      if (data.success) {
        alert('Assessment saved successfully!');
        loadBusinesses(); // Refresh the list
      } else {
        throw new Error(data.message);
      }
    } catch (err) {
      console.error('Error saving assessment:', err);
      throw err;
    }
  };

  const handlePayment = async (paymentData) => {
    try {
      const response = await fetch('http://localhost/revenue/api/business/mark_paid.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(paymentData)
      });

      const data = await response.json();
      if (data.success) {
        alert('Payment recorded successfully!');
        loadBusinesses(); // Refresh the list
      } else {
        throw new Error(data.message);
      }
    } catch (err) {
      console.error('Error processing payment:', err);
      throw err;
    }
  };

  const exportCSV = () => {
    const params = new URLSearchParams({
      type: 'assessments',
      ...filters
    });
    window.open(`http://localhost/revenue/api/business/export_csv.php?${params}`, '_blank');
  };

  const formatCurrency = (amount) => {
    return `₱${parseFloat(amount || 0).toLocaleString()}`;
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString();
  };

  return (
    <div className="business-module">
      <div className="business-card">
        <div className="business-card-header">
          <div>
            <h1 className="business-card-title">Business Tax & Regulatory Assessment</h1>
            <p className="business-card-subtitle">Manage business assessments and tax collection</p>
          </div>
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button className="btn btn-secondary" onClick={() => setShowHistoryModal(true)}>
              History
            </button>
            <button className="btn btn-primary" onClick={loadBusinesses}>
              Refresh
            </button>
          </div>
        </div>

        {/* Filters */}
        <div className="filters-section">
          <div className="filters-grid">
            <div className="form-group">
              <label className="form-label">Search</label>
              <input
                type="text"
                className="form-input"
                placeholder="Search businesses..."
                value={filters.search}
                onChange={(e) => handleFilterChange('search', e.target.value)}
              />
            </div>

            <div className="form-group">
              <label className="form-label">Status</label>
              <select
                className="form-select"
                value={filters.status}
                onChange={(e) => handleFilterChange('status', e.target.value)}
              >
                <option value="">All Statuses</option>
                {availableFilters.statuses.map(status => (
                  <option key={status} value={status}>
                    {status === 'no_assessment' ? 'No Assessment' : 
                     status === 'pending' ? 'Pending Assessment' :
                     status === 'assessed' ? 'Assessed (Unpaid)' :
                     status === 'overdue' ? 'Overdue' : status}
                  </option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label className="form-label">Barangay</label>
              <select
                className="form-select"
                value={filters.barangay}
                onChange={(e) => handleFilterChange('barangay', e.target.value)}
              >
                <option value="">All Barangays</option>
                {availableFilters.barangays.map(barangay => (
                  <option key={barangay} value={barangay}>{barangay}</option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label className="form-label">Business Type</label>
              <select
                className="form-select"
                value={filters.type}
                onChange={(e) => handleFilterChange('type', e.target.value)}
              >
                <option value="">All Types</option>
                {availableFilters.types.map(type => (
                  <option key={type} value={type}>{type}</option>
                ))}
              </select>
            </div>
          </div>
        </div>

        {/* Business Queue Table */}
        {loading ? (
          <div className="loading">
            <div className="spinner"></div>
            Loading businesses...
          </div>
        ) : error ? (
          <div className="empty-state">
            <div className="empty-state-icon">⚠️</div>
            <h3 className="empty-state-title">Error</h3>
            <p className="empty-state-description">{error}</p>
            <button className="btn btn-primary" onClick={loadBusinesses}>
              Try Again
            </button>
          </div>
        ) : businesses.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state-icon">📋</div>
            <h3 className="empty-state-title">No Businesses Found</h3>
            <p className="empty-state-description">No businesses match your current filters.</p>
          </div>
        ) : (
          <>
            <div className="business-card">
              <table className="business-table">
                <thead>
                  <tr>
                    <th>Business Name</th>
                    <th>Owner</th>
                    <th>TIN</th>
                    <th>Type</th>
                    <th>Barangay</th>
                    <th>Business Status</th>
                    <th>Assessment Status</th>
                    <th>Last Year Gross</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {businesses.map(business => (
                    <tr key={business.business_id}>
                      <td>
                        <div>
                          <strong>{business.business_name}</strong>
                          <br />
                          <small style={{ color: 'var(--text-muted)' }}>
                            {business.address}
                          </small>
                        </div>
                      </td>
                      <td>{business.owner_name}</td>
                      <td>{business.tin}</td>
                      <td>{business.business_type}</td>
                      <td>{business.barangay}</td>
                      <td>
                        <span className={`status-badge status-${business.status.toLowerCase()}`}>
                          {business.status}
                        </span>
                      </td>
                      <td>
                        {business.assessment_status ? (
                          <span className={`status-badge status-${business.assessment_status.toLowerCase()}`}>
                            {business.assessment_status === 'assessed' ? 'Assessed (Unpaid)' : business.assessment_status}
                          </span>
                        ) : (
                          <span className="status-badge status-pending">No Assessment</span>
                        )}
                      </td>
                      <td>{business.total_due ? formatCurrency(business.total_due) : '-'}</td>
                      <td>
                        <div style={{ display: 'flex', gap: '0.25rem', flexWrap: 'wrap' }}>
                          <button
                            className="btn btn-sm btn-primary"
                            onClick={() => handleBusinessSelect(business)}
                            title="Assess"
                          >
                            📋
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {pagination.total_pages > 1 && (
              <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '1rem', marginTop: '1rem' }}>
                <button
                  className="btn btn-sm btn-secondary"
                  onClick={() => handlePageChange(pagination.current_page - 1)}
                  disabled={!pagination.has_prev}
                >
                  Previous
                </button>
                
                <span style={{ color: 'var(--text-muted)' }}>
                  Page {pagination.current_page} of {pagination.total_pages}
                  ({pagination.total_records} total records)
                </span>
                
                <button
                  className="btn btn-sm btn-secondary"
                  onClick={() => handlePageChange(pagination.current_page + 1)}
                  disabled={!pagination.has_next}
                >
                  Next
                </button>
              </div>
            )}
          </>
        )}
      </div>

      {/* Modals */}
      {showAssessModal && (
        <AssessModal
          isOpen={showAssessModal}
          onClose={() => setShowAssessModal(false)}
          business={selectedBusiness}
          onSave={handleSaveAssessment}
          taxRates={taxRates}
          fees={fees}
        />
      )}

      {showHistoryModal && (
        <AssessmentHistory
          onClose={() => setShowHistoryModal(false)}
        />
      )}

      {showPaymentModal && selectedBusiness && (
        <PaymentModal
          isOpen={showPaymentModal}
          onClose={() => setShowPaymentModal(false)}
          assessment={selectedBusiness.current_assessment || {
            assessment_id: selectedBusiness.business?.business_id || selectedBusiness.business_id,
            total_due: selectedBusiness.outstanding_balance || selectedBusiness.total_due || 0
          }}
          onPayment={handlePayment}
        />
      )}
    </div>
  );
}
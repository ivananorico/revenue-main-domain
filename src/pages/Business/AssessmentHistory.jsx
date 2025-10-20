import React, { useState, useEffect } from 'react';
import './styles.css';

/**
 * Assessment History Component
 * Shows all assessments including paid ones
 */
const AssessmentHistory = ({ onClose }) => {
  const [assessments, setAssessments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [filters, setFilters] = useState({
    search: '',
    status: '',
    barangay: '',
    type: ''
  });
  const [pagination, setPagination] = useState({
    current_page: 1,
    per_page: 10,
    total_records: 0,
    total_pages: 0
  });
  const [availableFilters, setAvailableFilters] = useState({
    barangays: [],
    types: [],
    statuses: []
  });

  useEffect(() => {
    fetchAssessmentHistory();
  }, [filters, pagination.current_page]);

  const fetchAssessmentHistory = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams({
        page: pagination.current_page,
        per_page: pagination.per_page,
        ...filters
      });

      const response = await fetch(`http://localhost/revenue/api/business/assessment_history.php?${params}`);
      const data = await response.json();
      
      if (data.success) {
        setAssessments(data.data.assessments);
        setPagination(data.data.pagination);
        setAvailableFilters(data.data.filters);
      } else {
        setError(data.message);
      }
    } catch (err) {
      setError('Failed to fetch assessment history');
      console.error('Error fetching assessment history:', err);
    } finally {
      setLoading(false);
    }
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const formatCurrency = (amount) => {
    return `₱${parseFloat(amount).toLocaleString()}`;
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

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h2 className="modal-title">Assessment History</h2>
          <button className="modal-close" onClick={onClose}>×</button>
        </div>

        <div className="modal-body">
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
                    <option key={status} value={status}>{status}</option>
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

          {/* Assessment History Table */}
          {loading ? (
            <div className="loading">
              <div className="spinner"></div>
              Loading assessment history...
            </div>
          ) : error ? (
            <div className="empty-state">
              <div className="empty-state-icon">⚠️</div>
              <h3 className="empty-state-title">Error</h3>
              <p className="empty-state-description">{error}</p>
            </div>
          ) : assessments.length === 0 ? (
            <div className="empty-state">
              <div className="empty-state-icon">📋</div>
              <h3 className="empty-state-title">No Assessments Found</h3>
              <p className="empty-state-description">No assessments match your current filters.</p>
            </div>
          ) : (
            <div className="business-card">
              <table className="business-table">
                <thead>
                  <tr>
                    <th>Business Name</th>
                    <th>Owner</th>
                    <th>Type</th>
                    <th>Barangay</th>
                    <th>Assessment Year</th>
                    <th>Gross Sales</th>
                    <th>Tax Amount</th>
                    <th>Fees Total</th>
                    <th>Total Due</th>
                    <th>Status</th>
                    <th>Assessed At</th>
                  </tr>
                </thead>
                <tbody>
                  {assessments.map(assessment => (
                    <tr key={assessment.assessment_id}>
                      <td>{assessment.business_name}</td>
                      <td>{assessment.owner_name}</td>
                      <td>{assessment.business_type}</td>
                      <td>{assessment.barangay}</td>
                      <td>{assessment.assessment_year}</td>
                      <td>{formatCurrency(assessment.gross_sales)}</td>
                      <td>{formatCurrency(assessment.tax_amount)}</td>
                      <td>{formatCurrency(assessment.fees_total)}</td>
                      <td>{formatCurrency(assessment.total_due)}</td>
                      <td>
                        <span className={`status-badge status-${assessment.assessment_status}`}>
                          {assessment.assessment_status}
                        </span>
                      </td>
                      <td>{formatDate(assessment.assessed_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>

              {/* Pagination */}
              {pagination.total_pages > 1 && (
                <div className="pagination">
                  <button
                    className="btn btn-sm btn-outline"
                    onClick={() => handlePageChange(pagination.current_page - 1)}
                    disabled={pagination.current_page <= 1}
                  >
                    Previous
                  </button>
                  <span className="pagination-info">
                    Page {pagination.current_page} of {pagination.total_pages}
                  </span>
                  <button
                    className="btn btn-sm btn-outline"
                    onClick={() => handlePageChange(pagination.current_page + 1)}
                    disabled={pagination.current_page >= pagination.total_pages}
                  >
                    Next
                  </button>
                </div>
              )}
            </div>
          )}
        </div>

        <div className="modal-footer">
          <button type="button" className="btn btn-secondary" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

export default AssessmentHistory;

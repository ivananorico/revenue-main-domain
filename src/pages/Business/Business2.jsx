
import React, { useState, useEffect } from 'react';
import './styles.css';

/**
 * Business2 Component - Business Tax & Regulatory Fee Dashboard
 * Visual analytics dashboard with charts and compliance overview
 */
export default function Business2() {
  const [dashboardData, setDashboardData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [charts, setCharts] = useState({});
  
  // Filters
  const [filters, setFilters] = useState({
    from: new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0], // Start of year
    to: new Date().toISOString().split('T')[0], // Today
    barangay: '',
    type: ''
  });

  // Load dashboard data
  useEffect(() => {
    loadDashboardData();
  }, [filters]);

  // Load Chart.js from CDN
  useEffect(() => {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    script.onload = () => {
      // Chart.js is loaded, we can now create charts
      if (dashboardData) {
        createCharts();
      }
    };
    document.head.appendChild(script);

    return () => {
      // Cleanup script on unmount
      if (document.head.contains(script)) {
        document.head.removeChild(script);
      }
    };
  }, [dashboardData]);

  const loadDashboardData = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams(filters);
      const response = await fetch(`http://localhost/revenue/api/business/dashboard_data.php?${params}`);
      const data = await response.json();
      
      if (data.success) {
        setDashboardData(data.data);
        // Create charts after data is loaded
        if (window.Chart) {
          createCharts();
        }
      } else {
        setError(data.message);
      }
    } catch (err) {
      setError('Failed to load dashboard data');
      console.error('Error loading dashboard data:', err);
    } finally {
      setLoading(false);
    }
  };

  const createCharts = () => {
    if (!dashboardData || !window.Chart) return;

    // Destroy existing charts
    Object.values(charts).forEach(chart => {
      if (chart && typeof chart.destroy === 'function') {
        chart.destroy();
      }
    });

    const newCharts = {};

    // Monthly Revenue Chart
    const monthlyCtx = document.getElementById('monthlyRevenueChart');
    if (monthlyCtx) {
      const monthlyData = dashboardData.monthly_revenue || [];
      const months = monthlyData.map(item => {
        const date = new Date(item.year, item.month - 1);
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
      });

      newCharts.monthly = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
          labels: months,
          datasets: [
            {
              label: 'Tax Revenue',
              data: monthlyData.map(item => item.tax_revenue),
              backgroundColor: 'rgba(30, 102, 208, 0.8)',
              borderColor: 'rgba(30, 102, 208, 1)',
              borderWidth: 1
            },
            {
              label: 'Fee Revenue',
              data: monthlyData.map(item => item.fee_revenue),
              backgroundColor: 'rgba(111, 179, 255, 0.8)',
              borderColor: 'rgba(111, 179, 255, 1)',
              borderWidth: 1
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              labels: {
                color: '#e2e8f0'
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                color: '#94a3b8',
                callback: function(value) {
                  return '₱' + value.toLocaleString();
                }
              },
              grid: {
                color: '#334155'
              }
            },
            x: {
              ticks: {
                color: '#94a3b8'
              },
              grid: {
                color: '#334155'
              }
            }
          }
        }
      });
    }

    // Revenue by Business Type Chart
    const typeCtx = document.getElementById('revenueByTypeChart');
    if (typeCtx) {
      const typeData = dashboardData.revenue_by_type || [];
      const colors = [
        '#0b3d91', '#1e66d0', '#6fb3ff', '#3b82f6', '#10b981', '#f59e0b', '#ef4444'
      ];

      newCharts.type = new Chart(typeCtx, {
        type: 'pie',
        data: {
          labels: typeData.map(item => item.business_type),
          datasets: [{
            data: typeData.map(item => item.total_revenue),
            backgroundColor: colors.slice(0, typeData.length),
            borderColor: colors.slice(0, typeData.length).map(color => color + '80'),
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                color: '#e2e8f0',
                padding: 20
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.parsed;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = ((value / total) * 100).toFixed(1);
                  return `${label}: ₱${value.toLocaleString()} (${percentage}%)`;
                }
              }
            }
          }
        }
      });
    }

    // Collections by Barangay Chart
    const barangayCtx = document.getElementById('collectionsByBarangayChart');
    if (barangayCtx) {
      const barangayData = dashboardData.collections_by_barangay || [];
      
      newCharts.barangay = new Chart(barangayCtx, {
        type: 'bar',
        data: {
          labels: barangayData.map(item => item.barangay),
          datasets: [{
            label: 'Total Revenue',
            data: barangayData.map(item => item.total_revenue),
            backgroundColor: 'rgba(16, 185, 129, 0.8)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              labels: {
                color: '#e2e8f0'
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                color: '#94a3b8',
                callback: function(value) {
                  return '₱' + value.toLocaleString();
                }
              },
              grid: {
                color: '#334155'
              }
            },
            x: {
              ticks: {
                color: '#94a3b8'
              },
              grid: {
                color: '#334155'
              }
            }
          }
        }
      });
    }

    setCharts(newCharts);
  };

  const handleFilterChange = (field, value) => {
    setFilters(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const exportDashboard = () => {
    const params = new URLSearchParams({
      type: 'dashboard',
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

  if (loading) {
    return (
      <div className="business-module">
        <div className="loading">
          <div className="spinner"></div>
          Loading dashboard...
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="business-module">
        <div className="empty-state">
          <div className="empty-state-icon">⚠️</div>
          <h3 className="empty-state-title">Error</h3>
          <p className="empty-state-description">{error}</p>
          <button className="btn btn-primary" onClick={loadDashboardData}>
            Try Again
          </button>
        </div>
      </div>
    );
  }

  const summary = dashboardData?.summary || {};

  return (
    <div className="business-module">
      <div className="business-card">
        <div className="business-card-header">
          <div>
            <h1 className="business-card-title">Business Tax & Regulatory Fee Dashboard</h1>
            <p className="business-card-subtitle">Visual analytics and compliance overview</p>
          </div>
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button className="btn btn-secondary" onClick={exportDashboard}>
              Export Report
            </button>
            <button className="btn btn-primary" onClick={loadDashboardData}>
              Refresh
            </button>
          </div>
        </div>

        {/* Filters */}
        <div className="filters-section">
          <div className="filters-grid">
            <div className="form-group">
              <label className="form-label">From Date</label>
              <input
                type="date"
                className="form-input"
                value={filters.from}
                onChange={(e) => handleFilterChange('from', e.target.value)}
              />
            </div>

            <div className="form-group">
              <label className="form-label">To Date</label>
              <input
                type="date"
                className="form-input"
                value={filters.to}
                onChange={(e) => handleFilterChange('to', e.target.value)}
              />
            </div>

            <div className="form-group">
              <label className="form-label">Barangay</label>
              <select
                className="form-select"
                value={filters.barangay}
                onChange={(e) => handleFilterChange('barangay', e.target.value)}
              >
                <option value="">All Barangays</option>
                {/* Add barangay options from API */}
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
                <option value="Retail">Retail</option>
                <option value="Wholesale">Wholesale</option>
                <option value="Service">Service</option>
                <option value="Manufacturing">Manufacturing</option>
                <option value="Food Service">Food Service</option>
                <option value="Professional Services">Professional Services</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>
        </div>

        {/* Summary Cards */}
        <div className="summary-cards">
          <div className="summary-card">
            <div className="summary-card-header">
              <div className="summary-card-icon primary">💰</div>
              <div>
                <h3 className="summary-card-title">Total Tax Collected</h3>
                <p className="summary-card-value">{formatCurrency(summary.total_tax_collected)}</p>
              </div>
            </div>
          </div>

          <div className="summary-card">
            <div className="summary-card-header">
              <div className="summary-card-icon success">📋</div>
              <div>
                <h3 className="summary-card-title">Total Fees Collected</h3>
                <p className="summary-card-value">{formatCurrency(summary.total_fees_collected)}</p>
              </div>
            </div>
          </div>

          <div className="summary-card">
            <div className="summary-card-header">
              <div className="summary-card-icon warning">⏳</div>
              <div>
                <h3 className="summary-card-title">Pending Assessments</h3>
                <p className="summary-card-value">{summary.pending_assessments}</p>
              </div>
            </div>
          </div>

          <div className="summary-card">
            <div className="summary-card-header">
              <div className="summary-card-icon danger">🚨</div>
              <div>
                <h3 className="summary-card-title">Overdue Assessments</h3>
                <p className="summary-card-value">{summary.overdue_assessments}</p>
              </div>
            </div>
          </div>

          <div className="summary-card">
            <div className="summary-card-header">
              <div className="summary-card-icon primary">🏢</div>
              <div>
                <h3 className="summary-card-title">Active Businesses</h3>
                <p className="summary-card-value">{summary.active_businesses}</p>
              </div>
            </div>
          </div>

          <div className="summary-card">
            <div className="summary-card-header">
              <div className="summary-card-icon success">📈</div>
              <div>
                <h3 className="summary-card-title">New Registrations</h3>
                <p className="summary-card-value">{summary.new_registrations_quarter}</p>
                <small className="summary-card-change">This Quarter</small>
              </div>
            </div>
          </div>
        </div>

        {/* Charts */}
        <div className="grid grid-cols-2">
          <div className="chart-container">
            <div className="chart-header">
              <h3 className="chart-title">Monthly Revenue Comparison</h3>
            </div>
            <div className="chart-content">
              <canvas id="monthlyRevenueChart"></canvas>
            </div>
          </div>

          <div className="chart-container">
            <div className="chart-header">
              <h3 className="chart-title">Revenue by Business Type</h3>
            </div>
            <div className="chart-content">
              <canvas id="revenueByTypeChart"></canvas>
            </div>
          </div>
        </div>

        <div className="chart-container">
          <div className="chart-header">
            <h3 className="chart-title">Collections by Barangay</h3>
          </div>
          <div className="chart-content">
            <canvas id="collectionsByBarangayChart"></canvas>
          </div>
        </div>

        {/* Compliance Overview */}
        <div className="business-card">
          <div className="business-card-header">
            <h2 className="business-card-title">Compliance Overview</h2>
            <p className="business-card-subtitle">Delinquent businesses and expiring permits</p>
          </div>

          {dashboardData?.compliance_overview?.length > 0 ? (
            <div style={{ maxHeight: '400px', overflowY: 'auto', border: '1px solid var(--border-color)', borderRadius: '8px' }}>
              <table className="business-table" style={{ margin: 0 }}>
                <thead style={{ position: 'sticky', top: 0, backgroundColor: 'var(--card-bg)', zIndex: 1 }}>
                  <tr>
                    <th>Business Name</th>
                    <th>Barangay</th>
                    <th>Type</th>
                    <th>Assessment Status</th>
                    <th>Total Due</th>
                    <th>Days Overdue</th>
                    <th>Permit Expiry</th>
                  </tr>
                </thead>
                <tbody>
                  {dashboardData.compliance_overview.map(business => (
                    <tr key={business.business_id}>
                      <td>
                        <div>
                          <strong>{business.business_name}</strong>
                          <br />
                          <small style={{ color: 'var(--text-muted)' }}>
                            TIN: {business.tin_number}
                          </small>
                        </div>
                      </td>
                      <td>{business.barangay}</td>
                      <td>{business.business_type}</td>
                      <td>
                        {business.status ? (
                          <span className={`status-badge status-${business.status.toLowerCase()}`}>
                            {business.status}
                          </span>
                        ) : (
                          <span className="status-badge status-pending">Not Assessed</span>
                        )}
                      </td>
                      <td>{business.total_due ? formatCurrency(business.total_due) : '-'}</td>
                      <td>
                        {business.days_overdue > 0 ? (
                          <span style={{ color: 'var(--error)' }}>
                            {business.days_overdue} days
                          </span>
                        ) : (
                          '-'
                        )}
                      </td>
                      <td>
                        {business.permit_expiry ? (
                          <span style={{ 
                            color: new Date(business.permit_expiry) < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000) 
                              ? 'var(--warning)' 
                              : 'var(--text-secondary)' 
                          }}>
                            {formatDate(business.permit_expiry)}
                          </span>
                        ) : (
                          '-'
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="empty-state">
              <div className="empty-state-icon">✅</div>
              <h3 className="empty-state-title">All Compliant</h3>
              <p className="empty-state-description">No compliance issues found for the selected period.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

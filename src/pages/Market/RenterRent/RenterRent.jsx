import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import "./RenterRent.css";

const RenterRent = () => {
  const [renters, setRenters] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [sortField, setSortField] = useState("created_at");
  const [sortDirection, setSortDirection] = useState("desc");
  const [filters, setFilters] = useState({
    market_name: "",
    search: ""
  });

  const fetchRenters = async () => {
    try {
      setLoading(true);
      setError("");
      
      const API_URL = "http://localhost/revenue/backend/Market/RenterRent/get_all_renters.php";
      console.log("Fetching from:", API_URL);
      
      const response = await fetch(API_URL, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        },
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log("API Response:", data);

      if (data.success) {
        // Get active renters
        const activeRenters = data.renters ? data.renters.filter(renter => renter.status === 'active') : [];
        
        // Add payment data to each renter
        const rentersWithPaymentData = activeRenters.map(renter => {
          const paymentData = getNextPaymentData(renter);
          return {
            ...renter,
            id: renter.renter_id,
            ...paymentData
          };
        });
        
        console.log("Renters with payment data:", rentersWithPaymentData);
        setRenters(rentersWithPaymentData);
      } else {
        setError(data.message || "Failed to fetch renters");
      }
    } catch (err) {
      console.error("Fetch error:", err);
      setError("Network error: Unable to fetch renters - " + err.message);
    } finally {
      setLoading(false);
    }
  };

  const getNextPaymentData = (renter) => {
    const monthlyRent = parseFloat(renter.monthly_rent || 1000.00);
    
    // Get current date to determine payment status
    const today = new Date();
    const currentMonth = today.getMonth() + 1;
    const currentYear = today.getFullYear();
    
    let paymentStatus = 'pending';
    let nextPaymentAmount = monthlyRent;
    let nextPaymentDate = '';
    
    // Simple logic based on your database structure
    if (currentMonth === 10 && currentYear === 2025) {
      // October 2025 - show November payment
      nextPaymentDate = '2025-11-05';
      paymentStatus = 'pending';
    } else if (currentMonth === 11 && currentYear === 2025) {
      // November 2025 - show November payment (could be overdue)
      nextPaymentDate = '2025-11-05';
      const currentDay = today.getDate();
      paymentStatus = currentDay > 5 ? 'overdue' : 'pending';
    } else if (currentMonth === 12 && currentYear === 2025) {
      // December 2025 - show December payment
      nextPaymentDate = '2025-12-05';
      const currentDay = today.getDate();
      paymentStatus = currentDay > 5 ? 'overdue' : 'pending';
    } else {
      // Default to next month
      const nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, 5);
      nextPaymentDate = nextMonth.toISOString().split('T')[0];
      paymentStatus = 'pending';
    }
    
    return {
      payment_status: paymentStatus,
      next_payment_amount: nextPaymentAmount,
      next_payment_date: nextPaymentDate,
      monthly_rent: monthlyRent
    };
  };

  useEffect(() => {
    fetchRenters();
  }, []);

  const getStatusBadge = (status) => {
    return "status-badge status-active";
  };

  const getPaymentStatusBadge = (status) => {
    switch (status?.toLowerCase()) {
      case "paid":
        return "payment-badge payment-paid";
      case "pending":
        return "payment-badge payment-pending";
      case "overdue":
        return "payment-badge payment-overdue";
      default:
        return "payment-badge payment-pending";
    }
  };

  const formatPaymentStatus = (status) => {
    const statusMap = {
      paid: "Paid",
      pending: "Pending",
      overdue: "Overdue"
    };
    return statusMap[status] || "Pending";
  };

  const handleSort = (field) => {
    if (sortField === field) {
      setSortDirection(sortDirection === "asc" ? "desc" : "asc");
    } else {
      setSortField(field);
      setSortDirection("asc");
    }
  };

  const handleFilterChange = (filterType, value) => {
    setFilters(prev => ({
      ...prev,
      [filterType]: value
    }));
  };

  const clearFilters = () => {
    setFilters({
      market_name: "",
      search: ""
    });
  };

  const formatDate = (dateString) => {
    if (!dateString) return "N/A";
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP'
    }).format(parseFloat(amount || 0));
  };

  // Get unique values for filter dropdowns
  const uniqueMarkets = [...new Set(renters.map(renter => renter.market_name).filter(Boolean))];

  // Filter and sort renters
  const filteredAndSortedRenters = [...renters]
    .filter(renter => {
      const matchesMarket = !filters.market_name || renter.market_name === filters.market_name;
      const matchesSearch = !filters.search || 
        renter.business_name?.toLowerCase().includes(filters.search.toLowerCase()) ||
        renter.full_name?.toLowerCase().includes(filters.search.toLowerCase()) ||
        renter.first_name?.toLowerCase().includes(filters.search.toLowerCase()) ||
        renter.last_name?.toLowerCase().includes(filters.search.toLowerCase()) ||
        renter.renter_id?.toLowerCase().includes(filters.search.toLowerCase());

      return matchesMarket && matchesSearch;
    })
    .sort((a, b) => {
      let aValue = a[sortField];
      let bValue = b[sortField];

      if (sortField === "monthly_rent" || sortField === "next_payment_amount") {
        aValue = parseFloat(aValue || 0);
        bValue = parseFloat(bValue || 0);
      }

      if (sortField.includes("date") || sortField === "created_at" || sortField === "next_payment_date") {
        aValue = new Date(aValue);
        bValue = new Date(bValue);
      }

      if (aValue < bValue) return sortDirection === "asc" ? -1 : 1;
      if (aValue > bValue) return sortDirection === "asc" ? 1 : -1;
      return 0;
    });

  const getSortIndicator = (field) => {
    if (sortField !== field) return null;
    return (
      <span className="sort-indicator">
        {sortDirection === "asc" ? "↑" : "↓"}
      </span>
    );
  };

  const hasActiveFilters = Object.values(filters).some(value => value !== "");

  return (
    <div className="renters-container">
      {/* Header Section */}
      <header className="renters-header">
        <div className="header-content">
          <h1 className="renters-title">Approved Renters</h1>
          <p className="renters-subtitle">Manage and view all approved stall renters and their payment status</p>
        </div>
        <button
          onClick={fetchRenters}
          className="refresh-button"
          aria-label="Refresh renters"
        >
          <svg className="refresh-icon" viewBox="0 0 24 24" fill="none">
            <path
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
          Refresh
        </button>
      </header>

      {/* Filters Section */}
      <section className="filters-section">
        <div className="filters-header">
          <h2 className="filters-title">Filters</h2>
          {hasActiveFilters && (
            <button onClick={clearFilters} className="clear-filters-button">
              Clear All
            </button>
          )}
        </div>
        
        <div className="filters-grid">
          <div className="filter-group">
            <label htmlFor="search-filter" className="filter-label">Search</label>
            <input
              id="search-filter"
              type="text"
              placeholder="Search by renter ID, business name, or renter name..."
              value={filters.search}
              onChange={(e) => handleFilterChange("search", e.target.value)}
              className="filter-input"
            />
          </div>

          <div className="filter-group">
            <label htmlFor="market-filter" className="filter-label">Market</label>
            <select
              id="market-filter"
              value={filters.market_name}
              onChange={(e) => handleFilterChange("market_name", e.target.value)}
              className="filter-select"
            >
              <option value="">All Markets</option>
              {uniqueMarkets.map(market => (
                <option key={market} value={market}>
                  {market}
                </option>
              ))}
            </select>
          </div>
        </div>

        {hasActiveFilters && (
          <div className="active-filters">
            <span className="active-filters-label">Active filters:</span>
            {filters.market_name && (
              <span className="filter-tag">
                Market: {filters.market_name}
                <button 
                  onClick={() => handleFilterChange("market_name", "")}
                  aria-label="Remove market filter"
                >
                  ×
                </button>
              </span>
            )}
            {filters.search && (
              <span className="filter-tag">
                Search: "{filters.search}"
                <button 
                  onClick={() => handleFilterChange("search", "")}
                  aria-label="Remove search filter"
                >
                  ×
                </button>
              </span>
            )}
          </div>
        )}
      </section>

      {/* Main Content Section */}
      <main className="renters-main">
        {loading ? (
          <div className="loading-state">
            <div className="loading-spinner"></div>
            <p className="loading-text">Loading approved renters...</p>
          </div>
        ) : error ? (
          <div className="error-state">
            <div className="error-icon">⚠️</div>
            <div className="error-content">
              <h3 className="error-title">Unable to load renters</h3>
              <p className="error-message">{error}</p>
              <button onClick={fetchRenters} className="retry-button">
                Try Again
              </button>
            </div>
          </div>
        ) : renters.length === 0 ? (
          <div className="empty-state">
            <div className="empty-icon">🏪</div>
            <h3 className="empty-title">No approved renters found</h3>
            <p className="empty-message">
              There are no approved renters in the system at this time.
            </p>
          </div>
        ) : (
          <div className="table-container">
            <div className="table-responsive">
              <table className="renters-table">
                <thead className="table-header">
                  <tr>
                    <th
                      className="table-header-cell sortable"
                      onClick={() => handleSort("renter_id")}
                    >
                      <span className="header-content">
                        Renter ID {getSortIndicator("renter_id")}
                      </span>
                    </th>
                    <th
                      className="table-header-cell sortable"
                      onClick={() => handleSort("business_name")}
                    >
                      <span className="header-content">
                        Business Name {getSortIndicator("business_name")}
                      </span>
                    </th>
                    <th className="table-header-cell">Renter Name</th>
                    <th className="table-header-cell">Stall Location</th>
                    <th className="table-header-cell">Status</th>
                    <th
                      className="table-header-cell sortable"
                      onClick={() => handleSort("next_payment_amount")}
                    >
                      <span className="header-content">
                        Next Payment {getSortIndicator("next_payment_amount")}
                      </span>
                    </th>
                    <th className="table-header-cell">Payment Status</th>
                    <th className="table-header-cell actions-header">Actions</th>
                  </tr>
                </thead>
                <tbody className="table-body">
                  {filteredAndSortedRenters.map((renter, index) => (
                    <tr key={renter.id || index} className="table-row">
                      <td className="table-cell renter-id">
                        <div className="id-content">
                          {renter.renter_id}
                        </div>
                      </td>
                      <td className="table-cell business-info">
                        <div className="business-name">
                          {renter.business_name}
                        </div>
                        <div className="business-section">
                          {renter.section_name}
                        </div>
                      </td>
                      <td className="table-cell renter-info">
                        <div className="renter-name">
                          {renter.full_name}
                        </div>
                        <div className="renter-details">
                          <small>
                            {renter.first_name} {renter.middle_name ? renter.middle_name + ' ' : ''}{renter.last_name}
                          </small>
                        </div>
                        <div className="renter-contact">
                          {renter.contact_number}
                        </div>
                      </td>
                      <td className="table-cell stall-info">
                        <div className="stall-location">
                          <strong>{renter.market_name} - {renter.stall_number}</strong>
                        </div>
                        <div className="stall-class">
                          Class {renter.class_name}
                        </div>
                      </td>
                      <td className="table-cell">
                        <span className={getStatusBadge(renter.status)}>
                          Approved
                        </span>
                      </td>
                      <td className="table-cell payment-info">
                        <div className="payment-amount">
                          {formatCurrency(renter.next_payment_amount)}
                        </div>
                        <div className="payment-date">
                          Due: {formatDate(renter.next_payment_date)}
                        </div>
                      </td>
                      <td className="table-cell">
                        <span className={getPaymentStatusBadge(renter.payment_status)}>
                          {formatPaymentStatus(renter.payment_status)}
                        </span>
                      </td>
                      <td className="table-cell actions-cell">
                        <Link
                          to={`/Market/RenterStatus/${renter.renter_id}`}
                          className="view-button"
                          title="View renter details"
                        >
                          <svg
                            className="button-icon"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                          >
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                            <path
                              fillRule="evenodd"
                              d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                              clipRule="evenodd"
                            />
                          </svg>
                          View
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Table Footer */}
            <footer className="table-footer">
              <div className="table-summary">
                Showing {filteredAndSortedRenters.length} of {renters.length}{" "}
                approved renters
                {hasActiveFilters && " (filtered)"}
              </div>
            </footer>
          </div>
        )}
      </main>
    </div>
  );
};

export default RenterRent;
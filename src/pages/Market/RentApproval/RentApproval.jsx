import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import "./RentApproval.css";

const RentApproval = () => {
  const [applications, setApplications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [sortField, setSortField] = useState("application_date");
  const [sortDirection, setSortDirection] = useState("desc");
  const [filters, setFilters] = useState({
    status: "",
    application_type: "",
    market_section: "",
    search: ""
  });

  const fetchApplications = async () => {
    try {
      setLoading(true);
      setError("");
      
      const response = await fetch(
        "http://localhost/revenue/backend/Market/RentApproval/display_applications.php"
      );
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log("API Response:", data);

      if (data.success) {
        console.log("Applications data:", data.applications);
        setApplications(data.applications || []);
      } else {
        setError(data.message || "Failed to fetch applications");
      }
    } catch (err) {
      console.error("Fetch error:", err);
      setError("Network error: Unable to fetch applications - " + err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchApplications();
  }, []);

  const getStatusBadge = (status) => {
    switch (status?.toLowerCase()) {
      case "approved":
        return "status-badge status-approved";
      case "pending":
        return "status-badge status-pending";
      case "rejected":
        return "status-badge status-rejected";
      case "payment_phase":
        return "status-badge status-payment";
      case "paid":
        return "status-badge status-paid";
      case "documents_submitted":
        return "status-badge status-documents";
      case "cancelled":
        return "status-badge status-cancelled";
      default:
        return "status-badge status-unknown";
    }
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
      status: "",
      application_type: "",
      market_section: "",
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

  const formatDateTime = (dateString) => {
    if (!dateString) return "N/A";
    return new Date(dateString).toLocaleString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  // Get unique values for filter dropdowns
  const uniqueSections = [...new Set(applications.map(app => app.market_section).filter(Boolean))];
  const uniqueTypes = [...new Set(applications.map(app => app.application_type).filter(Boolean))];

  // Define allowed statuses based on your database enum
  const allowedStatuses = [
    "pending",
    "payment_phase", 
    "paid",
    "documents_submitted",
    "approved"
  ];

  // Filter and sort applications
  const filteredAndSortedApplications = [...applications]
    .filter(application => {
      const matchesStatus = !filters.status || application.status === filters.status;
      const matchesType = !filters.application_type || application.application_type === filters.application_type;
      const matchesSection = !filters.market_section || application.market_section === filters.market_section;
      const matchesSearch = !filters.search || 
        application.business_name?.toLowerCase().includes(filters.search.toLowerCase()) ||
        application.full_name?.toLowerCase().includes(filters.search.toLowerCase()) ||
        application.email?.toLowerCase().includes(filters.search.toLowerCase());

      return matchesStatus && matchesType && matchesSection && matchesSearch;
    })
    .sort((a, b) => {
      let aValue = a[sortField];
      let bValue = b[sortField];

      if (sortField === "application_date") {
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

  // Format status for display
  const formatStatusDisplay = (status) => {
    const statusMap = {
      pending: "Pending",
      payment_phase: "Payment Phase",
      paid: "Paid",
      documents_submitted: "Submitted Documents",
      approved: "Approved",
      rejected: "Rejected",
      cancelled: "Cancelled"
    };
    return statusMap[status] || status.charAt(0).toUpperCase() + status.slice(1);
  };

  return (
    <div className="applications-container">
      {/* Header Section */}
      <header className="applications-header">
        <div className="header-content">
          <h1 className="applications-title">Stall Rental Applications</h1>
        </div>
        <button
          onClick={fetchApplications}
          className="refresh-button"
          aria-label="Refresh applications"
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
              placeholder="Search by business, name, or email..."
              value={filters.search}
              onChange={(e) => handleFilterChange("search", e.target.value)}
              className="filter-input"
            />
          </div>

          <div className="filter-group">
            <label htmlFor="status-filter" className="filter-label">Status</label>
            <select
              id="status-filter"
              value={filters.status}
              onChange={(e) => handleFilterChange("status", e.target.value)}
              className="filter-select"
            >
              <option value="">All Statuses</option>
              {allowedStatuses.map(status => (
                <option key={status} value={status}>
                  {formatStatusDisplay(status)}
                </option>
              ))}
            </select>
          </div>

          <div className="filter-group">
            <label htmlFor="type-filter" className="filter-label">Application Type</label>
            <select
              id="type-filter"
              value={filters.application_type}
              onChange={(e) => handleFilterChange("application_type", e.target.value)}
              className="filter-select"
            >
              <option value="">All Types</option>
              {uniqueTypes.map(type => (
                <option key={type} value={type}>
                  {type.charAt(0).toUpperCase() + type.slice(1)}
                </option>
              ))}
            </select>
          </div>

          <div className="filter-group">
            <label htmlFor="section-filter" className="filter-label">Market Section</label>
            <select
              id="section-filter"
              value={filters.market_section}
              onChange={(e) => handleFilterChange("market_section", e.target.value)}
              className="filter-select"
            >
              <option value="">All Sections</option>
              {uniqueSections.map(section => (
                <option key={section} value={section}>
                  {section}
                </option>
              ))}
            </select>
          </div>
        </div>

        {hasActiveFilters && (
          <div className="active-filters">
            <span className="active-filters-label">Active filters:</span>
            {filters.status && (
              <span className="filter-tag">
                Status: {formatStatusDisplay(filters.status)}
                <button 
                  onClick={() => handleFilterChange("status", "")}
                  aria-label="Remove status filter"
                >
                  ×
                </button>
              </span>
            )}
            {filters.application_type && (
              <span className="filter-tag">
                Type: {filters.application_type}
                <button 
                  onClick={() => handleFilterChange("application_type", "")}
                  aria-label="Remove type filter"
                >
                  ×
                </button>
              </span>
            )}
            {filters.market_section && (
              <span className="filter-tag">
                Section: {filters.market_section}
                <button 
                  onClick={() => handleFilterChange("market_section", "")}
                  aria-label="Remove section filter"
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
      <main className="applications-main">
        {loading ? (
          <div className="loading-state">
            <div className="loading-spinner"></div>
            <p className="loading-text">Loading applications...</p>
          </div>
        ) : error ? (
          <div className="error-state">
            <div className="error-icon">⚠️</div>
            <div className="error-content">
              <h3 className="error-title">Unable to load applications</h3>
              <p className="error-message">{error}</p>
              <button onClick={fetchApplications} className="retry-button">
                Try Again
              </button>
            </div>
          </div>
        ) : applications.length === 0 ? (
          <div className="empty-state">
            <div className="empty-icon">📋</div>
            <h3 className="empty-title">No applications found</h3>
            <p className="empty-message">
              There are no stall rental applications to review at this time.
            </p>
          </div>
        ) : (
          <div className="table-container">
            <div className="table-responsive">
              <table className="applications-table">
                <thead className="table-header">
                  <tr>
                    <th
                      className="table-header-cell sortable"
                      onClick={() => handleSort("id")}
                    >
                      <span className="header-content">
                        ID {getSortIndicator("id")}
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
                    <th className="table-header-cell">Applicant</th>
                    <th className="table-header-cell">Stall Details</th>
                    <th
                      className="table-header-cell sortable"
                      onClick={() => handleSort("application_type")}
                    >
                      <span className="header-content">
                        Type {getSortIndicator("application_type")}
                      </span>
                    </th>
                    <th
                      className="table-header-cell sortable"
                      onClick={() => handleSort("status")}
                    >
                      <span className="header-content">
                        Status {getSortIndicator("status")}
                      </span>
                    </th>
                    <th
                      className="table-header-cell sortable"
                      onClick={() => handleSort("application_date")}
                    >
                      <span className="header-content">
                        Date Applied {getSortIndicator("application_date")}
                      </span>
                    </th>
                    <th className="table-header-cell actions-header">Actions</th>
                  </tr>
                </thead>
                <tbody className="table-body">
                  {filteredAndSortedApplications.map((application, index) => (
                    <tr key={application.id || index} className="table-row">
                      <td className="table-cell application-id">
                        <div className="id-content">
                          #{application.id}
                        </div>
                      </td>
                      <td className="table-cell business-info">
                        <div className="business-name">
                          {application.business_name}
                        </div>
                        <div className="business-type">
                          {application.market_section}
                        </div>
                      </td>
                      <td className="table-cell applicant-info">
                        <div className="applicant-name">
                          {application.full_name}
                        </div>
                        <div className="applicant-email">
                          {application.email}
                        </div>
                        <div className="applicant-contact">
                          {application.contact_number}
                        </div>
                      </td>
                      <td className="table-cell stall-info">
                        <div className="stall-number">
                          {application.stall_number}
                        </div>
                        <div className="market-name">
                          {application.market_name}
                        </div>
                        <div className="stall-class">
                          Class: {application.class_name}
                        </div>
                      </td>
                      <td className="table-cell">
                        <span
                          className={`application-type type-${application.application_type}`}
                        >
                          {application.application_type
                            ? application.application_type.charAt(0).toUpperCase() +
                              application.application_type.slice(1)
                            : "N/A"}
                        </span>
                      </td>
                      <td className="table-cell">
                        <span className={getStatusBadge(application.status)}>
                          {application.status
                            ? formatStatusDisplay(application.status)
                            : "Unknown"}
                        </span>
                      </td>
                      <td className="table-cell date-applied">
                        <div className="date-display">
                          {formatDate(application.application_date)}
                        </div>
                        <div className="time-display">
                          {
                            formatDateTime(application.application_date).split(
                              ", "
                            )[1]
                          }
                        </div>
                      </td>
                      <td className="table-cell actions-cell">
                        <Link
                          to={`/Market/RenterDetails/${application.id}`}
                          className="view-button"
                          title="View application details"
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
                Showing {filteredAndSortedApplications.length} of {applications.length}{" "}
                applications
                {hasActiveFilters && " (filtered)"}
              </div>
            </footer>
          </div>
        )}
      </main>
    </div>
  );
};

export default RentApproval;
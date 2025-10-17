import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import './RPTAssess.css';

export default function RPTAssess() {
  const [applications, setApplications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    fetchPendingApplications();
  }, []);

  const fetchPendingApplications = async () => {
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTAssess/pending-applications.php');
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log('API Response:', data); // Debug log
      
      if (data.status === 'success') {
        // Log the date format to see what we're getting
        data.data.forEach(app => {
          console.log(`App ${app.id} date:`, app.application_date, 'Type:', typeof app.application_date);
        });
        
        setApplications(data.data);
      } else {
        setError(data.message || 'Failed to fetch applications');
      }
      setLoading(false);
    } catch (error) {
      console.error('Error fetching pending applications:', error);
      setError(error.message);
      setLoading(false);
    }
  };

  const handleView = (applicationId) => {
    navigate(`/RPT/RPTDetails/${applicationId}`);
  };

  const getStatusBadge = (status) => {
    return (
      <span className={`status-badge status-${status}`}>
        {status.replace('_', ' ').toUpperCase()}
      </span>
    );
  };

  // Fixed date formatting function
  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    
    try {
      // Handle MySQL datetime format: "2025-10-17 16:32:34"
      const date = new Date(dateString);
      
      // Check if date is valid
      if (isNaN(date.getTime())) {
        console.log('Invalid date:', dateString);
        return 'Invalid Date';
      }
      
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (error) {
      console.error('Date formatting error:', error, 'Date string:', dateString);
      return 'Date Error';
    }
  };

  if (loading) {
    return (
      <div className='rpt-container'>
        <h1 className="rpt-title">Real Property Assessment</h1>
        <div className="loading-pulse">
          <div className="pulse-line wide"></div>
          <div className="pulse-line"></div>
          <div className="pulse-line medium"></div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className='rpt-container'>
        <h1 className="rpt-title">Real Property Assessment</h1>
        <div className="error-message">
          Error: {error}
        </div>
      </div>
    );
  }

  return (
    <div className='rpt-container'>
      <h1 className="rpt-title">Real Property Assessment</h1>
      <p className="rpt-subtitle">Pending Applications for Assessment</p>

      <div className="table-container">
        <table className="applications-table">
          <thead className="table-header">
            <tr>
              <th className="table-head">Application ID</th>
              <th className="table-head">Applicant Name</th>
              <th className="table-head">Property Type</th>
              <th className="table-head">Property Location</th>
              <th className="table-head">Status</th>
              <th className="table-head">Date</th>
              <th className="table-head">Actions</th>
            </tr>
          </thead>
          <tbody className="table-body">
            {applications.map((application) => (
              <tr key={application.id} className="table-row">
                <td className="table-cell application-id">#{application.id}</td>
                <td className="table-cell applicant-name">
                  {application.first_name} {application.middle_name} {application.last_name}
                </td>
                <td className="table-cell property-type">
                  {application.property_type.replace('_', ' ').toUpperCase()}
                </td>
                <td className="table-cell property-location">
                  {application.property_barangay}, {application.property_municipality}
                </td>
                <td className="table-cell status-cell">
                  {getStatusBadge(application.status)}
                </td>
                <td className="table-cell application-date">
                  {formatDate(application.application_date)}
                </td>
                <td className="table-cell actions-cell">
                  <button
                    onClick={() => handleView(application.id)}
                    className="view-button"
                  >
                    View
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {applications.length === 0 && (
          <div className="empty-state">No pending applications found.</div>
        )}
      </div>
    </div>
  );
}
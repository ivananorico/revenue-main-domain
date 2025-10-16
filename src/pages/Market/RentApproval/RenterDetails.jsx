import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import './RenterDetails.css';

export default function RenterDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [application, setApplication] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedDocument, setSelectedDocument] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [showProceedModal, setShowProceedModal] = useState(false);
  const [showApproveModal, setShowApproveModal] = useState(false);
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [reviewerNotes, setReviewerNotes] = useState('');

  useEffect(() => {
    const fetchApplicationDetails = async () => {
      try {
        setLoading(true);
        setError('');
        
        if (!id || !id.match(/^\d+$/)) {
          throw new Error('Invalid application ID');
        }

        const url = `http://localhost/revenue/backend/Market/RentApproval/get_application_details.php?id=${id}`;
        
        const response = await fetch(url, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          mode: 'cors',
          credentials: 'omit'
        });
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        
        if (!text) {
          throw new Error('Empty response from server');
        }

        let data;
        try {
          data = JSON.parse(text);
        } catch (parseError) {
          throw new Error(`Invalid JSON response from server. Server might be returning HTML error page.`);
        }

        if (data.success) {
          setApplication(data.application);
        } else {
          throw new Error(data.message || 'Failed to load application details');
        }
      } catch (err) {
        console.error('Fetch error:', err);
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    if (id) {
      fetchApplicationDetails();
    } else {
      setError('No application ID provided');
      setLoading(false);
    }
  }, [id]);

  const getStatusBadge = (status) => {
    if (!status) return "status-badge status-unknown";
    
    switch (status.toLowerCase()) {
      case "approved":
        return "status-badge status-approved";
      case "pending":
        return "status-badge status-pending";
      case "rejected":
        return "status-badge status-rejected";
      case "paid":
        return "status-badge status-paid";
      case "documents_submitted":
        return "status-badge status-documents-submitted";
      case "under_review":
        return "status-badge status-under-review";
      case "cancelled":
        return "status-badge status-cancelled";
      default:
        return "status-badge status-unknown";
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    } catch (e) {
      return 'Invalid date';
    }
  };

  const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch (e) {
      return 'Invalid date';
    }
  };

  const calculatePaymentBreakdown = () => {
    if (!application) return null;
    
    const applicationFee = 100;
    const securityBond = 10000;
    const stallRightsFee = application.stall_rights_price || 
                          (application.class_name === 'A' ? 15000 : 
                           application.class_name === 'B' ? 10000 : 5000);
    
    return {
      applicationFee,
      securityBond,
      stallRightsFee,
      total: applicationFee + securityBond + stallRightsFee
    };
  };

  const hasDocument = (documentType) => {
    if (!application?.documents) return false;
    return application.documents.some(doc => 
      doc.document_type === documentType
    );
  };

  const getDocument = (documentType) => {
    if (!application?.documents) return null;
    return application.documents.find(doc => 
      doc.document_type === documentType
    );
  };

  const getDocumentDisplayName = (documentType) => {
    const displayNames = {
      'barangay_certificate': 'Barangay Certificate',
      'id_picture': 'ID Picture',
      'stall_rights_certificate': 'Stall Rights Certificate',
      'business_permit': 'Business Permit',
      'lease_contract': 'Lease of Contract'
    };
    
    return displayNames[documentType] || 
           documentType.replace('_', ' ').split(' ').map(word => 
             word.charAt(0).toUpperCase() + word.slice(1)
           ).join(' ');
  };

  const getFileIcon = (fileExtension) => {
    if (!fileExtension) return '📎';
    
    const ext = fileExtension.toLowerCase();
    if (ext === 'pdf') return '📄';
    if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(ext)) return '🖼️';
    if (['doc', 'docx'].includes(ext)) return '📝';
    if (['xls', 'xlsx'].includes(ext)) return '📊';
    return '📎';
  };

  const getDocumentUrl = (document) => {
    if (!document?.file_path) return '#';
    
    let filePath = document.file_path;
    filePath = filePath.replace(/^(\.\.\/)+/, '');
    
    if (!filePath.startsWith('market_portal/') && !filePath.startsWith('http')) {
      filePath = `market_portal/${filePath}`;
    }
    
    const baseUrl = 'http://localhost/revenue/';
    let fullUrl;
    
    if (filePath.startsWith('http')) {
      fullUrl = filePath;
    } else {
      fullUrl = `${baseUrl}${filePath}`;
    }
    
    return fullUrl;
  };

  const handleProceedToPayment = async () => {
    if (!application) return;

    setActionLoading(true);
    try {
      const response = await fetch(
        'http://localhost/revenue/backend/Market/RentApproval/proceed_to_payment.php',
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ application_id: application.id }),
        }
      );

      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        throw new Error(`Server returned invalid JSON. Response: ${text}`);
      }

      if (data.success) {
        alert('Application moved to Paid status successfully!');
        setShowProceedModal(false);
        setApplication(prev => ({ ...prev, status: 'paid' }));
      } else {
        throw new Error(data.message || 'Failed to proceed to paid status');
      }
    } catch (err) {
      alert(`Error: ${err.message}`);
    } finally {
      setActionLoading(false);
    }
  };

  const handleApprove = async () => {
    if (!application) return;

    setActionLoading(true);
    try {
      const response = await fetch('http://localhost/revenue/backend/Market/RentApproval/approve_application.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          application_id: application.id,
          reviewer_id: 1
        })
      });

      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        throw new Error('Invalid JSON response from server');
      }

      if (data.success) {
        alert('Application approved successfully!');
        setShowApproveModal(false);
        window.location.reload();
      } else {
        throw new Error(data.message || 'Failed to approve application');
      }
    } catch (err) {
      alert(`Error: ${err.message}`);
    } finally {
      setActionLoading(false);
    }
  };

  const handleReject = async () => {
    if (!application) return;

    setActionLoading(true);
    try {
      const response = await fetch('http://localhost/revenue/backend/Market/RentApproval/reject_application.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          application_id: application.id,
          reviewer_notes: reviewerNotes,
          reviewer_id: 1
        })
      });

      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        throw new Error('Invalid JSON response from server');
      }

      if (data.success) {
        alert('Application rejected successfully!');
        setShowRejectModal(false);
        setReviewerNotes('');
        window.location.reload();
      } else {
        throw new Error(data.message || 'Failed to reject application');
      }
    } catch (err) {
      alert(`Error: ${err.message}`);
    } finally {
      setActionLoading(false);
    }
  };

  const handleResubmit = async () => {
    if (!application) return;

    if (window.confirm('Are you sure you want to resubmit this application for review?')) {
      setActionLoading(true);
      try {
        const response = await fetch('http://localhost/revenue/backend/Market/RentApproval/resubmit_application.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            application_id: application.id
          })
        });

        const text = await response.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch (parseError) {
          throw new Error('Invalid JSON response from server');
        }

        if (data.success) {
          alert('Application has been resubmitted for review!');
          window.location.reload();
        } else {
          throw new Error(data.message || 'Failed to resubmit application');
        }
      } catch (err) {
        alert(`Error: ${err.message}`);
      } finally {
        setActionLoading(false);
      }
    }
  };

  const openProceedModal = () => {
    setShowProceedModal(true);
  };

  const openApproveModal = () => {
    setShowApproveModal(true);
  };

  const openRejectModal = () => {
    setReviewerNotes('');
    setShowRejectModal(true);
  };

  const closeModals = () => {
    setShowProceedModal(false);
    setShowApproveModal(false);
    setShowRejectModal(false);
    setReviewerNotes('');
  };

  const openDocumentModal = (document) => {
    setSelectedDocument(document);
    setShowModal(true);
  };

  const closeDocumentModal = () => {
    setShowModal(false);
    setSelectedDocument(null);
  };

  const hasAllRequiredDocuments = () => {
    if (application?.status === 'documents_submitted') return true;
    
    if (application?.status === 'paid') {
      return hasDocument('lease_contract') && hasDocument('business_permit');
    }
    
    return true;
  };

  const shouldShowProceedNext = () => {
    const status = application?.status;
    return status && 
           status !== 'approved' && 
           status !== 'rejected' && 
           status !== 'paid' && 
           status !== 'documents_submitted';
  };

  const shouldShowApproveButton = () => {
    const status = application?.status;
    return status && 
           (status === 'paid' || status === 'documents_submitted') && 
           status !== 'rejected' && 
           status !== 'approved';
  };

  if (loading) {
    return (
      <div className="renter-details-container">
        <div className="loading-state">
          <div className="loading-spinner"></div>
          <p>Loading application details...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="renter-details-container">
        <div className="error-state">
          <h3>Unable to load application</h3>
          <p>{error}</p>
          <div className="error-actions">
            <button onClick={() => navigate(-1)} className="back-button">
              Go Back
            </button>
            <button onClick={() => window.location.reload()} className="retry-button">
              Try Again
            </button>
          </div>
        </div>
      </div>
    );
  }

  if (!application) {
    return (
      <div className="renter-details-container">
        <div className="empty-state">
          <h3>Application not found</h3>
          <p>The application you're looking for doesn't exist or you don't have permission to view it.</p>
          <button onClick={() => navigate(-1)} className="back-button">
            Go Back
          </button>
        </div>
      </div>
    );
  }

  const paymentBreakdown = calculatePaymentBreakdown();
  const leaseContractDocument = getDocument('lease_contract');
  const businessPermitDocument = getDocument('business_permit');

  return (
    <div className="renter-details-container">
      {/* Header */}
      <div className="details-header">
        <div className="header-content">
          <div className="title-section">
            <h1>{application.business_name || 'Business Application'}</h1>
            <div className="application-meta">
              <span className="application-id">Application #{application.id}</span>
              <span className={getStatusBadge(application.status)}>
                {application.status ? application.status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ') : 'Unknown'}
              </span>
              <span className="application-date">Applied on {formatDate(application.application_date)}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="details-content">
        
        {/* Application Status Card */}
        <div className="info-card status-card">
          <h3 className="card-title">Application Status</h3>
          <div className="status-info">
            <div className="status-item">
              <label>Current Status</label>
              <div className="value">
                <span className={getStatusBadge(application.status)}>
                  {application.status ? application.status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ') : 'Unknown'}
                </span>
              </div>
            </div>
            <div className="status-item">
              <label>Application Date</label>
              <div className="value">{formatDateTime(application.application_date)}</div>
            </div>
            <div className="status-item">
              <label>Last Updated</label>
              <div className="value">{formatDateTime(application.updated_at)}</div>
            </div>
          </div>
        </div>

        {/* Payment Information */}
        {(application.status === 'paid' || application.status === 'documents_submitted') && paymentBreakdown && (
          <div className="info-card payment-card">
            <h3 className="card-title">Payment Information</h3>
            <div className="payment-breakdown">
              <div className="payment-item">
                <label>Application Fee:</label>
                <span className="payment-amount">₱{paymentBreakdown.applicationFee.toLocaleString()}</span>
              </div>
              <div className="payment-item">
                <label>Security Bond:</label>
                <span className="payment-amount">₱{paymentBreakdown.securityBond.toLocaleString()}</span>
              </div>
              <div className="payment-item">
                <label>Stall Rights Fee (Class {application.class_name}):</label>
                <span className="payment-amount">₱{paymentBreakdown.stallRightsFee.toLocaleString()}</span>
              </div>
              <div className="payment-item total">
                <label>Total Amount Due:</label>
                <span className="payment-amount total-amount">₱{paymentBreakdown.total.toLocaleString()}</span>
              </div>
            </div>
          </div>
        )}

        {/* Applicant & Business Info Side by Side */}
        <div className="content-row">
          {/* Applicant Information */}
          <div className="info-card">
            <h3 className="card-title">Applicant Information</h3>
            <div className="info-grid">
              <div className="info-item">
                <label>Full Name</label>
                <div className="value">{application.full_name || 'N/A'}</div>
              </div>
              <div className="info-item">
                <label>Gender</label>
                <div className="value">{application.gender || 'N/A'}</div>
              </div>
              <div className="info-item">
                <label>Date of Birth</label>
                <div className="value">{formatDate(application.date_of_birth)}</div>
              </div>
              <div className="info-item">
                <label>Civil Status</label>
                <div className="value">{application.civil_status || 'N/A'}</div>
              </div>
              <div className="info-item full-width">
                <label>Complete Address</label>
                <div className="value">
                  {application.formatted_address || application.address || 'No address provided'}
                </div>
              </div>
              <div className="info-item">
                <label>Contact Number</label>
                <div className="value">{application.contact_number || 'N/A'}</div>
              </div>
              <div className="info-item">
                <label>Email</label>
                <div className="value">{application.email || 'N/A'}</div>
              </div>
            </div>
          </div>

          {/* Business & Stall Information */}
          <div className="info-card">
            <h3 className="card-title">Business & Stall Details</h3>
            <div className="info-grid">
              <div className="info-item">
                <label>Business Name</label>
                <div className="value">{application.business_name || 'N/A'}</div>
              </div>
              <div className="info-item">
                <label>Application Type</label>
                <div className="value">
                  <span className={`application-type type-${application.application_type}`}>
                    {application.application_type?.charAt(0).toUpperCase() + 
                     application.application_type?.slice(1) || 'N/A'}
                  </span>
                </div>
              </div>
              <div className="info-item">
                <label>Market Name</label>
                <div className="value">{application.market_name || 'N/A'}</div>
              </div>
              <div className="info-item">
                <label>Market Section</label>
                <div className="value">{application.section_name || application.market_section || 'N/A'}</div>
              </div>
              <div className="info-item">
                <label>Stall Number</label>
                <div className="value">{application.stall_number || 'N/A'}</div>
              </div>
              <div className="info-item">
                <label>Stall Class</label>
                <div className="value">{application.class_name ? `Class ${application.class_name}` : 'N/A'}</div>
              </div>
              <div className="info-item">
                <label>Monthly Rent</label>
                <div className="value">
                  {application.stall_price ? `₱${parseFloat(application.stall_price).toLocaleString()}` : 'N/A'}
                </div>
              </div>
              <div className="info-item">
                <label>Stall Dimensions</label>
                <div className="value">
                  {application.length && application.width && application.height 
                    ? `${application.length}m × ${application.width}m × ${application.height}m`
                    : 'N/A'
                  }
                </div>
              </div>
              <div className="info-item">
                <label>Stall Status</label>
                <div className="value">
                  <span className={getStatusBadge(application.stall_status)}>
                    {application.stall_status?.charAt(0).toUpperCase() + 
                     application.stall_status?.slice(1) || 'N/A'}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Documents Section */}
        <div className="info-card">
          <h3 className="card-title">Supporting Documents</h3>
          
          {/* Required Documents Section */}
          {(application.status === 'paid' || application.status === 'documents_submitted') && (
            <div className="required-documents-section">
              <h4 className="sub-section-title">Required Documents for Approval</h4>
              
              {/* Lease of Contract */}
              <div className={`document-section ${hasDocument('lease_contract') ? 'document-uploaded' : 'document-missing'}`}>
                <div className="document-header">
                  <div className="document-title">
                    <span className="document-icon">📑</span>
                    Lease of Contract
                  </div>
                  <div className={`document-status ${hasDocument('lease_contract') ? 'status-uploaded' : 'status-missing'}`}>
                    {hasDocument('lease_contract') ? '✅ Uploaded' : '❌ Missing'}
                  </div>
                </div>
                {leaseContractDocument ? (
                  <div className="document-item highlighted">
                    <div className="document-info">
                      <div className="document-name">{leaseContractDocument.file_name || 'Lease of Contract'}</div>
                      <div className="document-meta">
                        {leaseContractDocument.file_extension?.toUpperCase()} • 
                        {leaseContractDocument.file_size ? ` ${(leaseContractDocument.file_size / 1024).toFixed(1)} KB` : ' Size unknown'} • 
                        {formatDate(leaseContractDocument.uploaded_at)}
                      </div>
                    </div>
                    <button 
                      onClick={() => openDocumentModal(leaseContractDocument)}
                      className="view-document-button"
                    >
                      View
                    </button>
                  </div>
                ) : (
                  <div className="document-missing-message">
                    Applicant needs to upload the signed Lease of Contract document.
                  </div>
                )}
              </div>

              {/* Business Permit */}
              <div className={`document-section ${hasDocument('business_permit') ? 'document-uploaded' : 'document-missing'}`}>
                <div className="document-header">
                  <div className="document-title">
                    <span className="document-icon">🏢</span>
                    Business Permit
                  </div>
                  <div className={`document-status ${hasDocument('business_permit') ? 'status-uploaded' : 'status-missing'}`}>
                    {hasDocument('business_permit') ? '✅ Uploaded' : '❌ Missing'}
                  </div>
                </div>
                {businessPermitDocument ? (
                  <div className="document-item highlighted">
                    <div className="document-info">
                      <div className="document-name">{businessPermitDocument.file_name || 'Business Permit'}</div>
                      <div className="document-meta">
                        {businessPermitDocument.file_extension?.toUpperCase()} • 
                        {businessPermitDocument.file_size ? ` ${(businessPermitDocument.file_size / 1024).toFixed(1)} KB` : ' Size unknown'} • 
                        {formatDate(businessPermitDocument.uploaded_at)}
                      </div>
                    </div>
                    <button 
                      onClick={() => openDocumentModal(businessPermitDocument)}
                      className="view-document-button"
                    >
                      View
                    </button>
                  </div>
                ) : (
                  <div className="document-missing-message">
                    Applicant needs to upload a valid Business Permit.
                  </div>
                )}
              </div>

              {/* Approval Requirement Notice */}
              {!hasAllRequiredDocuments() && application.status === 'paid' && (
                <div className="approval-notice">
                  <div className="notice-icon">⚠️</div>
                  <div className="notice-content">
                    <strong>Approval Requirements Not Met</strong>
                    <p>Both Lease of Contract and Business Permit must be uploaded before this application can be approved.</p>
                  </div>
                </div>
              )}

              {/* Documents Submitted Notice */}
              {application.status === 'documents_submitted' && (
                <div className="documents-submitted-notice">
                  <div className="notice-icon">✅</div>
                  <div className="notice-content">
                    <strong>All Required Documents Submitted</strong>
                    <p>The applicant has uploaded both required documents. You can now proceed with approval.</p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Regular Documents */}
          <div className="regular-documents-section">
            <h4 className="sub-section-title">Other Supporting Documents</h4>
            {application.documents && application.documents.length > 0 ? (
              <div className="documents-grid">
                {application.documents
                  .filter(doc => (application.status !== 'paid' && application.status !== 'documents_submitted') || 
                          (doc.document_type !== 'lease_contract' && doc.document_type !== 'business_permit'))
                  .map((doc, index) => (
                  <div key={doc.id || index} className="document-item">
                    <div className="document-icon">
                      {getFileIcon(doc.file_extension)}
                    </div>
                    <div className="document-info">
                      <div className="document-name">{doc.file_name || 'Unnamed Document'}</div>
                      <div className="document-meta">
                        {getDocumentDisplayName(doc.document_type).toUpperCase()} • 
                        {doc.file_size ? ` ${(doc.file_size / 1024).toFixed(1)} KB` : ' Size unknown'} • 
                        {formatDate(doc.uploaded_at)}
                      </div>
                    </div>
                    <button 
                      onClick={() => openDocumentModal(doc)}
                      className="view-document-button"
                    >
                      View
                    </button>
                  </div>
                ))}
              </div>
            ) : (
              <div className="no-documents">
                No other documents uploaded for this application.
              </div>
            )}
          </div>
        </div>

      </div>

      {/* Action Buttons */}
      <div className="action-buttons">
        <div className="action-group">
          <button 
            onClick={() => navigate(-1)} 
            className="secondary-button"
            disabled={actionLoading}
          >
            Back to List
          </button>
          {application.status !== 'approved' && application.status !== 'rejected' && application.status !== 'paid' && application.status !== 'documents_submitted' && (
            <button 
              onClick={handleResubmit} 
              className="resubmit-button"
              disabled={actionLoading}
            >
              {actionLoading ? 'Processing...' : 'Resubmit Application'}
            </button>
          )}
        </div>
        <div className="action-group">
          {application.status !== 'rejected' && application.status !== 'approved' && (
            <button 
              onClick={openRejectModal} 
              className="reject-button"
              disabled={actionLoading}
            >
              Reject
            </button>
          )}
          {shouldShowApproveButton() && (
            <button 
              onClick={openApproveModal} 
              className="approve-button"
              disabled={actionLoading || !hasAllRequiredDocuments()}
              title={!hasAllRequiredDocuments() ? 'Both Lease Contract and Business Permit must be uploaded before approval' : ''}
            >
              {hasAllRequiredDocuments() ? 'Approve' : 'Approve (Documents Missing)'}
            </button>
          )}
          {shouldShowProceedNext() && (
            <button 
              onClick={openProceedModal} 
              className="proceed-button"
              disabled={actionLoading}
            >
              Proceed Next
            </button>
          )}
        </div>
      </div>

      {/* Proceed Next Modal */}
      {showProceedModal && (
        <div className="modal-overlay" onClick={closeModals}>
          <div className="modal-content action-modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Proceed to Paid Status</h3>
              <button className="modal-close" onClick={closeModals}>×</button>
            </div>
            <div className="modal-body">
              <p>Are you sure you want to move this application to Paid Status?</p>
              <p><strong>This will:</strong></p>
              <ul>
                <li>Change status to "Paid"</li>
                <li>Auto-generate payment fees</li>
                <li>Allow the applicant to proceed with payment</li>
                <li>Require the applicant to upload Lease of Contract and Business Permit</li>
              </ul>
            </div>
            <div className="modal-footer">
              <button onClick={closeModals} className="close-modal-button">
                Cancel
              </button>
              <button 
                onClick={handleProceedToPayment} 
                className="proceed-button"
                disabled={actionLoading}
              >
                {actionLoading ? 'Processing...' : 'Confirm Proceed'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Approve Modal */}
      {showApproveModal && (
        <div className="modal-overlay" onClick={closeModals}>
          <div className="modal-content action-modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Approve Application</h3>
              <button className="modal-close" onClick={closeModals}>×</button>
            </div>
            <div className="modal-body">
              <p>Are you sure you want to approve this application?</p>
              
              {/* Document Check Summary */}
              <div className="document-check-summary">
                <h4>Document Status:</h4>
                <div className={`check-item ${hasDocument('lease_contract') ? 'check-valid' : 'check-invalid'}`}>
                  {hasDocument('lease_contract') ? '✅' : '❌'} Lease of Contract
                </div>
                <div className={`check-item ${hasDocument('business_permit') ? 'check-valid' : 'check-invalid'}`}>
                  {hasDocument('business_permit') ? '✅' : '❌'} Business Permit
                </div>
              </div>

              {!hasAllRequiredDocuments() && (
                <div className="approval-warning">
                  <strong>⚠️ Warning:</strong> Not all required documents are uploaded. 
                  You can still approve, but this may require follow-up.
                </div>
              )}

              {application.status === 'documents_submitted' && (
                <div className="documents-ready-notice">
                  <strong>✅ All Documents Ready:</strong> The applicant has submitted all required documents.
                </div>
              )}

              <p><strong>This will:</strong></p>
              <ul>
                <li>Change status to "Approved"</li>
                <li>Finalize the stall assignment</li>
                <li>Notify the applicant of approval</li>
                <li>Complete the application process</li>
              </ul>
            </div>
            <div className="modal-footer">
              <button onClick={closeModals} className="close-modal-button">
                Cancel
              </button>
              <button 
                onClick={handleApprove} 
                className="approve-button"
                disabled={actionLoading}
              >
                {actionLoading ? 'Processing...' : 'Confirm Approval'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Reject Modal */}
      {showRejectModal && (
        <div className="modal-overlay" onClick={closeModals}>
          <div className="modal-content action-modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Reject Application</h3>
              <button className="modal-close" onClick={closeModals}>×</button>
            </div>
            <div className="modal-body">
              <p>Are you sure you want to reject this application?</p>
              <div className="notes-input">
                <label>Reason for Rejection (Required):</label>
                <textarea
                  value={reviewerNotes}
                  onChange={(e) => setReviewerNotes(e.target.value)}
                  placeholder="Please provide the reason for rejection..."
                  rows="4"
                  required
                />
              </div>
            </div>
            <div className="modal-footer">
              <button onClick={closeModals} className="close-modal-button">
                Cancel
              </button>
              <button 
                onClick={handleReject} 
                className="reject-button"
                disabled={actionLoading || !reviewerNotes.trim()}
              >
                {actionLoading ? 'Rejecting...' : 'Confirm Rejection'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Document Modal */}
      {showModal && selectedDocument && (
        <div className="modal-overlay" onClick={closeDocumentModal}>
          <div className="modal-content document-modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>{selectedDocument.file_name || 'Document'}</h3>
              <button className="modal-close" onClick={closeDocumentModal}>×</button>
            </div>
            <div className="modal-body">
              <div className="document-info-bar">
                <span className="document-type">
                  {getDocumentDisplayName(selectedDocument.document_type)}
                </span>
                <span className="document-size">
                  {selectedDocument.file_size ? `Size: ${(selectedDocument.file_size / 1024).toFixed(1)} KB` : ''}
                </span>
                <span className="document-date">
                  Uploaded: {formatDate(selectedDocument.uploaded_at)}
                </span>
              </div>
              
              <div className="document-viewer">
                {selectedDocument.file_extension?.toLowerCase() === 'pdf' ? (
                  <iframe 
                    src={getDocumentUrl(selectedDocument)} 
                    className="document-iframe"
                    title={selectedDocument.file_name || 'Document'}
                    onError={(e) => {
                      e.target.style.display = 'none';
                      document.querySelector('.document-error').style.display = 'block';
                    }}
                  />
                ) : (
                  <img 
                    src={getDocumentUrl(selectedDocument)} 
                    alt={selectedDocument.file_name || 'Document'}
                    className="document-image"
                    onError={(e) => {
                      e.target.style.display = 'none';
                      document.querySelector('.document-error').style.display = 'block';
                    }}
                  />
                )}
                <div className="document-error" style={{display: 'none'}}>
                  <div className="error-content">
                    <div className="error-icon">❌</div>
                    <h4>Unable to Load Document</h4>
                    <p>The document could not be loaded. This could be due to:</p>
                    <ul>
                      <li>File not found on server</li>
                      <li>Network connectivity issues</li>
                      <li>File format not supported in browser</li>
                    </ul>
                    <button 
                      onClick={() => window.open(getDocumentUrl(selectedDocument), '_blank')}
                      className="open-external-button"
                    >
                      Try Opening in New Tab
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div className="modal-footer">
              <button 
                onClick={() => window.open(getDocumentUrl(selectedDocument), '_blank')}
                className="open-external-button"
              >
                🔗 Open in New Tab
              </button>
              <button onClick={closeDocumentModal} className="close-modal-button">
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
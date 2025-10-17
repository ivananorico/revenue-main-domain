import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import './RPTDetails.css';

export default function RPTDetails() {
  const [application, setApplication] = useState(null);
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showAssessmentModal, setShowAssessmentModal] = useState(false);
  const [showPropertyModal, setShowPropertyModal] = useState(false);
  const [showDocumentModal, setShowDocumentModal] = useState(false);
  const [selectedDocument, setSelectedDocument] = useState(null);
  const [documentLoading, setDocumentLoading] = useState(false);
  const [assessmentData, setAssessmentData] = useState({
    visit_date: '',
    assessor_name: '',
    notes: ''
  });
  const [propertyData, setPropertyData] = useState({
    land_area: '',
    land_value: '',
    building_area: '',
    building_value: '',
    total_value: '',
    annual_tax: ''
  });
  const { id } = useParams();
  const navigate = useNavigate();

  useEffect(() => {
    if (id) {
      fetchApplicationDetails();
    } else {
      setError('No application ID provided');
      setLoading(false);
    }
  }, [id]);

  const fetchApplicationDetails = async () => {
    try {
      const response = await fetch(`http://localhost/revenue/backend/RPT/RPTAssess/application-details.php?id=${id}`);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.status === 'success') {
        setApplication(data.data.application);
        setDocuments(data.data.documents || []);
      } else {
        setError(data.message || 'Failed to fetch application details');
      }
      setLoading(false);
    } catch (error) {
      console.error('Error fetching application details:', error);
      setError(error.message);
      setLoading(false);
    }
  };

  const handleBack = () => {
    navigate('/RPT/RPTAssess');
  };

  const handleStatusUpdate = async (newStatus) => {
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTAssess/update-status.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          application_id: id,
          status: newStatus
        })
      });

      const data = await response.json();
      
      if (data.status === 'success') {
        setApplication(prev => ({ ...prev, status: newStatus }));
        alert('Status updated successfully!');
      } else {
        alert('Failed to update status: ' + data.message);
      }
    } catch (error) {
      console.error('Error updating status:', error);
      alert('Error updating status');
    }
  };

  const handleAssessmentSubmit = async () => {
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTAssess/save-assessment.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          application_id: id,
          ...assessmentData
        })
      });

      const data = await response.json();
      
      if (data.status === 'success') {
        setApplication(prev => ({ ...prev, status: 'for_assessment' }));
        setShowAssessmentModal(false);
        setAssessmentData({ visit_date: '', assessor_name: '', notes: '' });
        alert('Assessment scheduled successfully!');
      } else {
        alert('Failed to schedule assessment: ' + data.message);
      }
    } catch (error) {
      console.error('Error scheduling assessment:', error);
      alert('Error scheduling assessment');
    }
  };

  const handlePropertySubmit = async () => {
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTAssess/save-property-assessment.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          application_id: id,
          ...propertyData
        })
      });

      const data = await response.json();
      
      if (data.status === 'success') {
        setApplication(prev => ({ ...prev, status: 'assessed' }));
        setShowPropertyModal(false);
        setPropertyData({
          land_area: '',
          land_value: '',
          building_area: '',
          building_value: '',
          total_value: '',
          annual_tax: ''
        });
        alert('Property assessment saved successfully!');
      } else {
        alert('Failed to save property assessment: ' + data.message);
      }
    } catch (error) {
      console.error('Error saving property assessment:', error);
      alert('Error saving property assessment');
    }
  };

  const calculateTotalValue = () => {
    const land = parseFloat(propertyData.land_value) || 0;
    const building = parseFloat(propertyData.building_value) || 0;
    const total = land + building;
    const annualTax = total * 0.01;
    
    setPropertyData(prev => ({
      ...prev,
      total_value: total.toFixed(2),
      annual_tax: annualTax.toFixed(2)
    }));
  };

  const handleViewDocument = async (doc) => {
    setSelectedDocument(doc);
    setDocumentLoading(true);
    setShowDocumentModal(true);
    
    try {
      let filePath = doc.file_path;
      
      if (filePath.includes('C:\\xampp\\htdocs\\')) {
        filePath = filePath.replace('C:\\xampp\\htdocs\\', '/');
        filePath = filePath.replace(/\\/g, '/');
      }
      
      const fullUrl = `http://localhost${filePath}`;
      
      // For images, we can display them directly in the modal
      // For other file types, we'll show a download link
      setDocumentLoading(false);
    } catch (error) {
      console.error('Error loading document:', error);
      setDocumentLoading(false);
    }
  };

  const getDocumentUrl = (doc) => {
    let filePath = doc.file_path;
    
    if (filePath.includes('C:\\xampp\\htdocs\\')) {
      filePath = filePath.replace('C:\\xampp\\htdocs\\', '/');
      filePath = filePath.replace(/\\/g, '/');
    }
    
    return `http://localhost${filePath}`;
  };

  const isImageFile = (fileName) => {
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp'];
    return imageExtensions.some(ext => fileName.toLowerCase().endsWith(ext));
  };

  const isPdfFile = (fileName) => {
    return fileName.toLowerCase().endsWith('.pdf');
  };

  const getStatusBadge = (status) => {
    const statusColors = {
      pending: 'status-pending',
      for_assessment: 'status-for-assessment',
      assessed: 'status-assessed',
      approved: 'status-approved',
      rejected: 'status-rejected'
    };

    return (
      <span className={`status-badge ${statusColors[status] || 'status-pending'}`}>
        {status.replace('_', ' ').toUpperCase()}
      </span>
    );
  };

  // Close modal when clicking outside
  const handleOverlayClick = (e, modalType) => {
    if (e.target === e.currentTarget) {
      if (modalType === 'assessment') {
        setShowAssessmentModal(false);
      } else if (modalType === 'property') {
        setShowPropertyModal(false);
      } else if (modalType === 'document') {
        setShowDocumentModal(false);
        setSelectedDocument(null);
      }
    }
  };

  if (loading) {
    return (
      <div className='rpt-details-container'>
        <h1 className="details-title">Real Property Owner Details</h1>
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
      <div className='rpt-details-container'>
        <h1 className="details-title">Real Property Owner Details</h1>
        <div className="error-message">
          <div className="error-content">
            <strong>Error:</strong> {error}
            <br />
            <span className="error-id">Application ID: {id}</span>
          </div>
          <button onClick={handleBack} className="back-button">
            ← Back to Applications
          </button>
        </div>
      </div>
    );
  }

  if (!application) {
    return (
      <div className='rpt-details-container'>
        <h1 className="details-title">Real Property Owner Details</h1>
        <div className="error-message">
          <div className="error-content">
            Application not found
          </div>
          <button onClick={handleBack} className="back-button">
            ← Back to Applications
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className='rpt-details-container'>
      {/* Header */}
      <div className="details-header">
        <button onClick={handleBack} className="back-button">
          ← Back to Applications
        </button>
        <div className="header-content">
          <h1 className="details-title">Real Property Owner Details</h1>
          <div className="application-info">
            <span className="application-id">Application ID: #{application.id}</span>
            {getStatusBadge(application.status)}
          </div>
        </div>
      </div>

      <div className="details-content">
        {/* Applicant Information */}
        <div className="details-section">
          <h2 className="section-title">Applicant Information</h2>
          <div className="info-grid">
            <div className="info-group">
              <label>Full Name</label>
              <p>{application.first_name} {application.middle_name} {application.last_name}</p>
            </div>
            <div className="info-group">
              <label>Gender</label>
              <p>{application.gender}</p>
            </div>
            <div className="info-group">
              <label>Date of Birth</label>
              <p>{new Date(application.date_of_birth).toLocaleDateString()}</p>
            </div>
            <div className="info-group">
              <label>Civil Status</label>
              <p>{application.civil_status}</p>
            </div>
            <div className="info-group">
              <label>Contact Number</label>
              <p>{application.contact_number}</p>
            </div>
            <div className="info-group">
              <label>Email</label>
              <p>{application.email}</p>
            </div>
          </div>
        </div>

        {/* Address Information */}
        <div className="details-section">
          <h2 className="section-title">Address Information</h2>
          <div className="info-grid">
            <div className="info-group">
              <label>House Number</label>
              <p>{application.house_number || 'N/A'}</p>
            </div>
            <div className="info-group">
              <label>Street</label>
              <p>{application.street || 'N/A'}</p>
            </div>
            <div className="info-group">
              <label>Barangay</label>
              <p>{application.barangay}</p>
            </div>
            <div className="info-group">
              <label>City/Municipality</label>
              <p>{application.city}</p>
            </div>
            <div className="info-group">
              <label>ZIP Code</label>
              <p>{application.zip_code}</p>
            </div>
          </div>
        </div>

        {/* Property Information */}
        <div className="details-section">
          <h2 className="section-title">Property Information</h2>
          <div className="info-grid">
            <div className="info-group">
              <label>Application Type</label>
              <p>{application.application_type.toUpperCase()}</p>
            </div>
            <div className="info-group">
              <label>Property Type</label>
              <p>{application.property_type.replace('_', ' ').toUpperCase()}</p>
            </div>
            <div className="info-group full-width">
              <label>Property Address</label>
              <p>{application.property_address}</p>
            </div>
            <div className="info-group">
              <label>Property Barangay</label>
              <p>{application.property_barangay}</p>
            </div>
            <div className="info-group">
              <label>Property Municipality</label>
              <p>{application.property_municipality}</p>
            </div>
            {application.previous_tdn && (
              <div className="info-group">
                <label>Previous TDN</label>
                <p>{application.previous_tdn}</p>
              </div>
            )}
            {application.previous_owner && (
              <div className="info-group">
                <label>Previous Owner</label>
                <p>{application.previous_owner}</p>
              </div>
            )}
          </div>
        </div>

        {/* Documents Section */}
        {documents.length > 0 && (
          <div className="details-section">
            <h2 className="section-title">Submitted Documents ({documents.length})</h2>
            <div className="documents-grid">
              {documents.map((doc) => (
                <div key={doc.id} className="document-card">
                  <div className="document-type">
                    {doc.document_type.replace('_', ' ').toUpperCase()}
                  </div>
                  <div className="document-name">{doc.file_name}</div>
                  <div className="document-info">
                    <small>Size: {doc.file_size ? (doc.file_size / 1024).toFixed(2) + ' KB' : 'N/A'}</small>
                    <small>Type: {doc.file_extension || 'Unknown'}</small>
                  </div>
                  <button 
                    onClick={() => handleViewDocument(doc)}
                    className="view-document-button"
                  >
                    View Document
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Action Buttons */}
        <div className="details-section">
          <h2 className="section-title">Assessment Actions</h2>
          <div className="action-buttons">
            <button 
              onClick={() => setShowAssessmentModal(true)}
              className="action-button assessment"
              disabled={application.status !== 'pending'}
            >
              Mark for Assessment
            </button>
            <button 
              onClick={() => setShowPropertyModal(true)}
              className="action-button assessed"
              disabled={application.status !== 'for_assessment'}
            >
              Assess Property
            </button>
            <button 
              onClick={() => handleStatusUpdate('approved')}
              className="action-button approve"
              disabled={application.status !== 'assessed'}
            >
              Approve Application
            </button>
            <button 
              onClick={() => handleStatusUpdate('rejected')}
              className="action-button reject"
            >
              Reject Application
            </button>
          </div>
        </div>
      </div>

      {/* Assessment Modal */}
      {showAssessmentModal && (
        <div className="modal-overlay" onClick={(e) => handleOverlayClick(e, 'assessment')}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Schedule Property Assessment</h3>
              <button onClick={() => setShowAssessmentModal(false)} className="modal-close">
                ×
              </button>
            </div>
            <div className="modal-body">
              <div className="form-group">
                <label>Visit Date *</label>
                <input
                  type="date"
                  value={assessmentData.visit_date}
                  onChange={(e) => setAssessmentData(prev => ({...prev, visit_date: e.target.value}))}
                  min={new Date().toISOString().split('T')[0]}
                  className="form-input"
                />
              </div>
              <div className="form-group">
                <label>Assessor Name *</label>
                <input
                  type="text"
                  value={assessmentData.assessor_name}
                  onChange={(e) => setAssessmentData(prev => ({...prev, assessor_name: e.target.value}))}
                  placeholder="Enter assessor's full name"
                  className="form-input"
                />
              </div>
              <div className="form-group">
                <label>Assessment Notes</label>
                <textarea
                  value={assessmentData.notes}
                  onChange={(e) => setAssessmentData(prev => ({...prev, notes: e.target.value}))}
                  placeholder="Additional notes for the assessment..."
                  rows="4"
                  className="form-textarea"
                />
              </div>
            </div>
            <div className="modal-footer">
              <button onClick={() => setShowAssessmentModal(false)} className="btn-cancel">
                Cancel
              </button>
              <button 
                onClick={handleAssessmentSubmit}
                className="btn-primary"
                disabled={!assessmentData.visit_date || !assessmentData.assessor_name}
              >
                Schedule Assessment
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Property Assessment Modal */}
      {showPropertyModal && (
        <div className="modal-overlay" onClick={(e) => handleOverlayClick(e, 'property')}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Property Assessment Details</h3>
              <button onClick={() => setShowPropertyModal(false)} className="modal-close">
                ×
              </button>
            </div>
            <div className="modal-body">
              <div className="form-row">
                <div className="form-group">
                  <label>Land Area (sqm) *</label>
                  <input
                    type="number"
                    value={propertyData.land_area}
                    onChange={(e) => setPropertyData(prev => ({...prev, land_area: e.target.value}))}
                    placeholder="Enter land area"
                    className="form-input"
                  />
                </div>
                <div className="form-group">
                  <label>Land Value (₱) *</label>
                  <input
                    type="number"
                    value={propertyData.land_value}
                    onChange={(e) => {
                      setPropertyData(prev => ({...prev, land_value: e.target.value}));
                      setTimeout(calculateTotalValue, 100);
                    }}
                    placeholder="Enter land value"
                    className="form-input"
                  />
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label>Building Area (sqm)</label>
                  <input
                    type="number"
                    value={propertyData.building_area}
                    onChange={(e) => setPropertyData(prev => ({...prev, building_area: e.target.value}))}
                    placeholder="Enter building area"
                    className="form-input"
                  />
                </div>
                <div className="form-group">
                  <label>Building Value (₱)</label>
                  <input
                    type="number"
                    value={propertyData.building_value}
                    onChange={(e) => {
                      setPropertyData(prev => ({...prev, building_value: e.target.value}));
                      setTimeout(calculateTotalValue, 100);
                    }}
                    placeholder="Enter building value"
                    className="form-input"
                  />
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label>Total Property Value (₱)</label>
                  <input
                    type="text"
                    value={propertyData.total_value ? `₱${propertyData.total_value}` : ''}
                    readOnly
                    className="form-input readonly"
                  />
                </div>
                <div className="form-group">
                  <label>Annual Tax (₱)</label>
                  <input
                    type="text"
                    value={propertyData.annual_tax ? `₱${propertyData.annual_tax}` : ''}
                    readOnly
                    className="form-input readonly"
                  />
                </div>
              </div>
            </div>
            <div className="modal-footer">
              <button onClick={() => setShowPropertyModal(false)} className="btn-cancel">
                Cancel
              </button>
              <button 
                onClick={handlePropertySubmit}
                className="btn-primary"
                disabled={!propertyData.land_area || !propertyData.land_value}
              >
                Save Assessment
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Document View Modal */}
      {showDocumentModal && selectedDocument && (
        <div className="modal-overlay" onClick={(e) => handleOverlayClick(e, 'document')}>
          <div className="modal-content document-modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Document Viewer</h3>
              <button 
                onClick={() => {
                  setShowDocumentModal(false);
                  setSelectedDocument(null);
                }} 
                className="modal-close"
              >
                ×
              </button>
            </div>
            <div className="modal-body document-modal-body">
              {documentLoading ? (
                <div className="document-loading">
                  <div className="loading-spinner"></div>
                  <p>Loading document...</p>
                </div>
              ) : (
                <>
                  <div className="document-info-header">
                    <h4>{selectedDocument.file_name}</h4>
                    <div className="document-meta">
                      <span>Type: {selectedDocument.document_type.replace('_', ' ').toUpperCase()}</span>
                      <span>Size: {selectedDocument.file_size ? (selectedDocument.file_size / 1024).toFixed(2) + ' KB' : 'N/A'}</span>
                    </div>
                  </div>
                  
                  <div className="document-preview">
                    {isImageFile(selectedDocument.file_name) ? (
                      <div className="image-preview">
                        <img 
                          src={getDocumentUrl(selectedDocument)} 
                          alt={selectedDocument.file_name}
                          onError={(e) => {
                            e.target.style.display = 'none';
                            e.target.nextSibling.style.display = 'block';
                          }}
                        />
                        <div className="fallback-message" style={{display: 'none'}}>
                          <p>Unable to load image preview</p>
                          <a 
                            href={getDocumentUrl(selectedDocument)} 
                            target="_blank" 
                            rel="noopener noreferrer"
                            className="download-link"
                          >
                            Download Image
                          </a>
                        </div>
                      </div>
                    ) : isPdfFile(selectedDocument.file_name) ? (
                      <div className="pdf-preview">
                        <div className="pdf-placeholder">
                          <div className="pdf-icon">📄</div>
                          <p>PDF Document</p>
                          <a 
                            href={getDocumentUrl(selectedDocument)} 
                            target="_blank" 
                            rel="noopener noreferrer"
                            className="download-link"
                          >
                            Open PDF in New Tab
                          </a>
                        </div>
                      </div>
                    ) : (
                      <div className="file-preview">
                        <div className="file-icon">📎</div>
                        <p>This file type cannot be previewed</p>
                        <a 
                          href={getDocumentUrl(selectedDocument)} 
                          target="_blank" 
                          rel="noopener noreferrer"
                          className="download-link"
                        >
                          Download File
                        </a>
                      </div>
                    )}
                  </div>
                </>
              )}
            </div>
            <div className="modal-footer">
              <button 
                onClick={() => {
                  setShowDocumentModal(false);
                  setSelectedDocument(null);
                }} 
                className="btn-cancel"
              >
                Close
              </button>
              <a 
                href={getDocumentUrl(selectedDocument)} 
                target="_blank" 
                rel="noopener noreferrer"
                className="btn-primary"
              >
                Download
              </a>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
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
    lot_area: '',
    land_use: 'Residential',
    location: '',
    barangay: '',
    municipality: '',
    tdn_no: ''
  });
  const [landUseOptions, setLandUseOptions] = useState([]);
  const { id } = useParams();
  const navigate = useNavigate();

  useEffect(() => {
    if (id) {
      fetchApplicationDetails();
      fetchLandUseOptions();
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
        
        // Pre-fill property data from application
        setPropertyData(prev => ({
          ...prev,
          location: data.data.application.property_address,
          barangay: data.data.application.property_barangay,
          municipality: data.data.application.property_municipality,
          tdn_no: `TDN-${new Date().getFullYear()}-${data.data.application.id.toString().padStart(3, '0')}`
        }));
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

  const fetchLandUseOptions = async () => {
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTAssess/get-land-use-options.php');
      const data = await response.json();
      
      if (data.status === 'success') {
        setLandUseOptions(data.data);
      } else {
        // Fallback options
        setLandUseOptions([
          { land_use: 'Residential', market_value_per_sqm: 1500.00, land_assessed_lvl: 0.20 },
          { land_use: 'Commercial', market_value_per_sqm: 3000.00, land_assessed_lvl: 0.35 },
          { land_use: 'Industrial', market_value_per_sqm: 2500.00, land_assessed_lvl: 0.40 },
          { land_use: 'Agricultural', market_value_per_sqm: 800.00, land_assessed_lvl: 0.10 }
        ]);
      }
    } catch (error) {
      console.error('Error fetching land use options:', error);
      setLandUseOptions([
        { land_use: 'Residential', market_value_per_sqm: 1500.00, land_assessed_lvl: 0.20 },
        { land_use: 'Commercial', market_value_per_sqm: 3000.00, land_assessed_lvl: 0.35 },
        { land_use: 'Industrial', market_value_per_sqm: 2500.00, land_assessed_lvl: 0.40 },
        { land_use: 'Agricultural', market_value_per_sqm: 800.00, land_assessed_lvl: 0.10 }
      ]);
    }
  };

  const handleBack = () => {
    navigate('/RPT/RPTAssess');
  };

 const handlePropertySubmit = async () => {
  try {
    console.log('Submitting property data:', propertyData);
    console.log('Application ID:', id);

    // Validate required fields
    if (!propertyData.lot_area || !propertyData.land_use) {
      alert('Please fill in all required fields.');
      return;
    }

    // Create the payload structure that matches PHP backend expectations
    const payload = {
      application_id: parseInt(id),
      property_data: {
        lot_area: propertyData.lot_area,
        land_use: propertyData.land_use,
        location: propertyData.location,
        barangay: propertyData.barangay,
        municipality: propertyData.municipality,
        tdn_no: propertyData.tdn_no
      }
    };

    console.log('Payload being sent:', payload);

    const response = await fetch(
      'http://localhost/revenue/backend/RPT/RPTAssess/save_land_assessment-complete.php',
      {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      }
    );

    console.log('Response status:', response.status);

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const responseText = await response.text();
    console.log('Raw response:', responseText);

    let data;
    try {
      data = JSON.parse(responseText);
    } catch (err) {
      console.error('JSON parse error:', err);
      throw new Error('Invalid JSON response from server: ' + responseText);
    }

    if (data.status === 'success') {
      // Use the calculated values from the backend response
      const calculations = data.data.calculations;
      alert(
        `✅ Assessment completed successfully!\n\n` +
        `Market Value per sqm: ₱${calculations.market_value_per_sqm.toFixed(2)}\n` +
        `Assessed Value: ₱${calculations.land_assessed_value.toFixed(2)}\n` +
        `Annual Tax: ₱${calculations.land_total_tax.toFixed(2)}\n` +
        `Quarterly Tax: ₱${calculations.quarterly_tax.toFixed(2)}`
      );

      setApplication(prev => ({ ...prev, status: 'assessed' }));
      setShowPropertyModal(false);

      // Auto approve if property type is land only
      if (application.property_type === 'land_only') {
        setTimeout(() => handleStatusUpdate('approved'), 1000);
      }
    } else {
      alert('❌ Failed to save property assessment: ' + data.message);
    }
  } catch (error) {
    console.error('Error saving property assessment:', error);
    alert('Error saving property assessment: ' + error.message);
  }
};

  const handlePropertyDataChange = (field, value) => {
    setPropertyData(prev => ({
      ...prev,
      [field]: value
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
    const statusClasses = {
      pending: 'status-badge status-pending',
      for_assessment: 'status-badge status-for-assessment',
      assessed: 'status-badge status-assessed',
      approved: 'status-badge status-approved',
      rejected: 'status-badge status-rejected'
    };

    return (
      <span className={statusClasses[status] || 'status-badge status-pending'}>
        {status.replace('_', ' ').toUpperCase()}
      </span>
    );
  };

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
      <div className="rpt-details-container">
        <h1 className="details-title">Real Property Owner Details</h1>
        <div className="loading-skeleton">
          <div className="skeleton-line skeleton-wide"></div>
          <div className="skeleton-line"></div>
          <div className="skeleton-line skeleton-medium"></div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="rpt-details-container">
        <h1 className="details-title">Real Property Owner Details</h1>
        <div className="error-container">
          <div className="error-icon">⚠️</div>
          <h3 className="error-title">Error Loading Application</h3>
          <p className="error-message">{error}</p>
          <p className="error-id">Application ID: {id}</p>
          <button 
            onClick={handleBack}
            className="back-button"
          >
            ← Back to Applications
          </button>
        </div>
      </div>
    );
  }

  if (!application) {
    return (
      <div className="rpt-details-container">
        <h1 className="details-title">Real Property Owner Details</h1>
        <div className="error-container">
          <div className="error-icon">📄</div>
          <h3 className="error-title">Application Not Found</h3>
          <button 
            onClick={handleBack}
            className="back-button"
          >
            ← Back to Applications
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="rpt-details-container">
      {/* Header */}
      <div className="details-header">
        <button 
          onClick={handleBack}
          className="back-button"
        >
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
              <label className="info-label">Full Name</label>
              <p className="info-value">{application.first_name} {application.middle_name} {application.last_name}</p>
            </div>
            <div className="info-group">
              <label className="info-label">Gender</label>
              <p className="info-value">{application.gender}</p>
            </div>
            <div className="info-group">
              <label className="info-label">Date of Birth</label>
              <p className="info-value">{new Date(application.date_of_birth).toLocaleDateString()}</p>
            </div>
            <div className="info-group">
              <label className="info-label">Civil Status</label>
              <p className="info-value">{application.civil_status}</p>
            </div>
            <div className="info-group">
              <label className="info-label">Contact Number</label>
              <p className="info-value">{application.contact_number}</p>
            </div>
            <div className="info-group">
              <label className="info-label">Email</label>
              <p className="info-value">{application.email}</p>
            </div>
          </div>
        </div>

        {/* Address Information */}
        <div className="details-section">
          <h2 className="section-title">Address Information</h2>
          <div className="info-grid">
            <div className="info-group">
              <label className="info-label">House Number</label>
              <p className="info-value">{application.house_number || 'N/A'}</p>
            </div>
            <div className="info-group">
              <label className="info-label">Street</label>
              <p className="info-value">{application.street || 'N/A'}</p>
            </div>
            <div className="info-group">
              <label className="info-label">Barangay</label>
              <p className="info-value">{application.barangay}</p>
            </div>
            <div className="info-group">
              <label className="info-label">City/Municipality</label>
              <p className="info-value">{application.city}</p>
            </div>
            <div className="info-group">
              <label className="info-label">ZIP Code</label>
              <p className="info-value">{application.zip_code}</p>
            </div>
          </div>
        </div>

        {/* Property Information */}
        <div className="details-section">
          <h2 className="section-title">Property Information</h2>
          <div className="info-grid">
            <div className="info-group">
              <label className="info-label">Application Type</label>
              <p className="info-value">{application.application_type.toUpperCase()}</p>
            </div>
            <div className="info-group">
              <label className="info-label">Property Type</label>
              <p className="info-value">{application.property_type.replace('_', ' ').toUpperCase()}</p>
            </div>
            <div className="info-group full-width">
              <label className="info-label">Property Address</label>
              <p className="info-value">{application.property_address}</p>
            </div>
            <div className="info-group">
              <label className="info-label">Property Barangay</label>
              <p className="info-value">{application.property_barangay}</p>
            </div>
            <div className="info-group">
              <label className="info-label">Property Municipality</label>
              <p className="info-value">{application.property_municipality}</p>
            </div>
            {application.previous_tdn && (
              <div className="info-group">
                <label className="info-label">Previous TDN</label>
                <p className="info-value">{application.previous_tdn}</p>
              </div>
            )}
            {application.previous_owner && (
              <div className="info-group">
                <label className="info-label">Previous Owner</label>
                <p className="info-value">{application.previous_owner}</p>
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
              className={`action-button assessment ${application.status !== 'pending' ? 'disabled' : ''}`}
              disabled={application.status !== 'pending'}
            >
              Mark for Assessment
            </button>
            <button 
              onClick={() => setShowPropertyModal(true)}
              className={`action-button assessed ${application.status !== 'for_assessment' ? 'disabled' : ''}`}
              disabled={application.status !== 'for_assessment'}
            >
              Assess Property
            </button>
            <button 
              onClick={() => handleStatusUpdate('approved')}
              className={`action-button approve ${application.status !== 'assessed' ? 'disabled' : ''}`}
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
          <div className="modal-content modal-wide" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Land Property Assessment</h3>
              <button onClick={() => setShowPropertyModal(false)} className="modal-close">
                ×
              </button>
            </div>
            <div className="modal-body">
              {/* Basic Property Information */}
              <div className="form-section">
                <h4>Property Details</h4>
                <div className="form-row">
                  <div className="form-group">
                    <label>Location *</label>
                    <input
                      type="text"
                      value={propertyData.location}
                      onChange={(e) => handlePropertyDataChange('location', e.target.value)}
                      placeholder="Property location"
                      className="form-input"
                    />
                  </div>
                  <div className="form-group">
                    <label>Barangay *</label>
                    <input
                      type="text"
                      value={propertyData.barangay}
                      onChange={(e) => handlePropertyDataChange('barangay', e.target.value)}
                      placeholder="Barangay"
                      className="form-input"
                    />
                  </div>
                </div>
                <div className="form-row">
                  <div className="form-group">
                    <label>Municipality *</label>
                    <input
                      type="text"
                      value={propertyData.municipality}
                      onChange={(e) => handlePropertyDataChange('municipality', e.target.value)}
                      placeholder="Municipality"
                      className="form-input"
                    />
                  </div>
                  <div className="form-group">
                    <label>TDN Number *</label>
                    <input
                      type="text"
                      value={propertyData.tdn_no}
                      onChange={(e) => handlePropertyDataChange('tdn_no', e.target.value)}
                      placeholder="Tax Declaration Number"
                      className="form-input"
                    />
                  </div>
                </div>
                <div className="form-row">
                  <div className="form-group">
                    <label>Lot Area (sqm) *</label>
                    <input
                      type="number"
                      value={propertyData.lot_area}
                      onChange={(e) => handlePropertyDataChange('lot_area', e.target.value)}
                      placeholder="Enter lot area in square meters"
                      className="form-input"
                      step="0.01"
                      min="0"
                    />
                  </div>
                  <div className="form-group">
                    <label>Land Use *</label>
                    <select
                      value={propertyData.land_use}
                      onChange={(e) => handlePropertyDataChange('land_use', e.target.value)}
                      className="form-input"
                    >
                      <option value="">Select Land Use</option>
                      {landUseOptions.map(option => (
                        <option key={option.land_use} value={option.land_use}>
                          {option.land_use}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
              </div>

              <div className="calculation-note">
                <p><strong>Note:</strong> The system will automatically calculate the assessed value and taxes based on the configured rates when you submit.</p>
              </div>
            </div>
            <div className="modal-footer">
              <button onClick={() => setShowPropertyModal(false)} className="btn-cancel">
                Cancel
              </button>
              <button 
                onClick={handlePropertySubmit}
                className="btn-primary"
                disabled={!propertyData.lot_area || !propertyData.land_use || !propertyData.location || 
                         !propertyData.barangay || !propertyData.municipality || !propertyData.tdn_no}
              >
                Complete Assessment & Approve
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
                        <div className="fallback-message">
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
import React, { useState, useRef, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import "./MapEditor.css";

export default function MapEditor() {
  const { id } = useParams();
  const navigate = useNavigate();
  
  const [mapData, setMapData] = useState(null);
  const [stalls, setStalls] = useState([]);
  const [stallClasses, setStallClasses] = useState([]);
  const [sections, setSections] = useState([]); // All available sections from database
  const [stallCount, setStallCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [selectedStallIndex, setSelectedStallIndex] = useState(null);
  const [modalPos, setModalPos] = useState({ x: 0, y: 0 });

  // Market section filter states
  const [selectedSection, setSelectedSection] = useState("all");

  const marketMapRef = useRef(null);
  const modalRef = useRef(null);
  const API_BASE = "http://localhost/revenue/backend/Market/MarketCreator";

  // Fetch map, stalls, stall classes, and sections data
  useEffect(() => {
    fetchMapData();
    fetchStallClasses();
    fetchSections();
  }, [id]);

  const fetchMapData = async () => {
    try {
      const res = await fetch(`${API_BASE}/map_display.php?map_id=${id}`);
      if (!res.ok) throw new Error(`Network error: ${res.status}`);
      
      const data = await res.json();
      if (data.status === "success") {
        setMapData(data.map);
        setStalls(data.stalls || []);
        setStallCount(data.stalls?.length || 0);
      } else {
        throw new Error(data.message || "Failed to fetch map data");
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const fetchStallClasses = async () => {
    try {
      const res = await fetch(`${API_BASE}/get_stall_rights.php`);
      const data = await res.json();
      if (data.status === "success") {
        setStallClasses(data.classes);
      } else {
        console.error("Failed to fetch stall classes from database");
        setStallClasses([]);
      }
    } catch (err) {
      console.error("Error fetching stall classes:", err);
      setStallClasses([]);
    }
  };

  const fetchSections = async () => {
    try {
      const res = await fetch(`${API_BASE}/get_sections.php`);
      const data = await res.json();
      if (data.status === "success") {
        setSections(data.sections);
      } else {
        console.error("Failed to fetch sections from database");
        setSections([]);
      }
    } catch (err) {
      console.error("Error fetching sections:", err);
      setSections([]);
    }
  };

  // Add a new stall
  const addStall = () => {
    const newCount = stallCount + 1;
    // Use the first available class from database, or empty if none
    const defaultClass = stallClasses.length > 0 ? stallClasses[stallClasses.length - 1] : null;
    
    setStallCount(newCount);
    setStalls([
      ...stalls,
      { 
        name: `Stall ${newCount}`, 
        pos_x: 50, 
        pos_y: 50, 
        status: "available", 
        class_id: defaultClass ? defaultClass.class_id : null,
        class_name: defaultClass ? defaultClass.class_name : "No Class",
        price: defaultClass ? defaultClass.price : 0,
        height: 0, 
        length: 0, 
        width: 0,
        section_id: null, // Use section_id instead of market_section
        isNew: true
      }
    ]);
  };

  // Delete a stall
  const deleteStall = async (index) => {
    const stall = stalls[index];
    if (window.confirm(`Delete ${stall.name}?`)) {
      // If stall has an ID (exists in database), delete from backend
      if (stall.id && !stall.isNew) {
        try {
          const formData = new FormData();
          formData.append("stall_id", stall.id);

          const res = await fetch(`${API_BASE}/delete_stall.php`, {
            method: "POST",
            body: formData
          });
          
          const data = await res.json();
          if (data.status !== "success") {
            throw new Error(data.message || "Failed to delete stall");
          }
        } catch (err) {
          alert("Delete failed: " + err.message);
          return;
        }
      }
      
      // Remove from local state
      const updated = stalls.filter((_, i) => i !== index);
      setStalls(updated);
      setStallCount(updated.length);
    }
  };

  // Toggle maintenance status
  const toggleMaintenance = (index) => {
    const updated = [...stalls];
    const stall = updated[index];
    
    if (stall.status === "maintenance") {
      // If currently in maintenance, revert to available
      stall.status = "available";
    } else {
      // If not in maintenance, set to maintenance
      stall.status = "maintenance";
    }
    
    setStalls(updated);
  };

  // Save updates to backend
  const saveUpdates = async () => {
    try {
      const res = await fetch(`${API_BASE}/update_map.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          map_id: id,
          stalls: stalls
        })
      });
      
      const data = await res.json();
      if (data.status === "success") {
        alert("Map updated successfully!");
        fetchMapData();
      } else {
        throw new Error(data.message || "Failed to update map");
      }
    } catch (err) {
      alert("Update failed: " + err.message);
    }
  };

  // Drag stalls
  const handleDrag = (e, index) => {
    const containerRect = marketMapRef.current.getBoundingClientRect();
    const x = e.clientX - containerRect.left - 31.5;
    const y = e.clientY - containerRect.top - 29;

    const updated = [...stalls];
    updated[index].pos_x = Math.max(0, Math.min(containerRect.width - 63, x));
    updated[index].pos_y = Math.max(0, Math.min(containerRect.height - 58, y));
    setStalls(updated);
  };

  const handleMouseDown = (e, index) => {
    e.preventDefault();
    const onMouseMove = (ev) => handleDrag(ev, index);
    const onMouseUp = () => {
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
    };
    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);
  };

  // Open stall modal for editing
  const openEditModal = (index, e) => {
    e.preventDefault();
    setSelectedStallIndex(index);
    const viewportX = e.clientX;
    const viewportY = e.clientY;
    const modalWidth = 350;
    const modalHeight = 550;

    let x = viewportX;
    let y = viewportY;
    if (x + modalWidth > window.innerWidth) x = window.innerWidth - modalWidth - 10;
    if (y + modalHeight > window.innerHeight) y = window.innerHeight - modalHeight - 10;
    x = Math.max(10, x);
    y = Math.max(10, y);
    setModalPos({ x, y });
    setModalOpen(true);
  };

  const handleBackdropClick = (e) => {
    if (modalRef.current && !modalRef.current.contains(e.target)) {
      setModalOpen(false);
    }
  };

  // Update stall class
  const updateStallClass = (class_id) => {
    const selectedClass = stallClasses.find(cls => cls.class_id == class_id);
    if (selectedClass) {
      const updated = [...stalls];
      updated[selectedStallIndex].class_id = selectedClass.class_id;
      updated[selectedStallIndex].class_name = selectedClass.class_name;
      setStalls(updated);
    }
  };

  // Update price separately
  const updateStallPrice = (price) => {
    const updated = [...stalls];
    updated[selectedStallIndex].price = parseFloat(price) || 0;
    setStalls(updated);
  };

  // Update stall section
  const updateStallSection = (section_id) => {
    const updated = [...stalls];
    updated[selectedStallIndex].section_id = section_id ? parseInt(section_id) : null;
    setStalls(updated);
  };

  // Filter stalls by section
  const filteredStalls = selectedSection === "all" 
    ? stalls 
    : stalls.filter(stall => stall.section_id == selectedSection);

  // Get section name by ID
  const getSectionName = (section_id) => {
    const section = sections.find(s => s.id == section_id);
    return section ? section.name : "No Section";
  };

  if (loading) return (
    <div className="map-editor-container">
      <h1>Loading Map...</h1>
      <p>Please wait while we load the map data.</p>
    </div>
  );

  if (error) return (
    <div className="map-editor-container">
      <h1>Error</h1>
      <p className="error-message">{error}</p>
      <button 
        onClick={() => navigate("/Market/ViewAllMaps")}
        className="back-button"
      >
        Back to Maps
      </button>
    </div>
  );

  return (
    <div className="map-editor-container">
      <div className="header-section">
        <h1>Edit Map: {mapData?.name}</h1>
        <div className="header-buttons">
          <button 
            onClick={() => navigate("/Market/ViewAllMaps")}
            className="btn-secondary"
          >
            Back to Maps
          </button>
          <button 
            onClick={() => navigate(`/Market/MarketOutput/view/${id}`)}
            className="btn-primary"
          >
            View as Customer
          </button>
        </div>
      </div>

      <div className="instructions">
        <p>
          <strong>Instructions:</strong> Drag stalls to reposition. Right-click to edit details. 
          Click the × button to delete stalls. Add new stalls with the button below.
          Click the wrench button to toggle maintenance mode (turns stall gray).
        </p>
      </div>

      {/* Market Section Filter */}
      {stalls.length > 0 && sections.length > 0 && (
        <div className="section-filter">
          <label htmlFor="sectionFilter">Filter by Market Section:</label>
          <select 
            id="sectionFilter"
            value={selectedSection} 
            onChange={(e) => setSelectedSection(e.target.value)}
            className="filter-select"
          >
            <option value="all">All Sections</option>
            {sections.map((section) => (
              <option key={section.id} value={section.id}>
                {section.name}
              </option>
            ))}
          </select>
          <div className="filter-info">
            Showing {filteredStalls.length} of {stalls.length} stalls
            {selectedSection !== "all" && ` in "${getSectionName(selectedSection)}"`}
          </div>
        </div>
      )}

      <div
        ref={marketMapRef}
        className="market-map"
        style={{ 
          backgroundImage: mapData ? `url(http://localhost/revenue/${mapData.image_path})` : "none"
        }}
      >
        {filteredStalls.map((stall, index) => {
          const originalIndex = stalls.findIndex(s => 
            s.id === stall.id && s.name === stall.name
          );
          return (
            <div
              key={stall.id || `new-${index}`}
              className={`stall ${stall.status} ${selectedSection !== "all" ? "filtered" : ""}`}
              style={{ left: stall.pos_x, top: stall.pos_y }}
              onMouseDown={(e) => handleMouseDown(e, originalIndex)}
              onContextMenu={(e) => openEditModal(originalIndex, e)}
            >
              <div className="stall-content">
                <div className="stall-name">{stall.name}</div>
                <div className="stall-class">Class: {stall.class_name}</div>
                <div className="stall-price">₱{stall.price}</div>
                <div className="stall-size">{stall.length}m × {stall.width}m × {stall.height}m</div>
                {stall.section_id && (
                  <div className="stall-section">Section: {getSectionName(stall.section_id)}</div>
                )}
              </div>

              <button
                className="delete-stall-btn"
                onClick={(e) => { e.stopPropagation(); deleteStall(originalIndex); }}
                title="Delete stall"
              >
                ×
              </button>

              <button
                className={`maintenance-stall-btn ${stall.status === "maintenance" ? "active" : ""}`}
                onClick={(e) => { e.stopPropagation(); toggleMaintenance(originalIndex); }}
                title={stall.status === "maintenance" ? "Remove from maintenance" : "Put under maintenance"}
              >
                ⚙️
              </button>
            </div>
          );
        })}
      </div>

      <div className="controls">
        <button onClick={addStall} className="btn-add">Add New Stall</button>
        <button onClick={saveUpdates} className="btn-save">Save Changes</button>
      </div>

      {modalOpen && (
        <div className="modal-backdrop" onClick={handleBackdropClick}>
          <div
            ref={modalRef}
            className="price-modal"
            style={{ left: `${modalPos.x}px`, top: `${modalPos.y}px` }}
            onClick={(e) => e.stopPropagation()}
          >
            <h4>Edit Stall Details - {stalls[selectedStallIndex]?.name}</h4>

            {/* Section Selection Field */}
            <label>Market Section</label>
            <select
              value={stalls[selectedStallIndex]?.section_id || ""}
              onChange={(e) => updateStallSection(e.target.value)}
            >
              <option value="">No Section</option>
              {sections.map((section) => (
                <option key={section.id} value={section.id}>
                  {section.name}
                </option>
              ))}
            </select>

            <label>Stall Class</label>
            <select
              value={stalls[selectedStallIndex]?.class_id || ""}
              onChange={(e) => updateStallClass(e.target.value)}
            >
              <option value="">Select a class...</option>
              {stallClasses.map((cls) => (
                <option key={cls.class_id} value={cls.class_id}>
                  Class {cls.class_name} - ₱{cls.price} ({cls.description})
                </option>
              ))}
            </select>

            <div className="current-class-info">
              <strong>Selected: Class {stalls[selectedStallIndex]?.class_name || "None"}</strong>
              <br />
              <span>Stall Rights: ₱{stallClasses.find(cls => cls.class_id == stalls[selectedStallIndex]?.class_id)?.price || 0}</span>
            </div>

            <label>Custom Price (₱)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.price || 0}
              onChange={(e) => updateStallPrice(e.target.value)}
              step="0.01"
              min="0"
            />

            <label>Height (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.height || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].height = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <label>Length (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.length || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].length = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <label>Width (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.width || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].width = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <div className="modal-buttons">
              <button onClick={() => setModalOpen(false)}>Save</button>
              <button onClick={() => setModalOpen(false)}>Cancel</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
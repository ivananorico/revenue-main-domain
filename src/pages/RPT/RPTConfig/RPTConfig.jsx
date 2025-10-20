import { useState, useEffect } from 'react'

export default function RPTConfig() {
  const [activeTab, setActiveTab] = useState('taxRates')
  const [loading, setLoading] = useState(false)
  const [message, setMessage] = useState({ type: '', text: '' })
  
  // Tax Rates State
  const [taxRates, setTaxRates] = useState({
    tax_rate_id: '',
    tax_rate: '',
    sef_rate: ''
  })

  // Land Rates State
  const [landRates, setLandRates] = useState([])
  const [editingLandRate, setEditingLandRate] = useState(null)
  const [newLandRate, setNewLandRate] = useState({
    land_use: '',
    market_value_per_sqm: '',
    land_assessed_lvl: ''
  })

  // Building Rates State
  const [buildingRates, setBuildingRates] = useState([])
  const [editingBuildingRate, setEditingBuildingRate] = useState(null)
  const [newBuildingRate, setNewBuildingRate] = useState({
    building_type: '',
    construction_type: '',
    market_value_per_sqm: '',
    building_assessed_lvl: ''
  })

  // Fetch configuration data from your database
  useEffect(() => {
    fetchConfigData()
  }, [])

  const showMessage = (type, text) => {
    setMessage({ type, text })
    setTimeout(() => setMessage({ type: '', text: '' }), 5000)
  }

  const fetchConfigData = async () => {
    setLoading(true)
    try {
      const baseURL = 'http://localhost/revenue/backend/RPT/RPTConfig/RPTConfig.php'

      // Fetch Tax Rates
      console.log('=== FETCHING TAX RATES ===')
      const taxResponse = await fetch(`${baseURL}?action=tax-rates`)
      console.log('Tax response status:', taxResponse.status)
      const taxText = await taxResponse.text()
      console.log('Tax response text:', taxText)
      
      if (!taxResponse.ok) {
        throw new Error(`HTTP ${taxResponse.status}: ${taxText}`)
      }
      
      const taxData = JSON.parse(taxText)
      console.log('Tax rates data:', taxData)
      if (taxData.length > 0) {
        setTaxRates(taxData[0])
      }

      // Fetch Land Rates
      console.log('=== FETCHING LAND RATES ===')
      const landResponse = await fetch(`${baseURL}?action=land-rates`)
      console.log('Land response status:', landResponse.status)
      const landText = await landResponse.text()
      console.log('Land response text:', landText)
      
      if (!landResponse.ok) {
        throw new Error(`HTTP ${landResponse.status}: ${landText}`)
      }
      
      const landData = JSON.parse(landText)
      console.log('Land rates data:', landData)
      setLandRates(landData)

      // Fetch Building Rates
      console.log('=== FETCHING BUILDING RATES ===')
      const buildingResponse = await fetch(`${baseURL}?action=building-rates`)
      console.log('Building response status:', buildingResponse.status)
      const buildingText = await buildingResponse.text()
      console.log('Building response text:', buildingText)
      
      if (!buildingResponse.ok) {
        throw new Error(`HTTP ${buildingResponse.status}: ${buildingText}`)
      }
      
      const buildingData = JSON.parse(buildingText)
      console.log('Building rates data:', buildingData)
      setBuildingRates(buildingData)

      showMessage('success', 'Configuration data loaded successfully')

    } catch (error) {
      console.error('Error fetching configuration:', error)
      showMessage('error', `Failed to load configuration data: ${error.message}`)
    } finally {
      setLoading(false)
    }
  }

  // Tax Rates Functions
  const updateTaxRates = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTConfig/RPTConfig.php?action=tax-rates', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(taxRates)
      })

      if (response.ok) {
        const result = await response.json()
        showMessage('success', result.message)
        // Refresh data
        fetchConfigData()
      } else {
        const errorData = await response.json()
        throw new Error(errorData.error || 'Failed to update tax rates')
      }
    } catch (error) {
      console.error('Error updating tax rates:', error)
      showMessage('error', error.message)
    } finally {
      setLoading(false)
    }
  }

  // Land Rates Functions
  const addLandRate = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTConfig/RPTConfig.php?action=land-rates', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(newLandRate)
      })

      if (response.ok) {
        const newRate = await response.json()
        setLandRates([...landRates, newRate])
        setNewLandRate({ land_use: '', market_value_per_sqm: '', land_assessed_lvl: '' })
        showMessage('success', 'Land rate added successfully')
      } else {
        const errorData = await response.json()
        throw new Error(errorData.error || 'Failed to add land rate')
      }
    } catch (error) {
      console.error('Error adding land rate:', error)
      showMessage('error', error.message)
    } finally {
      setLoading(false)
    }
  }

  const updateLandRate = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTConfig/RPTConfig.php?action=land-rates', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(editingLandRate)
      })

      if (response.ok) {
        setLandRates(landRates.map(rate => 
          rate.land_rate_id === editingLandRate.land_rate_id ? editingLandRate : rate
        ))
        setEditingLandRate(null)
        showMessage('success', 'Land rate updated successfully')
      } else {
        const errorData = await response.json()
        throw new Error(errorData.error || 'Failed to update land rate')
      }
    } catch (error) {
      console.error('Error updating land rate:', error)
      showMessage('error', error.message)
    } finally {
      setLoading(false)
    }
  }

  const deleteLandRate = async (landRateId) => {
    if (!confirm('Are you sure you want to delete this land rate?')) return
    
    setLoading(true)
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTConfig/RPTConfig.php?action=land-rates', {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ land_rate_id: landRateId })
      })

      if (response.ok) {
        setLandRates(landRates.filter(rate => rate.land_rate_id !== landRateId))
        showMessage('success', 'Land rate deleted successfully')
      } else {
        const errorData = await response.json()
        throw new Error(errorData.error || 'Failed to delete land rate')
      }
    } catch (error) {
      console.error('Error deleting land rate:', error)
      showMessage('error', error.message)
    } finally {
      setLoading(false)
    }
  }

  // Building Rates Functions
  const addBuildingRate = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTConfig/RPTConfig.php?action=building-rates', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(newBuildingRate)
      })

      if (response.ok) {
        const newRate = await response.json()
        setBuildingRates([...buildingRates, newRate])
        setNewBuildingRate({ 
          building_type: '', 
          construction_type: '', 
          market_value_per_sqm: '', 
          building_assessed_lvl: '' 
        })
        showMessage('success', 'Building rate added successfully')
      } else {
        const errorData = await response.json()
        throw new Error(errorData.error || 'Failed to add building rate')
      }
    } catch (error) {
      console.error('Error adding building rate:', error)
      showMessage('error', error.message)
    } finally {
      setLoading(false)
    }
  }

  const updateBuildingRate = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTConfig/RPTConfig.php?action=building-rates', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(editingBuildingRate)
      })

      if (response.ok) {
        setBuildingRates(buildingRates.map(rate => 
          rate.building_rate_id === editingBuildingRate.building_rate_id ? editingBuildingRate : rate
        ))
        setEditingBuildingRate(null)
        showMessage('success', 'Building rate updated successfully')
      } else {
        const errorData = await response.json()
        throw new Error(errorData.error || 'Failed to update building rate')
      }
    } catch (error) {
      console.error('Error updating building rate:', error)
      showMessage('error', error.message)
    } finally {
      setLoading(false)
    }
  }

  const deleteBuildingRate = async (buildingRateId) => {
    if (!confirm('Are you sure you want to delete this building rate?')) return
    
    setLoading(true)
    try {
      const response = await fetch('http://localhost/revenue/backend/RPT/RPTConfig/RPTConfig.php?action=building-rates', {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ building_rate_id: buildingRateId })
      })

      if (response.ok) {
        setBuildingRates(buildingRates.filter(rate => rate.building_rate_id !== buildingRateId))
        showMessage('success', 'Building rate deleted successfully')
      } else {
        const errorData = await response.json()
        throw new Error(errorData.error || 'Failed to delete building rate')
      }
    } catch (error) {
      console.error('Error deleting building rate:', error)
      showMessage('error', error.message)
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
        <div className="flex justify-center items-center h-64">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
        </div>
      </div>
    )
  }

  return (
    <div className="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
      <h1 className="text-2xl font-bold mb-6">Real Property Tax Configuration</h1>

      {/* Message Alert */}
      {message.text && (
        <div className={`mb-4 p-4 rounded-lg ${
          message.type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
        }`}>
          {message.text}
        </div>
      )}

      {/* Tabs */}
      <div className="mb-6 border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          {['taxRates', 'landRates', 'buildingRates'].map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm ${
                activeTab === tab
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              {tab === 'taxRates' && 'Tax Rates'}
              {tab === 'landRates' && 'Land Rates'}
              {tab === 'buildingRates' && 'Building Rates'}
            </button>
          ))}
        </nav>
      </div>

      {/* Tax Rates Tab */}
      {activeTab === 'taxRates' && (
        <div>
          <h2 className="text-xl font-semibold mb-4">Tax Rate Configuration</h2>
          <form onSubmit={updateTaxRates} className="max-w-md space-y-4">
            <div>
              <label className="block text-sm font-medium mb-2">Basic Tax Rate (%)</label>
              <input
                type="number"
                step="0.01"
                min="0"
                max="100"
                value={taxRates.tax_rate || ''}
                onChange={(e) => setTaxRates({...taxRates, tax_rate: parseFloat(e.target.value)})}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-2">SEF Rate (%)</label>
              <input
                type="number"
                step="0.01"
                min="0"
                max="100"
                value={taxRates.sef_rate || ''}
                onChange={(e) => setTaxRates({...taxRates, sef_rate: parseFloat(e.target.value)})}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600"
                required
              />
            </div>
            <button
              type="submit"
              className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              Update Tax Rates
            </button>
          </form>
        </div>
      )}

      {/* Land Rates Tab */}
      {activeTab === 'landRates' && (
        <div>
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-xl font-semibold">Land Rate Configuration</h2>
          </div>

          {/* Add New Land Rate Form */}
          <form onSubmit={addLandRate} className="mb-6 p-4 border border-gray-200 rounded-lg dark:border-slate-600">
            <h3 className="text-lg font-medium mb-3">Add New Land Rate</h3>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Land Use</label>
                <input
                  type="text"
                  value={newLandRate.land_use}
                  onChange={(e) => setNewLandRate({...newLandRate, land_use: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., Residential"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Market Value per sqm (₱)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={newLandRate.market_value_per_sqm}
                  onChange={(e) => setNewLandRate({...newLandRate, market_value_per_sqm: parseFloat(e.target.value)})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600"
                  placeholder="1500.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Assessment Level</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  max="1"
                  value={newLandRate.land_assessed_lvl}
                  onChange={(e) => setNewLandRate({...newLandRate, land_assessed_lvl: parseFloat(e.target.value)})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.20"
                  required
                />
              </div>
            </div>
            <button
              type="submit"
              className="mt-4 bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
            >
              Add Land Rate
            </button>
          </form>

          {/* Land Rates Table */}
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
              <thead className="bg-gray-50 dark:bg-slate-800">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Land Use</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Market Value/sqm</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment Level</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200 dark:bg-slate-900 dark:divide-slate-600">
                {landRates.map((rate) => (
                  <tr key={rate.land_rate_id}>
                    {editingLandRate?.land_rate_id === rate.land_rate_id ? (
                      <>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <input
                            type="text"
                            value={editingLandRate.land_use}
                            onChange={(e) => setEditingLandRate({...editingLandRate, land_use: e.target.value})}
                            className="w-full px-2 py-1 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                          />
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <input
                            type="number"
                            step="0.01"
                            value={editingLandRate.market_value_per_sqm}
                            onChange={(e) => setEditingLandRate({...editingLandRate, market_value_per_sqm: parseFloat(e.target.value)})}
                            className="w-full px-2 py-1 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                          />
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <input
                            type="number"
                            step="0.01"
                            value={editingLandRate.land_assessed_lvl}
                            onChange={(e) => setEditingLandRate({...editingLandRate, land_assessed_lvl: parseFloat(e.target.value)})}
                            className="w-full px-2 py-1 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                          />
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap space-x-2">
                          <button
                            onClick={updateLandRate}
                            className="text-green-600 hover:text-green-900"
                          >
                            Save
                          </button>
                          <button
                            onClick={() => setEditingLandRate(null)}
                            className="text-gray-600 hover:text-gray-900"
                          >
                            Cancel
                          </button>
                        </td>
                      </>
                    ) : (
                      <>
                        <td className="px-6 py-4 whitespace-nowrap">{rate.land_use}</td>
                        <td className="px-6 py-4 whitespace-nowrap">₱{rate.market_value_per_sqm?.toLocaleString()}</td>
                        <td className="px-6 py-4 whitespace-nowrap">{rate.land_assessed_lvl ? (rate.land_assessed_lvl * 100).toFixed(1) + '%' : ''}</td>
                        <td className="px-6 py-4 whitespace-nowrap space-x-2">
                          <button
                            onClick={() => setEditingLandRate(rate)}
                            className="text-blue-600 hover:text-blue-900"
                          >
                            Edit
                          </button>
                          <button
                            onClick={() => deleteLandRate(rate.land_rate_id)}
                            className="text-red-600 hover:text-red-900"
                          >
                            Delete
                          </button>
                        </td>
                      </>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Building Rates Tab */}
      {activeTab === 'buildingRates' && (
        <div>
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-xl font-semibold">Building Rate Configuration</h2>
          </div>

          {/* Add New Building Rate Form */}
          <form onSubmit={addBuildingRate} className="mb-6 p-4 border border-gray-200 rounded-lg dark:border-slate-600">
            <h3 className="text-lg font-medium mb-3">Add New Building Rate</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Building Type</label>
                <input
                  type="text"
                  value={newBuildingRate.building_type}
                  onChange={(e) => setNewBuildingRate({...newBuildingRate, building_type: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., Residential"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Construction Type</label>
                <input
                  type="text"
                  value={newBuildingRate.construction_type}
                  onChange={(e) => setNewBuildingRate({...newBuildingRate, construction_type: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., Concrete"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Market Value per sqm (₱)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={newBuildingRate.market_value_per_sqm}
                  onChange={(e) => setNewBuildingRate({...newBuildingRate, market_value_per_sqm: parseFloat(e.target.value)})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600"
                  placeholder="8000.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Assessment Level</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  max="1"
                  value={newBuildingRate.building_assessed_lvl}
                  onChange={(e) => setNewBuildingRate({...newBuildingRate, building_assessed_lvl: parseFloat(e.target.value)})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.40"
                  required
                />
              </div>
            </div>
            <button
              type="submit"
              className="mt-4 bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
            >
              Add Building Rate
            </button>
          </form>

          {/* Building Rates Table */}
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
              <thead className="bg-gray-50 dark:bg-slate-800">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Building Type</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Construction Type</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Market Value/sqm</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment Level</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200 dark:bg-slate-900 dark:divide-slate-600">
                {buildingRates.map((rate) => (
                  <tr key={rate.building_rate_id}>
                    {editingBuildingRate?.building_rate_id === rate.building_rate_id ? (
                      <>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <input
                            type="text"
                            value={editingBuildingRate.building_type}
                            onChange={(e) => setEditingBuildingRate({...editingBuildingRate, building_type: e.target.value})}
                            className="w-full px-2 py-1 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                          />
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <input
                            type="text"
                            value={editingBuildingRate.construction_type}
                            onChange={(e) => setEditingBuildingRate({...editingBuildingRate, construction_type: e.target.value})}
                            className="w-full px-2 py-1 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                          />
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <input
                            type="number"
                            step="0.01"
                            value={editingBuildingRate.market_value_per_sqm}
                            onChange={(e) => setEditingBuildingRate({...editingBuildingRate, market_value_per_sqm: parseFloat(e.target.value)})}
                            className="w-full px-2 py-1 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                          />
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <input
                            type="number"
                            step="0.01"
                            value={editingBuildingRate.building_assessed_lvl}
                            onChange={(e) => setEditingBuildingRate({...editingBuildingRate, building_assessed_lvl: parseFloat(e.target.value)})}
                            className="w-full px-2 py-1 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                          />
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap space-x-2">
                          <button
                            onClick={updateBuildingRate}
                            className="text-green-600 hover:text-green-900"
                          >
                            Save
                          </button>
                          <button
                            onClick={() => setEditingBuildingRate(null)}
                            className="text-gray-600 hover:text-gray-900"
                          >
                            Cancel
                          </button>
                        </td>
                      </>
                    ) : (
                      <>
                        <td className="px-6 py-4 whitespace-nowrap">{rate.building_type}</td>
                        <td className="px-6 py-4 whitespace-nowrap">{rate.construction_type}</td>
                        <td className="px-6 py-4 whitespace-nowrap">₱{rate.market_value_per_sqm?.toLocaleString()}</td>
                        <td className="px-6 py-4 whitespace-nowrap">{rate.building_assessed_lvl ? (rate.building_assessed_lvl * 100).toFixed(1) + '%' : ''}</td>
                        <td className="px-6 py-4 whitespace-nowrap space-x-2">
                          <button
                            onClick={() => setEditingBuildingRate(rate)}
                            className="text-blue-600 hover:text-blue-900"
                          >
                            Edit
                          </button>
                          <button
                            onClick={() => deleteBuildingRate(rate.building_rate_id)}
                            className="text-red-600 hover:text-red-900"
                          >
                            Delete
                          </button>
                        </td>
                      </>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  )
}
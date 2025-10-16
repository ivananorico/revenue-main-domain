import { useState } from 'react'
import { Routes, Route, useLocation } from 'react-router-dom'
import Sidebar from './components/sidebar/sidebar'
import Dashboard from './pages/Dashboard'
import GeneralSettings from './pages/settings/General'
import SecuritySettings from './pages/settings/Security'
import Header from './components/header/Header'
import sidebarItems from './components/sidebar/sidebarItems'

import RPT1 from './pages/RPT/RPT1'
import RPT2 from './pages/RPT/RPT2'

import Business1 from './pages/Business/Business1'
import Business2 from './pages/Business/Business2'



import Treasury1 from './pages/Treasury/Treasury1'
import Treasury2 from './pages/Treasury/Treasury2'
import Treasury3 from './pages/Treasury/Treasury3'

import Digital1 from './pages/Digital/Digital1'
import Digital2 from './pages/Digital/Digital2'
import Digital3 from './pages/Digital/Digital3'

import MarketCreator from './pages/Market/MapCreator/MapCreator'
import MarketOutput from './pages/Market/MapCreator/MarketOutput'
import ViewAllMaps from './pages/Market/MapCreator/ViewAllMaps'
import MapEditor from './pages/Market/MapCreator/MapEditor'

import RentApproval from './pages/Market/RentApproval/RentApproval'
import RenterDetails from './pages/Market/RentApproval/RenterDetails'

import RenterRent from './pages/Market/RenterRent/RenterRent'
import RenterStatus from './pages/Market/RenterRent/RenterStatus'




function App() {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false)
  const location = useLocation()

  // Helper to find breadcrumb path from sidebarItems
  function getBreadcrumb() {
    for (const item of sidebarItems) {
      if (item.path === location.pathname) return [item.label]
      if (item.subItems) {
        const sub = item.subItems.find(sub => sub.path === location.pathname)
        if (sub) return [item.label, sub.label]
      }
    }
    return ['Dashboard']
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-slate-800 dark:via-slate-800 dark:to-slate-800 transition-colors duration-200">
      <div className='flex h-screen overflow-hidden'>
        <Sidebar collapsed={sidebarCollapsed} />
        <div className='flex-1 flex flex-col'>
          <Header
            sidebarCollapsed={sidebarCollapsed}
            onToggleSidebar={() => setSidebarCollapsed(!sidebarCollapsed)}
            breadcrumb={getBreadcrumb()}
          />
          <main className="flex-1 overflow-auto p-8 dark:bg-slate-800">
            <Routes>
              <Route path="/dashboard" element={<Dashboard />} />
              <Route path="/RPT/RPT1" element={<RPT1 />} />
              <Route path="/RPT/RPT2" element={<RPT2 />} />
             

              <Route path="/Business/Business1" element={<Business1 />} />
              <Route path="/Business/Business2" element={<Business2 />} />


              
              <Route path="/Treasury/Treasury1" element={<Treasury1 />} />
              <Route path="/Treasury/Treasury2" element={<Treasury2 />} />
              <Route path="/Treasury/Treasury3" element={<Treasury3 />} />

              <Route path="/Digital/Digital1" element={<Digital1 />} />
              <Route path="/Digital/Digital2" element={<Digital2 />} />
              <Route path="/Digital/Digital3" element={<Digital3 />} />

              <Route path="/Market/MarketCreator" element={<MarketCreator />} />
              <Route path="/Market/MarketOutput/view/:id" element={<MarketOutput />} />
              <Route path="/Market/ViewAllMaps" element={<ViewAllMaps />} />
              <Route path="/Market/MapEditor/:id" element={<MapEditor />} />

              <Route path="/Market/RentApproval" element={<RentApproval />} />
              <Route path="/Market/RenterDetails/:id" element={<RenterDetails />} />

              <Route path="/Market/RenterRent" element={<RenterRent />} />
              <Route path="/Market/RenterStatus/:id" element={<RenterStatus />} />


              <Route path="/settings/general" element={<GeneralSettings />} />
              <Route path="/settings/security" element={<SecuritySettings />} />
            </Routes>
          </main>
        </div>
      </div>
    </div>
  )
}

export default App
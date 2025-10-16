import Dashboard from './pages/Dashboard'
import Module1 from './pages/RPT/Module'
import RPT1 from './pages/RPT/RPT1'
import Business1 from './pages/Business/Business1'
import Treasury1 from './pages/Treasury/Treasury1'
import Digital1 from './pages/Digital/Digital1'
import Digital1 from './pages/Market/Market1'
import GeneralSettings from './pages/settings/General'
import SecuritySettings from './pages/settings/Security'

const routes = [
  {
    path: '/dashboard',
    element: <Dashboard />,
  },
  {
    path: '/RPT/rpt1',
    element: <RPT1 />,
  },
 {
  path: '/Business/Business1',
  element: <Business1/>,
 },
 {
  path: '/Treasury/Treasury1',
  element: <Treasury1/>,
 },
  {
  path: '/Digital/Digital1',
  element: <Digital1/>,
 },
  {
  path: '/Market/Market1',
  element: <Market1/>,
 },
  {
    path: '/settings/general',
    element: <GeneralSettings />,
  },
  {
    path: '/settings/security',
    element: <SecuritySettings />,
  },
]

export default routes
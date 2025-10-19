import { LayoutDashboard, Settings, Users, FileText } from 'lucide-react'

const sidebarItems = [
  {
    id: "dashboard",
    label: "Dashboard",
    icon: LayoutDashboard,
    path: "/dashboard",
  },
  {
    id: "module1",
    label: "Real Property Tax Collecion System",
    icon: LayoutDashboard,
    subItems: [
       {
        id: "submodule1",
        label: "Real Property Dashboard",
        path: "/RPT/RPTDash"
      },

      {
        id: "submodule1",
        label: "Real Property Assessment",
        path: "/RPT/RPTAssess"
      },

      
    ]
  },
 
{
    id: "module2",
    label: "Business Tax and Regulatory Fee Payment",
    icon: LayoutDashboard,
    subItems: [
      {
        id: "submodule2",
        label: "Business Tax & Regulatory Assessment",
        path: "/Business/Business1"
      },
      {
        id: "submodule2",
        label: "Business Tax & Regulatory Fee Dashboard",
        path: "/Business/Business2"
      },

    ]
  },
  {
    id: "module3",
    label: "Treasury Dashboard & Report",
    icon: LayoutDashboard,
    subItems: [
      {
        id: "submodule3",
        label: "Tax Collection Dashboard",
        path: "/Treasury/Revenue"
      },
      {
        id: "submodule3",
        label: "Disbursement",
        path: "/Treasury/Disbursement"
      },
      {
        id: "submodule3",
        label: "Report",
        path: "/Treasury/Report"
      }
    ]
  },
  {
    id: "module4",
    label: "Digital Payment Integration",
    icon: LayoutDashboard,
    subItems: [
      {
        id: "submodule4",
        label: "Real Property Tax Receipt",
        path: "/Digital/Digital1"
      },
      {
        id: "submodule4",
        label: "Business Tax & Regulatory Fee Receipt",
        path: "/Digital/Digital2"
      },
      {
        id: "submodule4",
        label: "Market Stall Rental Receitp",
        path: "/Digital/MarketDig"
      }
    ]
  },
  {
    id: "module5",
    label: "Market Stall Rental & Billing",
    icon: LayoutDashboard,
    subItems: [
      {
        id: "submodule5",
        label: "Market Stall Map Creator  ",
        path: "/Market/MarketCreator"
      },
      {
        id: "submodule5",
        label: "Market Approval",
        path: "/Market/RentApproval"
      },
       {
        id: "submodule5",
        label: "Market Rent Status",
        path: "/Market/RenterRent"
      },
     
    ]
  },
  {
    id: "settings",
    label: "Settings",
    icon: Settings,
    subItems: [
      {
        id: "general-settings",
        label: "General",
        path: "/settings/general"
      },
      {
        id: "security-settings",
        label: "Security",
        path: "/settings/security"
      }
    ]
  }
]

export default sidebarItems
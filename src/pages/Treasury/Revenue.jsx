import { useState, useEffect } from 'react';
import { 
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
  PieChart, Pie, Cell, LineChart, Line, AreaChart, Area
} from 'recharts';

export default function Revenue() {
  const [selectedYear, setSelectedYear] = useState(2025);
  const [revenueData, setRevenueData] = useState({});
  const [loading, setLoading] = useState(false);

  // Mock data based on your actual database sections
  const mockRevenueData = {
    2023: {
      businessTax: 1250000,
      rptTax: 850000,
      stallRental: 15680000,
      monthly: [
        { month: 'Jan', businessTax: 98000, rptTax: 65000, stallRental: 1250000, applications: 8 },
        { month: 'Feb', businessTax: 102000, rptTax: 68000, stallRental: 1280000, applications: 7 },
        { month: 'Mar', businessTax: 115000, rptTax: 72000, stallRental: 1320000, applications: 9 },
        { month: 'Apr', businessTax: 108000, rptTax: 71000, stallRental: 1290000, applications: 6 },
        { month: 'May', businessTax: 112000, rptTax: 69000, stallRental: 1350000, applications: 10 },
        { month: 'Jun', businessTax: 105000, rptTax: 75000, stallRental: 1220000, applications: 8 },
        { month: 'Jul', businessTax: 118000, rptTax: 73000, stallRental: 1450000, applications: 11 },
        { month: 'Aug', businessTax: 122000, rptTax: 77000, stallRental: 1480000, applications: 12 },
        { month: 'Sep', businessTax: 110000, rptTax: 71000, stallRental: 1360000, applications: 9 },
        { month: 'Oct', businessTax: 125000, rptTax: 79000, stallRental: 1520000, applications: 13 },
        { month: 'Nov', businessTax: 118000, rptTax: 76000, stallRental: 1420000, applications: 10 },
        { month: 'Dec', businessTax: 135000, rptTax: 82000, stallRental: 1650000, applications: 15 }
      ],
      // Based on your actual sections from database
      sections: [
        { name: 'Food Stalls', value: 2850000, applications: 25, color: '#0088FE' },
        { name: 'Food Court', value: 2450000, applications: 18, color: '#00C49F' },
        { name: 'Meat Section', value: 1950000, applications: 15, color: '#FFBB28' },
        { name: 'Fish Section', value: 1850000, applications: 12, color: '#FF8042' },
        { name: 'Vegetables Section', value: 1650000, applications: 22, color: '#8884D8' },
        { name: 'Fruits Section', value: 1550000, applications: 20, color: '#82CA9D' },
        { name: 'Dry Goods', value: 1350000, applications: 18, color: '#FF6B6B' },
        { name: 'Bakery Section', value: 1250000, applications: 15, color: '#4ECDC4' },
        { name: 'Clothing Section', value: 950000, applications: 12, color: '#45B7D1' },
        { name: 'Electronics Section', value: 850000, applications: 8, color: '#96CEB4' }
      ],
      stallClasses: [
        { name: 'Class A (Premium)', value: 6500000, stalls: 25, color: '#FF6B6B' },
        { name: 'Class B (Standard)', value: 5800000, stalls: 45, color: '#4ECDC4' },
        { name: 'Class C (Economy)', value: 3380000, stalls: 60, color: '#45B7D1' }
      ]
    },
    2024: {
      businessTax: 1420000,
      rptTax: 920000,
      stallRental: 17850000,
      monthly: [
        { month: 'Jan', businessTax: 112000, rptTax: 72000, stallRental: 1420000, applications: 9 },
        { month: 'Feb', businessTax: 118000, rptTax: 75000, stallRental: 1450000, applications: 8 },
        { month: 'Mar', businessTax: 125000, rptTax: 78000, stallRental: 1520000, applications: 11 },
        { month: 'Apr', businessTax: 122000, rptTax: 76000, stallRental: 1480000, applications: 7 },
        { month: 'May', businessTax: 128000, rptTax: 82000, stallRental: 1580000, applications: 12 },
        { month: 'Jun', businessTax: 135000, rptTax: 85000, stallRental: 1620000, applications: 10 },
        { month: 'Jul', businessTax: 142000, rptTax: 88000, stallRental: 1750000, applications: 14 },
        { month: 'Aug', businessTax: 138000, rptTax: 90000, stallRental: 1680000, applications: 13 },
        { month: 'Sep', businessTax: 145000, rptTax: 92000, stallRental: 1720000, applications: 11 },
        { month: 'Oct', businessTax: 152000, rptTax: 95000, stallRental: 1850000, applications: 16 },
        { month: 'Nov', businessTax: 148000, rptTax: 93000, stallRental: 1780000, applications: 14 },
        { month: 'Dec', businessTax: 165000, rptTax: 98000, stallRental: 2050000, applications: 18 }
      ],
      sections: [
        { name: 'Food Stalls', value: 3250000, applications: 28, color: '#0088FE' },
        { name: 'Food Court', value: 2850000, applications: 22, color: '#00C49F' },
        { name: 'Meat Section', value: 2250000, applications: 18, color: '#FFBB28' },
        { name: 'Fish Section', value: 2150000, applications: 15, color: '#FF8042' },
        { name: 'Vegetables Section', value: 1950000, applications: 25, color: '#8884D8' },
        { name: 'Fruits Section', value: 1850000, applications: 23, color: '#82CA9D' },
        { name: 'Dry Goods', value: 1650000, applications: 20, color: '#FF6B6B' },
        { name: 'Bakery Section', value: 1550000, applications: 18, color: '#4ECDC4' },
        { name: 'Clothing Section', value: 1150000, applications: 15, color: '#45B7D1' },
        { name: 'Electronics Section', value: 1050000, applications: 10, color: '#96CEB4' }
      ],
      stallClasses: [
        { name: 'Class A (Premium)', value: 7500000, stalls: 28, color: '#FF6B6B' },
        { name: 'Class B (Standard)', value: 6500000, stalls: 50, color: '#4ECDC4' },
        { name: 'Class C (Economy)', value: 3850000, stalls: 65, color: '#45B7D1' }
      ]
    },
    2025: {
      businessTax: 1890000,
      rptTax: 1150000,
      stallRental: 22500000,
      monthly: [
        { month: 'Jan', businessTax: 145000, rptTax: 85000, stallRental: 1750000, applications: 12 },
        { month: 'Feb', businessTax: 152000, rptTax: 88000, stallRental: 1820000, applications: 11 },
        { month: 'Mar', businessTax: 165000, rptTax: 92000, stallRental: 1950000, applications: 15 },
        { month: 'Apr', businessTax: 158000, rptTax: 90000, stallRental: 1880000, applications: 10 },
        { month: 'May', businessTax: 172000, rptTax: 95000, stallRental: 2050000, applications: 16 },
        { month: 'Jun', businessTax: 168000, rptTax: 98000, stallRental: 1980000, applications: 14 },
        { month: 'Jul', businessTax: 185000, rptTax: 105000, stallRental: 2250000, applications: 18 },
        { month: 'Aug', businessTax: 192000, rptTax: 108000, stallRental: 2350000, applications: 20 },
        { month: 'Sep', businessTax: 178000, rptTax: 102000, stallRental: 2150000, applications: 16 },
        { month: 'Oct', businessTax: 195000, rptTax: 112000, stallRental: 2450000, applications: 22 },
        { month: 'Nov', businessTax: 188000, rptTax: 110000, stallRental: 2320000, applications: 19 },
        { month: 'Dec', businessTax: 206000, rptTax: 120000, stallRental: 2550000, applications: 25 }
      ],
      sections: [
        { name: 'Food Stalls', value: 3850000, applications: 32, color: '#0088FE' },
        { name: 'Food Court', value: 3450000, applications: 26, color: '#00C49F' },
        { name: 'Meat Section', value: 2850000, applications: 22, color: '#FFBB28' },
        { name: 'Fish Section', value: 2650000, applications: 18, color: '#FF8042' },
        { name: 'Vegetables Section', value: 2350000, applications: 28, color: '#8884D8' },
        { name: 'Fruits Section', value: 2250000, applications: 26, color: '#82CA9D' },
        { name: 'Dry Goods', value: 1950000, applications: 23, color: '#FF6B6B' },
        { name: 'Bakery Section', value: 1850000, applications: 21, color: '#4ECDC4' },
        { name: 'Clothing Section', value: 1450000, applications: 18, color: '#45B7D1' },
        { name: 'Electronics Section', value: 1350000, applications: 12, color: '#96CEB4' }
      ],
      stallClasses: [
        { name: 'Class A (Premium)', value: 9500000, stalls: 35, color: '#FF6B6B' },
        { name: 'Class B (Standard)', value: 8500000, stalls: 60, color: '#4ECDC4' },
        { name: 'Class C (Economy)', value: 4500000, stalls: 75, color: '#45B7D1' }
      ]
    }
  };

  useEffect(() => {
    // Simulate API call
    setLoading(true);
    setTimeout(() => {
      setRevenueData(mockRevenueData[selectedYear] || mockRevenueData[2025]);
      setLoading(false);
    }, 500);
  }, [selectedYear]);

  const currentData = revenueData;
  const totalRevenue = (currentData.businessTax || 0) + (currentData.rptTax || 0) + (currentData.stallRental || 0);

  if (loading) {
    return (
      <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
        <div className="text-2xl font-bold mb-4">Revenue Dashboard</div>
        <div className="flex justify-center items-center h-64">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
        </div>
      </div>
    );
  }

  return (
    <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
      <h1 className="text-2xl font-bold mb-6">Market Revenue Dashboard</h1>
      
      {/* Year Selector */}
      <div className="mb-6">
        <label className="block text-sm font-medium mb-2">Select Year:</label>
        <select 
          value={selectedYear} 
          onChange={(e) => setSelectedYear(parseInt(e.target.value))}
          className="dark:bg-slate-800 dark:text-white border border-gray-300 rounded px-3 py-2"
        >
          <option value={2023}>2023</option>
          <option value={2024}>2024</option>
          <option value={2025}>2025</option>
        </select>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div className="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg">
          <h3 className="text-lg font-semibold text-blue-800 dark:text-blue-200">Total Revenue</h3>
          <p className="text-2xl font-bold text-blue-600 dark:text-blue-300">
            ₱{totalRevenue.toLocaleString()}
          </p>
        </div>
        <div className="bg-green-50 dark:bg-green-900 p-4 rounded-lg">
          <h3 className="text-lg font-semibold text-green-800 dark:text-green-200">Business Tax</h3>
          <p className="text-2xl font-bold text-green-600 dark:text-green-300">
            ₱{(currentData.businessTax || 0).toLocaleString()}
          </p>
        </div>
        <div className="bg-purple-50 dark:bg-purple-900 p-4 rounded-lg">
          <h3 className="text-lg font-semibold text-purple-800 dark:text-purple-200">RPT Tax</h3>
          <p className="text-2xl font-bold text-purple-600 dark:text-purple-300">
            ₱{(currentData.rptTax || 0).toLocaleString()}
          </p>
        </div>
        <div className="bg-orange-50 dark:bg-orange-900 p-4 rounded-lg">
          <h3 className="text-lg font-semibold text-orange-800 dark:text-orange-200">Stall Rental</h3>
          <p className="text-2xl font-bold text-orange-600 dark:text-orange-300">
            ₱{(currentData.stallRental || 0).toLocaleString()}
          </p>
        </div>
      </div>

      {/* Monthly Revenue Trend */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div className="bg-white dark:bg-slate-800 p-4 rounded-lg shadow">
          <h3 className="text-lg font-semibold mb-4">Monthly Revenue Trend - {selectedYear}</h3>
          <ResponsiveContainer width="100%" height={300}>
            <AreaChart data={currentData.monthly || []}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="month" />
              <YAxis />
              <Tooltip formatter={(value) => `₱${value.toLocaleString()}`} />
              <Legend />
              <Area type="monotone" dataKey="businessTax" stackId="1" stroke="#10B981" fill="#10B981" fillOpacity={0.6} name="Business Tax" />
              <Area type="monotone" dataKey="rptTax" stackId="1" stroke="#8B5CF6" fill="#8B5CF6" fillOpacity={0.6} name="RPT Tax" />
              <Area type="monotone" dataKey="stallRental" stackId="1" stroke="#F59E0B" fill="#F59E0B" fillOpacity={0.6} name="Stall Rental" />
            </AreaChart>
          </ResponsiveContainer>
        </div>

        {/* Revenue by Section */}
        <div className="bg-white dark:bg-slate-800 p-4 rounded-lg shadow">
          <h3 className="text-lg font-semibold mb-4">Revenue by Market Section</h3>
          <ResponsiveContainer width="100%" height={300}>
            <PieChart>
              <Pie
                data={currentData.sections || []}
                cx="50%"
                cy="50%"
                labelLine={false}
                label={({ name, percent }) => `${name} (${(percent * 100).toFixed(0)}%)`}
                outerRadius={80}
                fill="#8884d8"
                dataKey="value"
              >
                {(currentData.sections || []).map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Pie>
              <Tooltip formatter={(value) => `₱${value.toLocaleString()}`} />
            </PieChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Additional Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Revenue by Stall Class */}
        <div className="bg-white dark:bg-slate-800 p-4 rounded-lg shadow">
          <h3 className="text-lg font-semibold mb-4">Revenue by Stall Class</h3>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={currentData.stallClasses || []}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="name" />
              <YAxis />
              <Tooltip formatter={(value) => `₱${value.toLocaleString()}`} />
              <Bar dataKey="value" name="Revenue">
                {(currentData.stallClasses || []).map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Monthly Applications */}
        <div className="bg-white dark:bg-slate-800 p-4 rounded-lg shadow">
          <h3 className="text-lg font-semibold mb-4">Monthly Applications - {selectedYear}</h3>
          <ResponsiveContainer width="100%" height={300}>
            <LineChart data={currentData.monthly || []}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="month" />
              <YAxis />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="applications" stroke="#EF4444" strokeWidth={2} name="Applications" />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>
    </div>
  );
}
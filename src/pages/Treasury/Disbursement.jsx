import { useState, useEffect } from 'react';
import { 
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
  PieChart, Pie, Cell, LineChart, Line, AreaChart, Area
} from 'recharts';

export default function Disbursement() {
  const [selectedYear, setSelectedYear] = useState(2025);
  const [disbursementData, setDisbursementData] = useState({});
  const [loading, setLoading] = useState(false);

  // Mock disbursement data
  const mockDisbursementData = {
    2023: {
      totalDisbursed: 3850000,
      financialAid: 2450000,
      scholarships: 1400000,
      monthly: [
        { month: 'Jan', financialAid: 185000, scholarships: 95000, total: 280000 },
        { month: 'Feb', financialAid: 192000, scholarships: 88000, total: 280000 },
        { month: 'Mar', financialAid: 210000, scholarships: 110000, total: 320000 },
        { month: 'Apr', financialAid: 198000, scholarships: 102000, total: 300000 },
        { month: 'May', financialAid: 225000, scholarships: 125000, total: 350000 },
        { month: 'Jun', financialAid: 205000, scholarships: 115000, total: 320000 },
        { month: 'Jul', financialAid: 235000, scholarships: 135000, total: 370000 },
        { month: 'Aug', financialAid: 248000, scholarships: 142000, total: 390000 },
        { month: 'Sep', financialAid: 220000, scholarships: 120000, total: 340000 },
        { month: 'Oct', financialAid: 255000, scholarships: 145000, total: 400000 },
        { month: 'Nov', financialAid: 242000, scholarships: 138000, total: 380000 },
        { month: 'Dec', financialAid: 275000, scholarships: 165000, total: 440000 }
      ],
      financialAidBreakdown: [
        { category: 'Small Business Grants', amount: 850000, beneficiaries: 45, color: '#0088FE' },
        { category: 'Market Vendor Support', amount: 680000, beneficiaries: 38, color: '#00C49F' },
        { category: 'Livelihood Programs', amount: 520000, beneficiaries: 52, color: '#FFBB28' },
        { category: 'Emergency Assistance', amount: 250000, beneficiaries: 25, color: '#FF8042' },
        { category: 'Skills Training', amount: 150000, beneficiaries: 30, color: '#8884D8' }
      ],
      scholarshipBreakdown: [
        { category: 'College Scholarships', amount: 650000, students: 32, color: '#0088FE' },
        { category: 'High School Grants', amount: 380000, students: 45, color: '#00C49F' },
        { category: 'Vocational Training', amount: 220000, students: 28, color: '#FFBB28' },
        { category: 'Elementary Support', amount: 150000, students: 60, color: '#FF8042' }
      ],
      beneficiaries: [
        { type: 'Market Vendors', count: 120, averageAmount: 12500 },
        { type: 'Students', count: 165, averageAmount: 8500 },
        { type: 'Small Business', count: 45, averageAmount: 18800 },
        { type: 'Training Participants', count: 82, averageAmount: 6200 }
      ]
    },
    2024: {
      totalDisbursed: 4520000,
      financialAid: 2850000,
      scholarships: 1670000,
      monthly: [
        { month: 'Jan', financialAid: 215000, scholarships: 105000, total: 320000 },
        { month: 'Feb', financialAid: 228000, scholarships: 112000, total: 340000 },
        { month: 'Mar', financialAid: 245000, scholarships: 125000, total: 370000 },
        { month: 'Apr', financialAid: 232000, scholarships: 118000, total: 350000 },
        { month: 'May', financialAid: 258000, scholarships: 142000, total: 400000 },
        { month: 'Jun', financialAid: 265000, scholarships: 135000, total: 400000 },
        { month: 'Jul', financialAid: 285000, scholarships: 155000, total: 440000 },
        { month: 'Aug', financialAid: 298000, scholarships: 162000, total: 460000 },
        { month: 'Sep', financialAid: 275000, scholarships: 145000, total: 420000 },
        { month: 'Oct', financialAid: 312000, scholarships: 178000, total: 490000 },
        { month: 'Nov', financialAid: 295000, scholarships: 165000, total: 460000 },
        { month: 'Dec', financialAid: 342000, scholarships: 198000, total: 540000 }
      ],
      financialAidBreakdown: [
        { category: 'Small Business Grants', amount: 1050000, beneficiaries: 52, color: '#0088FE' },
        { category: 'Market Vendor Support', amount: 820000, beneficiaries: 45, color: '#00C49F' },
        { category: 'Livelihood Programs', amount: 580000, beneficiaries: 58, color: '#FFBB28' },
        { category: 'Emergency Assistance', amount: 280000, beneficiaries: 28, color: '#FF8042' },
        { category: 'Skills Training', amount: 120000, beneficiaries: 35, color: '#8884D8' }
      ],
      scholarshipBreakdown: [
        { category: 'College Scholarships', amount: 780000, students: 38, color: '#0088FE' },
        { category: 'High School Grants', amount: 450000, students: 52, color: '#00C49F' },
        { category: 'Vocational Training', amount: 280000, students: 32, color: '#FFBB28' },
        { category: 'Elementary Support', amount: 160000, students: 65, color: '#FF8042' }
      ],
      beneficiaries: [
        { type: 'Market Vendors', count: 145, averageAmount: 13200 },
        { type: 'Students', count: 187, averageAmount: 8900 },
        { type: 'Small Business', count: 52, averageAmount: 20100 },
        { type: 'Training Participants', count: 93, averageAmount: 6500 }
      ]
    },
    2025: {
      totalDisbursed: 5250000,
      financialAid: 3250000,
      scholarships: 2000000,
      monthly: [
        { month: 'Jan', financialAid: 245000, scholarships: 125000, total: 370000 },
        { month: 'Feb', financialAid: 258000, scholarships: 132000, total: 390000 },
        { month: 'Mar', financialAid: 285000, scholarships: 145000, total: 430000 },
        { month: 'Apr', financialAid: 272000, scholarships: 138000, total: 410000 },
        { month: 'May', financialAid: 298000, scholarships: 162000, total: 460000 },
        { month: 'Jun', financialAid: 312000, scholarships: 158000, total: 470000 },
        { month: 'Jul', financialAid: 335000, scholarships: 185000, total: 520000 },
        { month: 'Aug', financialAid: 348000, scholarships: 192000, total: 540000 },
        { month: 'Sep', financialAid: 325000, scholarships: 175000, total: 500000 },
        { month: 'Oct', financialAid: 362000, scholarships: 208000, total: 570000 },
        { month: 'Nov', financialAid: 345000, scholarships: 195000, total: 540000 },
        { month: 'Dec', financialAid: 395000, scholarships: 235000, total: 630000 }
      ],
      financialAidBreakdown: [
        { category: 'Small Business Grants', amount: 1250000, beneficiaries: 62, color: '#0088FE' },
        { category: 'Market Vendor Support', amount: 950000, beneficiaries: 52, color: '#00C49F' },
        { category: 'Livelihood Programs', amount: 650000, beneficiaries: 65, color: '#FFBB28' },
        { category: 'Emergency Assistance', amount: 320000, beneficiaries: 32, color: '#FF8042' },
        { category: 'Skills Training', amount: 180000, beneficiaries: 45, color: '#8884D8' }
      ],
      scholarshipBreakdown: [
        { category: 'College Scholarships', amount: 950000, students: 45, color: '#0088FE' },
        { category: 'High School Grants', amount: 550000, students: 62, color: '#00C49F' },
        { category: 'Vocational Training', amount: 350000, students: 40, color: '#FFBB28' },
        { category: 'Elementary Support', amount: 150000, students: 75, color: '#FF8042' }
      ],
      beneficiaries: [
        { type: 'Market Vendors', count: 168, averageAmount: 14200 },
        { type: 'Students', count: 222, averageAmount: 9200 },
        { type: 'Small Business', count: 62, averageAmount: 21500 },
        { type: 'Training Participants', count: 110, averageAmount: 6800 }
      ]
    }
  };

  useEffect(() => {
    setLoading(true);
    setTimeout(() => {
      setDisbursementData(mockDisbursementData[selectedYear] || mockDisbursementData[2025]);
      setLoading(false);
    }, 500);
  }, [selectedYear]);

  const currentData = disbursementData;

  if (loading) {
    return (
      <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
        <div className="text-2xl font-bold mb-4">Disbursement Dashboard</div>
        <div className="flex justify-center items-center h-64">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
        </div>
      </div>
    );
  }

  return (
    <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
      <h1 className="text-2xl font-bold mb-6">Disbursement Dashboard</h1>
      
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
          <h3 className="text-sm font-medium text-blue-800 dark:text-blue-200">Total Disbursed</h3>
          <p className="text-2xl font-bold text-blue-600 dark:text-blue-300">
            ₱{(currentData.totalDisbursed || 0).toLocaleString()}
          </p>
        </div>
        <div className="bg-green-50 dark:bg-green-900 p-4 rounded-lg">
          <h3 className="text-sm font-medium text-green-800 dark:text-green-200">Financial Aid</h3>
          <p className="text-2xl font-bold text-green-600 dark:text-green-300">
            ₱{(currentData.financialAid || 0).toLocaleString()}
          </p>
        </div>
        <div className="bg-purple-50 dark:bg-purple-900 p-4 rounded-lg">
          <h3 className="text-sm font-medium text-purple-800 dark:text-purple-200">Scholarships</h3>
          <p className="text-2xl font-bold text-purple-600 dark:text-purple-300">
            ₱{(currentData.scholarships || 0).toLocaleString()}
          </p>
        </div>
        <div className="bg-orange-50 dark:bg-orange-900 p-4 rounded-lg">
          <h3 className="text-sm font-medium text-orange-800 dark:text-orange-200">Total Beneficiaries</h3>
          <p className="text-2xl font-bold text-orange-600 dark:text-orange-300">
            {(currentData.beneficiaries || []).reduce((sum, b) => sum + b.count, 0)}
          </p>
        </div>
      </div>

      {/* Monthly Disbursement Trend */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div className="bg-white dark:bg-slate-800 p-4 rounded-lg shadow">
          <h3 className="text-lg font-semibold mb-4">Monthly Disbursement Trend - {selectedYear}</h3>
          <ResponsiveContainer width="100%" height={300}>
            <AreaChart data={currentData.monthly || []}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="month" />
              <YAxis />
              <Tooltip formatter={(value) => `₱${value.toLocaleString()}`} />
              <Legend />
              <Area type="monotone" dataKey="financialAid" stackId="1" stroke="#10B981" fill="#10B981" fillOpacity={0.6} name="Financial Aid" />
              <Area type="monotone" dataKey="scholarships" stackId="1" stroke="#8B5CF6" fill="#8B5CF6" fillOpacity={0.6} name="Scholarships" />
            </AreaChart>
          </ResponsiveContainer>
        </div>

        {/* Financial Aid Breakdown */}
        <div className="bg-white dark:bg-slate-800 p-4 rounded-lg shadow">
          <h3 className="text-lg font-semibold mb-4">Financial Aid Breakdown</h3>
          <ResponsiveContainer width="100%" height={300}>
            <PieChart>
              <Pie
                data={currentData.financialAidBreakdown || []}
                cx="50%"
                cy="50%"
                labelLine={false}
                label={({ category, percent }) => `${category} (${(percent * 100).toFixed(0)}%)`}
                outerRadius={80}
                fill="#8884d8"
                dataKey="amount"
              >
                {(currentData.financialAidBreakdown || []).map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Pie>
              <Tooltip formatter={(value) => `₱${value.toLocaleString()}`} />
            </PieChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Additional Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {/* Scholarship Breakdown */}
        <div className="bg-white dark:bg-slate-800 p-4 rounded-lg shadow">
          <h3 className="text-lg font-semibold mb-4">Scholarship Breakdown</h3>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={currentData.scholarshipBreakdown || []}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="category" />
              <YAxis />
              <Tooltip formatter={(value) => `₱${value.toLocaleString()}`} />
              <Bar dataKey="amount" name="Amount">
                {(currentData.scholarshipBreakdown || []).map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Beneficiaries Overview */}
        <div className="bg-white dark:bg-slate-800 p-4 rounded-lg shadow">
          <h3 className="text-lg font-semibold mb-4">Beneficiaries Overview</h3>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={currentData.beneficiaries || []}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="type" />
              <YAxis yAxisId="left" />
              <YAxis yAxisId="right" orientation="right" />
              <Tooltip />
              <Legend />
              <Bar yAxisId="left" dataKey="count" fill="#0088FE" name="Number of Beneficiaries" />
              <Line yAxisId="right" type="monotone" dataKey="averageAmount" stroke="#FF8042" name="Average Amount (₱)" />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Detailed Breakdown Tables */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Financial Aid Details */}
        <div className="bg-white dark:bg-slate-800 p-4 rounded-lg shadow">
          <h3 className="text-lg font-semibold mb-4">Financial Aid Programs</h3>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead>
                <tr>
                  <th className="px-4 py-2 text-left text-sm font-medium">Program</th>
                  <th className="px-4 py-2 text-left text-sm font-medium">Amount</th>
                  <th className="px-4 py-2 text-left text-sm font-medium">Beneficiaries</th>
                </tr>
              </thead>
              <tbody>
                {(currentData.financialAidBreakdown || []).map((program, index) => (
                  <tr key={index} className="border-b dark:border-gray-700">
                    <td className="px-4 py-2 text-sm">{program.category}</td>
                    <td className="px-4 py-2 text-sm font-medium">₱{program.amount.toLocaleString()}</td>
                    <td className="px-4 py-2 text-sm">{program.beneficiaries}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Scholarship Details */}
        <div className="bg-white dark:bg-slate-800 p-4 rounded-lg shadow">
          <h3 className="text-lg font-semibold mb-4">Scholarship Programs</h3>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead>
                <tr>
                  <th className="px-4 py-2 text-left text-sm font-medium">Program</th>
                  <th className="px-4 py-2 text-left text-sm font-medium">Amount</th>
                  <th className="px-4 py-2 text-left text-sm font-medium">Students</th>
                </tr>
              </thead>
              <tbody>
                {(currentData.scholarshipBreakdown || []).map((program, index) => (
                  <tr key={index} className="border-b dark:border-gray-700">
                    <td className="px-4 py-2 text-sm">{program.category}</td>
                    <td className="px-4 py-2 text-sm font-medium">₱{program.amount.toLocaleString()}</td>
                    <td className="px-4 py-2 text-sm">{program.students}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
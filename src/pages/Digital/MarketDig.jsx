import { useState, useEffect } from 'react';

export default function MarketDig() {
  const [paymentLogs, setPaymentLogs] = useState([]);
  const [filter, setFilter] = useState('all');
  const [loading, setLoading] = useState(true);

  // Mock payment audit logs with different renter names
  const mockPaymentLogs = [
    {
      id: 1,
      renterId: 'R2025100023',
      renterName: 'Juan Dela Cruz',
      businessName: 'Merienda Corner',
      stallNumber: 'Stall 1',
      paymentType: 'monthly_rent',
      amount: 516.13,
      referenceNumber: 'GCASH-1760645362-8946',
      paymentMethod: 'gcash',
      status: 'paid',
      description: 'Monthly Rent - October 2025 (Prorated)',
      timestamp: '2025-10-17 04:09:22',
      market: 'Nitang',
      section: 'Bakery Section'
    },
    {
      id: 2,
      renterId: 'R2025100023',
      renterName: 'Juan Dela Cruz',
      businessName: 'Merienda Corner',
      stallNumber: 'Stall 1',
      paymentType: 'monthly_rent',
      amount: 1000.00,
      referenceNumber: 'GCASH-1760680925-7683',
      paymentMethod: 'gcash',
      status: 'paid',
      description: 'Monthly Rent - November 2025',
      timestamp: '2025-10-17 14:02:05',
      market: 'Nitang',
      section: 'Bakery Section'
    },
    {
      id: 3,
      renterId: 'R2025100023',
      renterName: 'Juan Dela Cruz',
      businessName: 'Merienda Corner',
      stallNumber: 'Stall 1',
      paymentType: 'application_fee',
      amount: 15100.00,
      referenceNumber: 'GCASH-1760644513-8482',
      paymentMethod: 'gcash',
      status: 'paid',
      description: 'Application Fee + Security Bond + Stall Rights',
      timestamp: '2025-10-17 03:55:13',
      market: 'Nitang',
      section: 'Bakery Section'
    },
    {
      id: 4,
      renterId: 'R2025100024',
      renterName: 'Maria Santos',
      businessName: 'Fresh Fruits',
      stallNumber: 'Stall 2',
      paymentType: 'monthly_rent',
      amount: 1500.00,
      referenceNumber: 'BANK-1760651234-5678',
      paymentMethod: 'bank_transfer',
      status: 'paid',
      description: 'Monthly Rent - October 2025',
      timestamp: '2025-10-16 10:30:15',
      market: 'Nitang',
      section: 'Fruits Section'
    },
    {
      id: 5,
      renterId: 'R2025100025',
      renterName: 'Pedro Reyes',
      businessName: 'Reyes Hardware',
      stallNumber: 'Stall 3',
      paymentType: 'monthly_rent',
      amount: 2000.00,
      referenceNumber: 'GCASH-1760654321-9876',
      paymentMethod: 'gcash',
      status: 'paid',
      description: 'Monthly Rent - October 2025',
      timestamp: '2025-10-16 09:15:42',
      market: 'Quezon City',
      section: 'Hardware Section'
    },
    {
      id: 6,
      renterId: 'R2025100026',
      renterName: 'Ana Lopez',
      businessName: 'Lopez Clothing',
      stallNumber: 'Stall 4',
      paymentType: 'application_fee',
      amount: 25100.00,
      referenceNumber: 'BANK-1760655555-1111',
      paymentMethod: 'bank_transfer',
      status: 'paid',
      description: 'Application Fee + Security Bond + Stall Rights (Class A)',
      timestamp: '2025-10-15 16:45:33',
      market: 'Quezon City',
      section: 'Clothing Section'
    },
    {
      id: 7,
      renterId: 'R2025100027',
      renterName: 'Carlos Garcia',
      businessName: 'Garcia Electronics',
      stallNumber: 'Stall 5',
      paymentType: 'monthly_rent',
      amount: 1800.00,
      referenceNumber: 'GCASH-1760649999-2222',
      paymentMethod: 'gcash',
      status: 'pending',
      description: 'Monthly Rent - October 2025',
      timestamp: '2025-10-15 14:20:18',
      market: 'Quezon City',
      section: 'Electronics Section'
    },
    {
      id: 8,
      renterId: 'R2025100028',
      renterName: 'Elena Torres',
      businessName: 'Torres Seafood',
      stallNumber: 'Stall 6',
      paymentType: 'monthly_rent',
      amount: 2200.00,
      referenceNumber: 'BANK-1760648888-3333',
      paymentMethod: 'bank_transfer',
      status: 'overdue',
      description: 'Monthly Rent - September 2025',
      timestamp: '2025-09-30 11:10:25',
      market: 'Nitang',
      section: 'Seafood Section'
    },
    {
      id: 9,
      renterId: 'R2025100029',
      renterName: 'Roberto Lim',
      businessName: 'Lim Dry Goods',
      stallNumber: 'Stall 7',
      paymentType: 'monthly_rent',
      amount: 1200.00,
      referenceNumber: 'GCASH-1760637777-4444',
      paymentMethod: 'gcash',
      status: 'paid',
      description: 'Monthly Rent - October 2025',
      timestamp: '2025-10-14 08:45:12',
      market: 'Nitang',
      section: 'Dry Goods'
    },
    {
      id: 10,
      renterId: 'R2025100030',
      renterName: 'Sofia Mendoza',
      businessName: 'Mendoza Beauty',
      stallNumber: 'Stall 8',
      paymentType: 'application_fee',
      amount: 20100.00,
      referenceNumber: 'BANK-1760636666-5555',
      paymentMethod: 'bank_transfer',
      status: 'paid',
      description: 'Application Fee + Security Bond + Stall Rights (Class B)',
      timestamp: '2025-10-13 13:35:47',
      market: 'Quezon City',
      section: 'Beauty Products'
    }
  ];

  useEffect(() => {
    // Simulate API call
    setTimeout(() => {
      setPaymentLogs(mockPaymentLogs);
      setLoading(false);
    }, 1000);
  }, []);

  const filteredLogs = filter === 'all' 
    ? paymentLogs 
    : paymentLogs.filter(log => log.paymentType === filter);

  const getStatusColor = (status) => {
    switch (status) {
      case 'paid': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
      case 'pending': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
      case 'overdue': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
      default: return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }
  };

  const getPaymentTypeLabel = (type) => {
    switch (type) {
      case 'monthly_rent': return 'Monthly Rent';
      case 'application_fee': return 'Application Fee';
      default: return type;
    }
  };

  if (loading) {
    return (
      <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
        <h1 className="text-2xl font-bold mb-4">Payment Receipts & Audit Logs</h1>
        <div className="flex justify-center items-center h-32">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        </div>
      </div>
    );
  }

  return (
    <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
      <h1 className="text-2xl font-bold mb-6">Payment Receipts & Audit Logs</h1>
      
      {/* Filters */}
      <div className="mb-6 flex flex-wrap gap-4">
        <button
          onClick={() => setFilter('all')}
          className={`px-4 py-2 rounded-lg ${
            filter === 'all' 
              ? 'bg-blue-500 text-white' 
              : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
          }`}
        >
          All Payments
        </button>
        <button
          onClick={() => setFilter('monthly_rent')}
          className={`px-4 py-2 rounded-lg ${
            filter === 'monthly_rent' 
              ? 'bg-blue-500 text-white' 
              : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
          }`}
        >
          Monthly Rent
        </button>
        <button
          onClick={() => setFilter('application_fee')}
          className={`px-4 py-2 rounded-lg ${
            filter === 'application_fee' 
              ? 'bg-blue-500 text-white' 
              : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
          }`}
        >
          Application Fees
        </button>
      </div>

      {/* Payment Logs Table */}
      <div className="bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Renter & Business
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Payment Details
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Amount
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Date & Time
                </th>
              </tr>
            </thead>
            <tbody className="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-gray-700">
              {filteredLogs.map((payment) => (
                <tr key={payment.id} className="hover:bg-gray-50 dark:hover:bg-slate-700">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-medium text-gray-900 dark:text-white">
                      {payment.renterName}
                    </div>
                    <div className="text-sm text-gray-500 dark:text-gray-400">
                      {payment.businessName}
                    </div>
                    <div className="text-xs text-gray-400 dark:text-gray-500">
                      {payment.market} • {payment.stallNumber}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900 dark:text-white">
                      {getPaymentTypeLabel(payment.paymentType)}
                    </div>
                    <div className="text-sm text-gray-500 dark:text-gray-400">
                      {payment.description}
                    </div>
                    <div className="text-xs text-gray-400 dark:text-gray-500">
                      Ref: {payment.referenceNumber}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-semibold text-gray-900 dark:text-white">
                      ₱{payment.amount.toLocaleString()}
                    </div>
                    <div className="text-xs text-gray-500 dark:text-gray-400 capitalize">
                      {payment.paymentMethod}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(payment.status)}`}>
                      {payment.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    {new Date(payment.timestamp).toLocaleDateString()}
                    <br />
                    {new Date(payment.timestamp).toLocaleTimeString()}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Summary Statistics */}
      <div className="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg">
          <h3 className="text-sm font-medium text-blue-800 dark:text-blue-200">Total Payments</h3>
          <p className="text-2xl font-bold text-blue-600 dark:text-blue-300">
            {filteredLogs.length}
          </p>
        </div>
        <div className="bg-green-50 dark:bg-green-900 p-4 rounded-lg">
          <h3 className="text-sm font-medium text-green-800 dark:text-green-200">Total Collected</h3>
          <p className="text-2xl font-bold text-green-600 dark:text-green-300">
            ₱{filteredLogs.filter(p => p.status === 'paid').reduce((sum, p) => sum + p.amount, 0).toLocaleString()}
          </p>
        </div>
        <div className="bg-yellow-50 dark:bg-yellow-900 p-4 rounded-lg">
          <h3 className="text-sm font-medium text-yellow-800 dark:text-yellow-200">Pending</h3>
          <p className="text-2xl font-bold text-yellow-600 dark:text-yellow-300">
            {filteredLogs.filter(p => p.status === 'pending').length}
          </p>
        </div>
        <div className="bg-red-50 dark:bg-red-900 p-4 rounded-lg">
          <h3 className="text-sm font-medium text-red-800 dark:text-red-200">Overdue</h3>
          <p className="text-2xl font-bold text-red-600 dark:text-red-300">
            {filteredLogs.filter(p => p.status === 'overdue').length}
          </p>
        </div>
      </div>
    </div>
  );
}
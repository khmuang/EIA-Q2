import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Layout, Monitor, Key, Plus, LogOut, User, X, Calendar, Hash, Users, ChevronDown, ChevronUp, Search, Filter, Edit2, Trash2, ShieldCheck, AlertTriangle, Clock } from 'lucide-react';
import { format, isValid, differenceInDays } from 'date-fns';

const API_URL = 'http://localhost:3001/api';

interface License {
  id: number;
  name: string;
  licenseKey: string | null;
  totalSeats: number;
  startDate: string | null;
  expiryDate: string | null;
  status: string;
  usageLogs: { id: number; userName: string; machineName: string; startedAt: string }[];
}

interface Stats {
  totalLicenses: number;
  activeUsers: number;
  expiredCount: number;
  expiringSoonCount: number;
}

function App() {
  const [licenses, setLicenses] = useState<License[]>([]);
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [activeTab, setActiveTab] = useState<'inventory' | 'history'>('inventory');
  const [history, setHistory] = useState<any[]>([]);
  
  const [searchTerm, setSearchTerm] = useState('');
  const [filterStatus, setFilterStatus] = useState('All');

  const [modalType, setModalType] = useState<'add' | 'edit' | null>(null);
  const [currentLicenseId, setCurrentLicenseId] = useState<number | null>(null);
  const [showCheckoutModal, setShowCheckoutModal] = useState<{show: boolean, licenseId: number | null}>({show: false, licenseId: null});

  const [formData, setFormData] = useState({ 
    name: '', 
    licenseKey: '', 
    totalSeats: 1, 
    startDate: '', 
    expiryDate: '', 
    status: 'Active' 
  });
  const [checkoutInfo, setCheckoutInfo] = useState({ userName: '', machineName: '' });

  const fetchData = async () => {
    try {
      const [licensesRes, statsRes, historyRes] = await Promise.all([
        axios.get(`${API_URL}/licenses`),
        axios.get(`${API_URL}/stats`),
        axios.get(`${API_URL}/history`)
      ]);
      setLicenses(licensesRes.data);
      setStats(statsRes.data);
      setHistory(historyRes.data);
      setLoading(false);
    } catch (error) { console.error(error); setLoading(false); }
  };

  useEffect(() => { fetchData(); }, []);

  const filteredLicenses = licenses.filter(l => {
    const matchesSearch = l.name.toLowerCase().includes(searchTerm.toLowerCase()) || (l.licenseKey || '').toLowerCase().includes(searchTerm.toLowerCase());
    const matchesStatus = filterStatus === 'All' || l.status === filterStatus;
    return matchesSearch && matchesStatus;
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      if (modalType === 'add') {
        await axios.post(`${API_URL}/licenses`, formData);
      } else if (currentLicenseId) {
        console.log('>>> Sending PUT to:', `${API_URL}/licenses/${currentLicenseId}`, formData);
        const res = await axios.put(`${API_URL}/licenses/${currentLicenseId}`, formData);
        console.log('>>> PUT Response:', res.data);
      }
      
      setModalType(null);
      setCurrentLicenseId(null);
      
      // หน่วงเวลา 300ms เพื่อให้ SQLite เขียนไฟล์เสร็จชัวร์ๆ ก่อนโหลดใหม่
      setTimeout(() => {
        fetchData();
      }, 300);
      
    } catch (error: any) { 
      console.error('Submit error:', error);
      alert('Operation failed: ' + (error.response?.data?.error || error.message)); 
      setLoading(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (window.confirm('Are you sure you want to delete this license?')) {
      try { await axios.delete(`${API_URL}/licenses/${id}`); fetchData(); } catch (error) { alert('Delete failed'); }
    }
  };

  const openEdit = (license: License) => {
    setCurrentLicenseId(license.id);
    setFormData({
      name: license.name,
      licenseKey: license.licenseKey || '',
      totalSeats: license.totalSeats,
      startDate: license.startDate ? license.startDate.substring(0, 10) : '',
      expiryDate: license.expiryDate ? license.expiryDate.substring(0, 10) : '',
      status: license.status
    });
    setModalType('edit');
  };

  const handleEndUsage = async (logId: number) => {
    try { await axios.post(`${API_URL}/usage/end/${logId}`); fetchData(); } catch (error) { alert('Failed'); }
  };

  const formatDateString = (dateStr: string | null) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return isValid(date) ? format(date, 'dd/MM/yyyy') : '-';
  };

  const getExpiryStatus = (date: string | null) => {
    if (!date) return 'none';
    const days = differenceInDays(new Date(date), new Date());
    if (days < 0) return 'expired';
    if (days < 30) return 'warning';
    return 'good';
  };

  return (
    <div className="app-container">
      <aside className="sidebar">
        <div className="logo"><Monitor size={24} /> <span>License Monitor</span></div>
        <nav>
          <a href="#" className={activeTab === 'inventory' ? 'active' : ''} onClick={() => setActiveTab('inventory')}><Layout size={18} /> Inventory</a>
          <a href="#" className={activeTab === 'history' ? 'active' : ''} onClick={() => setActiveTab('history')}><Clock size={18} /> Usage History</a>
        </nav>
      </aside>

      <main className="main-content">
        <header>
          <div className="header-title">
            <h1>{activeTab === 'inventory' ? 'License Inventory' : 'Usage History'}</h1>
            <p>Manage and track your software assets efficiently.</p>
          </div>
          {activeTab === 'inventory' && (
            <button className="btn-primary" onClick={() => { setModalType('add'); setCurrentLicenseId(null); setFormData({name:'', licenseKey:'', totalSeats:1, startDate:'', expiryDate:'', status:'Active'}); }}>
              <Plus size={18} /> Add New License
            </button>
          )}
        </header>

        {stats && activeTab === 'inventory' && (
          <div className="stats-bar">
            <div className="stat-card">
              <div className="stat-icon blue"><ShieldCheck size={20} /></div>
              <div className="stat-info"><span>Total Licenses</span><strong>{stats.totalLicenses}</strong></div>
            </div>
            <div className="stat-card">
              <div className="stat-icon green"><Users size={20} /></div>
              <div className="stat-info"><span>Active Users</span><strong>{stats.activeUsers}</strong></div>
            </div>
            <div className="stat-card">
              <div className="stat-icon orange"><AlertTriangle size={20} /></div>
              <div className="stat-info"><span>Expiring Soon</span><strong>{stats.expiringSoonCount}</strong></div>
            </div>
            <div className="stat-card">
              <div className="stat-icon red"><AlertTriangle size={20} /></div>
              <div className="stat-info"><span>Expired</span><strong>{stats.expiredCount}</strong></div>
            </div>
          </div>
        )}

        {activeTab === 'inventory' ? (
          <>
            <div className="toolbar">
              <div className="search-box"><Search size={18} /><input type="text" placeholder="Search by name or key..." value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} /></div>
              <div className="filter-box">
                <Filter size={18} />
                <select value={filterStatus} onChange={(e) => setFilterStatus(e.target.value)}>
                  <option value="All">All Status</option>
                  <option value="Active">Active</option>
                  <option value="Expired">Expired</option>
                </select>
              </div>
            </div>

            <div className="table-container">
              <table className="license-table">
                <thead>
                  <tr>
                    <th>Software Name</th>
                    <th>License Key</th>
                    <th>Duration (Start - End)</th>
                    <th>Capacity (Used/Total)</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredLicenses.map(license => {
                    const expiry = getExpiryStatus(license.expiryDate);
                    const isFull = license.usageLogs.length >= license.totalSeats;
                    return (
                      <React.Fragment key={license.id}>
                        <tr className={`${expandedId === license.id ? 'row-expanded' : ''} ${expiry === 'expired' ? 'row-danger' : ''}`}>
                          <td className="col-name" onClick={() => setExpandedId(expandedId === license.id ? null : license.id)} style={{cursor:'pointer'}}>
                            <div className="name-wrapper">
                              {expandedId === license.id ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
                              <strong>{license.name}</strong>
                            </div>
                          </td>
                          <td><code>{license.licenseKey || '-'}</code></td>
                          <td>{formatDateString(license.startDate)} - {formatDateString(license.expiryDate)}</td>
                          <td>
                            <div className="capacity-bar-mini">
                              <span className={isFull ? 'text-red' : ''}>{license.usageLogs.length} / {license.totalSeats}</span>
                              <div className="progress-mini">
                                <div className={`progress-fill ${isFull ? 'bg-red' : ''}`} style={{ width: `${(license.usageLogs.length / license.totalSeats) * 100}%` }}></div>
                              </div>
                            </div>
                          </td>
                          <td>
                            <span className={`status-pill ${license.status.toLowerCase()} ${expiry === 'warning' && license.status === 'Active' ? 'warning' : ''} ${expiry === 'expired' && license.status === 'Active' ? 'expired' : ''}`}>
                              {license.status}
                              {license.status === 'Active' && expiry === 'warning' && ' (Expiring)'}
                              {license.status === 'Active' && expiry === 'expired' && ' (Expired)'}
                            </span>
                          </td>
                          <td className="col-actions">
                            <div className="action-buttons">
                              <button className="btn-icon" onClick={() => openEdit(license)} title="Edit"><Edit2 size={16} /></button>
                              <button className="btn-icon delete" onClick={() => handleDelete(license.id)} title="Delete"><Trash2 size={16} /></button>
                              {!isFull && (
                                <button className="btn-table-checkout" onClick={() => setShowCheckoutModal({show:true, licenseId:license.id})}>
                                  Check-out
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                        {expandedId === license.id && (
                          <tr className="user-detail-row">
                            <td colSpan={6}>
                              <div className="user-list-expanded">
                                <h4>Current Active Users</h4>
                                {license.usageLogs.length === 0 ? <p className="no-users">No active users.</p> : (
                                  <div className="user-tags-container">
                                    {license.usageLogs.map(log => (
                                      <div key={log.id} className="user-tag">
                                        <User size={12} />
                                        <span>{log.userName} ({log.machineName})</span>
                                        <button className="btn-tag-end" onClick={() => handleEndUsage(log.id)}><LogOut size={12} /></button>
                                      </div>
                                    ))}
                                  </div>
                                )}
                              </div>
                            </td>
                          </tr>
                        )}
                      </React.Fragment>
                    );
                  })}
                </tbody>
              </table>
              {filteredLicenses.length === 0 && <div className="empty-state">No licenses found.</div>}
            </div>
          </>
        ) : (
          <div className="table-container">
            <table className="license-table">
              <thead>
                <tr>
                  <th>Software</th>
                  <th>User Name</th>
                  <th>Machine Name</th>
                  <th>Started At</th>
                  <th>Ended At</th>
                </tr>
              </thead>
              <tbody>
                {history.map(log => (
                  <tr key={log.id}>
                    <td><strong>{log.license.name}</strong></td>
                    <td>{log.userName}</td>
                    <td>{log.machineName}</td>
                    <td>{format(new Date(log.startedAt), 'dd/MM/yyyy HH:mm')}</td>
                    <td>{log.endedAt ? format(new Date(log.endedAt), 'dd/MM/yyyy HH:mm') : <span className="text-green">Currently Active</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </main>

      {/* Modal: Add/Edit License (Complete Version) */}
      {modalType && (
        <div className="modal-overlay">
          <div className="modal">
            <div className="modal-header">
              <h2>{modalType === 'add' ? 'Add New License' : 'Edit License Details'}</h2>
              <button onClick={() => setModalType(null)}><X /></button>
            </div>
            <form onSubmit={handleSubmit}>
              <div className="form-group">
                <label>Software Name*</label>
                <input type="text" required value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} placeholder="e.g. AutoCAD 2025" />
              </div>
              <div className="form-group">
                <label>License Key (Optional)</label>
                <input type="text" value={formData.licenseKey} onChange={e => setFormData({...formData, licenseKey: e.target.value})} placeholder="XXXX-XXXX-XXXX" />
              </div>
              <div className="form-row" style={{ display: 'flex', gap: '15px' }}>
                <div className="form-group" style={{ flex: 1 }}>
                  <label>Start Date</label>
                  <input type="date" value={formData.startDate} onChange={e => setFormData({...formData, startDate: e.target.value})} />
                </div>
                <div className="form-group" style={{ flex: 1 }}>
                  <label>Expiry Date</label>
                  <input type="date" value={formData.expiryDate} onChange={e => setFormData({...formData, expiryDate: e.target.value})} />
                </div>
              </div>
              <div className="form-row" style={{ display: 'flex', gap: '15px' }}>
                <div className="form-group" style={{ flex: 1 }}>
                  <label>Total Capacity (Seats)*</label>
                  <input type="number" min="1" required value={formData.totalSeats} onChange={e => setFormData({...formData, totalSeats: parseInt(e.target.value) || 1})} />
                </div>
                <div className="form-group" style={{ flex: 1 }}>
                  <label>Status</label>
                  <select value={formData.status} onChange={e => setFormData({...formData, status: e.target.value})}>
                    <option value="Active">Active</option>
                    <option value="Expired">Expired</option>
                    <option value="Inactive">Inactive</option>
                  </select>
                </div>
              </div>
              <div className="modal-actions">
                <button type="button" className="btn-secondary" onClick={() => setModalType(null)}>Cancel</button>
                <button type="submit" className="btn-primary">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {showCheckoutModal.show && (
        <div className="modal-overlay">
          <div className="modal">
            <div className="modal-header"><h2>Check-out License</h2><button onClick={() => setShowCheckoutModal({show: false, licenseId: null})}><X /></button></div>
            <form onSubmit={async (e) => {
              e.preventDefault();
              try {
                await axios.post(`${API_URL}/usage/start`, { licenseId: showCheckoutModal.licenseId, ...checkoutInfo });
                setShowCheckoutModal({ show: false, licenseId: null });
                setCheckoutInfo({ userName: '', machineName: '' });
                fetchData();
              } catch (err) { alert('Full'); }
            }}>
              <div className="form-group"><label>User Name*</label><input type="text" required value={checkoutInfo.userName} onChange={e => setCheckoutInfo({...checkoutInfo, userName: e.target.value})} /></div>
              <div className="form-group"><label>Machine Name*</label><input type="text" required value={checkoutInfo.machineName} onChange={e => setCheckoutInfo({...checkoutInfo, machineName: e.target.value})} /></div>
              <div className="modal-actions"><button type="submit" className="btn-primary">Confirm Check-out</button></div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

export default App;

import { useState } from 'react';
import NavBar from '../components/NavBar';
import './styles/dashboardadmin.css';

export default function AdminDashboard() {
  const { user, csrf, flash } = window.__LARAVEL__;
  const [users, setUsers] = useState(window.__LARAVEL__.users);

  const updateRole = async (userId, newRole) => {
    await fetch(`/admin/users/${userId}/role`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ role: newRole })
    });
    setUsers(users.map(u => u.id === userId ? { ...u, role: newRole } : u));
  };

  const deleteUser = async (userId) => {
    if (!confirm('Delete user?')) return;
    await fetch(`/admin/users/${userId}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrf }
    });
    setUsers(users.filter(u => u.id !== userId));
  };

  return (
    <>
    <NavBar name={user.name}/>
    <div className='main-cont'>
      {flash.success && <p style={{ color: 'green' }}>{flash.success}</p>}
      {flash.error && <p style={{ color: 'red' }}>{flash.error}</p>}

      <div className="heading-cont">
        <h3>All Users</h3>
      </div>
      
      <div className="table-cont">
        <table className='table-main'>
          <thead>
              <tr className='table-heading'><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr>
          </thead>
          <tbody>
            {users.map(u => (
              <tr className='table-body' key={u.id}>
                <td className='id'>{u.id}</td>
                <td>{u.name}</td>
                <td>{u.email}</td>
                <td className='role-selector'>
                  <select value={u.role} onChange={e => updateRole(u.id, e.target.value)}>
                    <option value="applicant">Applicant</option>
                    <option value="recruiter">Recruiter</option>
                    <option value="admin">Admin</option>
                  </select>
                </td>
                <td className='delete-btn'>
                  {u.role !== 'admin' && (
                    <button onClick={() => deleteUser(u.id)}>Delete</button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
    </>
  );
}
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
    <div className='main-cont'>
      <NavBar name={user.name}/>
      <h2>Admin Dashboard from react</h2>

      <form action="/logout" method="POST" style={{ display: 'inline' }}>
        <input type="hidden" name="_token" value={csrf} />
        <button type="submit">Logout</button>
      </form>

      <hr />
      {flash.success && <p style={{ color: 'green' }}>{flash.success}</p>}
      {flash.error && <p style={{ color: 'red' }}>{flash.error}</p>}

      <h3>All Users</h3>
      <table border="1" cellPadding="5">
        <thead>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr>
        </thead>
        <tbody>
          {users.map(u => (
            <tr key={u.id}>
              <td>{u.id}</td>
              <td>{u.name}</td>
              <td>{u.email}</td>
              <td>
                <select value={u.role} onChange={e => updateRole(u.id, e.target.value)}>
                  <option value="applicant">Applicant</option>
                  <option value="recruiter">Recruiter</option>
                  <option value="admin">Admin</option>
                </select>
              </td>
              <td>
                {u.role !== 'admin' && (
                  <button onClick={() => deleteUser(u.id)}>Delete</button>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
    </>
  );
}
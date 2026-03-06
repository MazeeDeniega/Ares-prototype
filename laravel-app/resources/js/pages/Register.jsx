import { useState } from 'react';
import { Link } from 'react-router-dom';
import './styles/login.css';

export default function Register() {
  const csrf = window.__LARAVEL__?.csrf;
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    const form = e.target;

    const response = await fetch('/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: JSON.stringify({
        name: form.name.value,
        email: form.email.value,
        password: form.password.value,
        role: form.role.value
        })
      });

      if (response.ok || response.redirected) {
            window.location.href = '/login';
        } else {
            const data = await response.json();
            setError(data.message || 'Registration failed.');
        }
    };

    // Css of this page is linked to login.css
  return (
    <>
    <div className="login-main-cont">
      <div className="login-inner-cont">
        <h2>Register</h2>

        {error && <p style={{ color: 'red' }}>{error}</p>}

        <div className="login-form">
          <form onSubmit={handleSubmit}>

            <div className="input-section">
              <label>Name: </label>
              <input type="text" name="name" placeholder="Name" required />
            </div>
            <div className="input-section">
              <label>Email: </label>
              <input type="email" name="email" placeholder="Email" required />
            </div>

            <div className="input-section">
              <label>Password: </label>
              <input type="password" name="password" placeholder="Password" required />
            </div>

            <div className="input-section">
              <label>I am a:</label>
              <select name="role" required>
                <option value="applicant">Applicant</option>
                <option value="recruiter">Recruiter</option>
              </select>

            </div>
            <button className='login-btn' type="submit">Register</button>
          </form>
        </div>

        <div className="lower-section">
        <p><Link to="/login">Back to Login</Link></p>
        </div>
      </div>
    </div>
    </>
  );
}
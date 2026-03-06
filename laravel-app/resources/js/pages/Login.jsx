import { useState } from 'react';
import { Link } from 'react-router-dom';
import './styles/login.css';

export default function Login() {
  document.title = "Login";
  const csrf = window.__LARAVEL__?.csrf;
  const [error, setError] = useState(window.__LARAVEL__?.flash?.error || '');

  const handleSubmit = async (e) => {
    e.preventDefault();
    const form = e.target;

    const response = await fetch('/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: JSON.stringify({
        email: form.email.value,
        password: form.password.value
      })
    });

    if (response.ok) {
      window.location.href = '/';
    } else {
      const data = await response.json();
      setError(data.message || 'Invalid credentials.');
    }
  };

  return (
    <>
    <div className="login-main-cont">
      <div className="login-inner-cont">
        <h2>Welcome to ARES!</h2>

        {error && <p style={{ color: 'red' }}>{error}</p>}

        <div className="login-form">
          <form onSubmit={handleSubmit}>

            <div className="input-section">
              <label>Email: </label>
              <input type="text" name="email" placeholder="name@example.com" required />
            </div>

            <div className="input-section">
              <label>Password: </label>
              <input type="password" name="password" placeholder="" required />
            </div>

            <button className='login-btn' type="submit">Login</button>
          </form>
        </div>
        
        <div className="lower-section">
          <p>Don't have an account? <Link to="/register">Register</Link></p>
          <p><Link to="/jobs">Enter as a guest</Link></p>
        </div>
      </div>
    </div>
    </>
  );
}
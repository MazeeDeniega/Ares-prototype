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
      <h2>Log in page from react</h2>

      {error && <p style={{ color: 'red' }}>{error}</p>}

      <form onSubmit={handleSubmit}>
        <label>Email: </label>
        <input type="text" name="email" placeholder="Email" required /><br /><br />
        <label>Password: </label>
        <input type="password" name="password" placeholder="Password" required /><br /><br />
        <button type="submit">Login</button>
      </form>

      <p>Don't have an account? <Link to="/register">Register</Link></p>
      <p><Link to="/jobs">Enter as guest</Link></p>
    </div>
    </>
  );
}
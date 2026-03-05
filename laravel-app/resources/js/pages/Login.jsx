import { useState } from 'react';

export default function Login() {
  document.title = "Login";
  const { csrf, loginRoute, errors: serverError } = window.__LARAVEL__;
  const [error, setError] = useState(serverError || '');

  const handleSubmit = async (e) => {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);

    const res = await fetch(loginRoute, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
      body: data
    });

    if (res.ok) {
      const json = await res.json();
      window.location.href = json.redirect ?? '/admin';
    } else {
      const json = await res.json();
      setError(json.message || 'Invalid credentials.');
    }
  };

  return (
    <>
    <div style={{ padding: 20 }}>
      <h2>Log in page from react</h2>

      {error && <p style={{ color: 'red' }}>{error}</p>}

      <form onSubmit={handleSubmit}>
        <input type="text" name="email" placeholder="Email" required /><br /><br />
        <input type="password" name="password" placeholder="Password" required /><br /><br />
        <button type="submit">Login</button>
      </form>

      <p>Don't have an account? <a href="/register">Register</a></p>
      <p><a href="/jobs">View Jobs (Public)</a></p>
    </div>
    </>
  );
}
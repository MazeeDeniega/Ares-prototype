import { useState } from 'react';
import { Link } from 'react-router-dom';
import './styles/login.css';

export default function Register() {
  const csrf = window.__LARAVEL__?.csrf;
  const [password, setPassword] = useState('');
  const [input, setInput] = useState({
    password: '',
    confirmPassword: '',
  });
  const [error, setError] = useState('');
  document.title = "Register";

    const handleInputChange = (e) => {
      const newPassword = e.target.value;
      setPassword(newPassword);
      if (newPassword.length < 8) {
        setError('Password should have at least 8 characters');
      } else {
        setError('');
      }

      const { name, value } = e.target;
      setInput((prev) => ({
        ...prev,
        [name]: value,
      }));
    };

  const handleSubmit = async (e) => {
    e.preventDefault();
    const form = e.target;

    // Checks if passwords match
    if (input.password !== input.confirmPassword) {
      setError("Passwords do not match");
      return;
    }

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
        <div className="login-header">
          <h2>Register</h2>
        </div>
        

        {error && <p style={{ color: 'red' }}>{error}</p>}

        <div className="login-form">
          <form onSubmit={handleSubmit}>
            <div className="login-form-body">
              <div className="input-section">
                <label>Name </label>
                <input type="text" name="name" placeholder="Enter your name" required />
              </div>
              <div className="input-section">
                <label>Email </label>
                <input type="email" name="email" placeholder="Enter your email" required />
              </div>

              <div className="input-section">
                <label>Password </label>
                <input type="password" name="password" placeholder="Enter your password" onChange={handleInputChange} required />
              </div>

              <div className="input-section">
                <label>Confirm your password </label>
                <input type="password" name="password" placeholder="Enter your password" onChange={handleInputChange} required />
              </div>

              {/* <div className="input-section">
                <label>I am a:</label>
                <select name="role" required>
                  <option value="applicant">Applicant</option>
                  <option value="recruiter">Recruiter</option>
                </select>

              </div> */}
              <button className='login-btn' type="submit">Register</button>
            </div>

            
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
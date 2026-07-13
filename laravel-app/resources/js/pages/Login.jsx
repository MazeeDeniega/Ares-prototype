import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { BsFillLockFill, BsEnvelopeFill } from "react-icons/bs";
import AuthForm from '../components/AuthForm';

export default function Login() {
  const navigate = useNavigate();
  document.title = "Login";
  const csrf = window.__LARAVEL__?.csrf;

  const [formData, setFormData] = useState({
    email: '',
    password: '',
  });
  const [error, setError] = useState('');

  // Reverts input#password back to type="password" if DevTools is used
  // to change it. NOTE: this is a client-side deterrent only — it does
  // NOT prevent someone from reading the raw value via the console
  // (e.g. document.querySelector('#password').value), so it should not
  // be treated as an actual security fix.
  useEffect(() => {
    const passwordInput = document.getElementById('password');
    if (!passwordInput) return;

    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.attributeName === 'type' && passwordInput.type !== 'password') {
          passwordInput.type = 'password';
        }
      });
    });

    observer.observe(passwordInput, { attributes: true });

    return () => observer.disconnect();
  }, []);

  const handleChange = (field) => (e) =>
    setFormData((prev) => ({ ...prev, [field]: e.target.value }));

  const handleSubmit = async (e) => {
    e.preventDefault();

    const response = await fetch('/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify({
        email: formData.email,
        password: formData.password,
      }),
    });

    if (response.ok) {
      window.location.href = '/';
    } else {
      const data = await response.json();
      setError(data.message || 'Invalid credentials.');
    }
  };

  const fields = [
    {
      id: 'email',
      name: 'email',
      type: 'email',
      placeholder: 'Email',
      icon: <BsEnvelopeFill />,
      value: formData.email,
      onChange: handleChange('email'),
      autoComplete: 'email',
    },
    {
      id: 'password',
      name: 'password',
      type: 'password',
      placeholder: 'Password',
      icon: <BsFillLockFill />,
      value: formData.password,
      onChange: handleChange('password'),
      autoComplete: 'current-password',
    },
  ];

  const footer = (
    <>
      <span>
        Don't have an account?{' '}
        <Link to='/register'>Sign up</Link>
      </span>
      <span>or</span>
      <a href='/jobs'>Apply for jobs</a>
    </>
  );

  const altActions = [
    { label: 'Sign up', to: '/register' },
    { label: 'Apply for jobs', to: '/jobs' },
  ];

  return (
    <AuthForm
      title="Start Screening!"
      fields={fields}
      submitLabel="Login"
      onSubmit={handleSubmit}
      error={error}
      footer={footer}
      altActions={altActions}
    />
  );
}
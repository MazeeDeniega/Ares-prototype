import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { BsFillLockFill, BsEnvelopeFill, BsFillPersonFill } from "react-icons/bs";
import AuthForm from '../components/AuthForm';
import './styles/login.css';

export default function Register() {
  document.title = "Register";
  const csrf = window.__LARAVEL__?.csrf;
  const navigate = useNavigate();
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    confirmPassword: '',
  })

    // const handleInputChange = (e) => {
    //   const newPassword = e.target.value;
    //   setPassword(newPassword);
    //   if (newPassword.length < 8) {
    //     setError('Password should have at least 8 characters');
    //   }

    //   const { name, value } = e.target;
    //   setInput((prev) => ({
    //     ...prev,
    //     [name]: value,
    //   }));
    // };

  const handleChange = (field) => (e) =>
    setFormData((prev) => ({ ...prev, [field]: e.target.value }));

  const handleSubmit = async (e) => {
    e.preventDefault();
    const form = e.target;

    // Checks if passwords match
    if (formData.password !== formData.confirmPassword) {
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
        // role: form.role.value
        })
      });

      if (response.ok || response.redirected) {
            window.location.href = '/login';
        } else {
            const data = await response.json();
            setError(data.message || 'Registration failed.');
        }
    };

  const fields = [
    {
      id: 'name',
      type: 'text',
      placeholder: 'Name',
      icon: <BsFillPersonFill />,
      value: formData.name,
      onChange: handleChange('name'),
      autoComplete: 'name',
    },
    {
      id: 'email',
      type: 'email',
      placeholder: 'Email',
      icon: <BsEnvelopeFill />,
      value: formData.email,
      onChange: handleChange('email'),
      autoComplete: 'email',
    },
    {
      id: 'password',
      type: 'password',
      placeholder: 'Password',
      icon: <BsFillLockFill />,
      value: formData.password,
      onChange: handleChange('password'),
      autoComplete: 'new-password',
    },
    {
      id: 'confirmPassword',
      type: 'password',
      placeholder: 'Confirm Password',
      icon: <BsFillLockFill />,
      value: formData.confirmPassword,
      onChange: handleChange('confirmPassword'),
      autoComplete: 'new-password',
    },
  ];

  const footerLinks = (
    <Link to="/jobs">Apply for jobs</Link>
  );

  const secondaryBtns = [
    { label: 'Log in',         to: '/login' },
    { label: 'Apply for jobs', to: '/jobs'  },
  ];

    return(
      <>
        <div className="signup-page">
          <AuthForm
            title="Join ARES!"
            fields={fields}
            primaryBtn="Sign up"
            onSubmit={handleSubmit}
            footerLinks={footerLinks}
            secondaryBtns={secondaryBtns}
          />
        </div>
      </>
    );

  }

  // return (
  //   <>
  //   <div className="login-main-cont">
  //     <div className="login-inner-cont">
  //       <div className="login-header">
  //         <h2>Register to ARES</h2>
  //       </div>
        

  //       {error && <p style={{ color: 'red' }}>{error}</p>}

  //       <div className="login-form">
  //         <form onSubmit={handleSubmit}>
  //           <div className="login-form-body">
  //             <div className="input-section">
  //               <label>Name </label>
  //               <input type="text" name="name" placeholder="Enter your name" required />
  //             </div>
  //             <div className="input-section">
  //               <label>Email </label>
  //               <input type="email" name="email" placeholder="Enter your email" required />
  //             </div>

  //             <div className="input-section">
  //               <label>Password </label>
  //               <input type="password" name="password" placeholder="Enter your password" onChange={handleInputChange} required />
  //             </div>

  //             <div className="input-section">
  //               <label>Confirm your password </label>
  //               <input type="password" name="confirmPassword" placeholder="Enter your password" onChange={handleInputChange} required />
  //             </div>

  //             {/* <div className="input-section">
  //               <label>I am a:</label>
  //               <select name="role" required>
  //                 <option value="applicant">Applicant</option>
  //                 <option value="recruiter">Recruiter</option>
  //               </select>

  //             </div> */}
  //             <button className='login-btn' type="submit">Register</button>
  //           </div>

            
  //         </form>
  //       </div>

  //       <div className="lower-section">
  //       <p><Link to="/login">Back to Login</Link></p>
  //       </div>
  //     </div>
  //   </div>
  //   </>
  // );
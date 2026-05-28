import React from 'react';
import { route } from 'ziggy-js';
import { useForm, Link } from '@inertiajs/react';
import { BsFillLockFill, BsEnvelopeFill } from "react-icons/bs";
import AuthForm from '../components/AuthForm';
import './styles/login.css';

export default function Login() {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
    remember: false,
  });
  document.title = "Login";
  const csrf = window.__LARAVEL__?.csrf;

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
 
  const fields = [
    {
      id: 'email',
      name: 'email',
      type: 'email',
      placeholder: 'Email',
      icon: <BsEnvelopeFill />,
      value: data.email,
      onChange: (e) => setData('email', e.target.value),
      autoComplete: 'username',
      error: errors.email,
    },
    {
      id: 'password',
      name: 'password',
      type: 'password',
      placeholder: 'Password',
      icon: <BsFillLockFill />,
      value: data.password,
      onChange: (e) => setData('password', e.target.value),
      autoComplete: 'current-password',
      error: errors.password,
    },
  ];
 
  const footer = (
    <>
      <span>
        Don't have an account?{' '}
        <Link href='/register'>Sign up</Link>
      </span>
      <span>or</span>
      <Link href='/jobs'>Apply for jobs</Link>
    </>
  );
 
  const altActions = [
  { label: 'Sign up',        href: '/register'    },
  { label: 'Apply for jobs', href: '/jobs'  },
];
 
  return (
    <AuthForm
      title="Start Screening!"
      fields={fields}
      submitLabel="Login"
      onSubmit={handleSubmit}
      footer={footer}
      altActions={altActions}
      processing={processing}
    />
  );
}

// export default function Login() {
//   document.title = "Login";
//   const csrf = window.__LARAVEL__?.csrf;
//   const [error, setError] = useState(window.__LARAVEL__?.flash?.error || '');

//   const handleSubmit = async (e) => {
//     e.preventDefault();
//     const form = e.target;

//     const response = await fetch('/login', {
//       method: 'POST',
//       headers: {
//         'Content-Type': 'application/json',
//         'X-CSRF-TOKEN': csrf
//       },
//       body: JSON.stringify({
//         email: form.email.value,
//         password: form.password.value
//       })
//     });

//     if (response.ok) {
//       window.location.href = '/';
//     } else {
//       const data = await response.json();
//       setError(data.message || 'Invalid credentials.');
//     }
//   };

//   return (
//     <>
//     <div className="login-main-cont">
//       <div className="login-inner-cont">
//         <div className="login-header">
//           <h2>Welcome to <span>ARES</span>!</h2>
//         </div>

//         {error && <p style={{ color: 'red' }}>{error}</p>}

//         <div className="login-form">
//           <form onSubmit={handleSubmit}>
//             <div className="login-form-body">
//               <div className="input-section">
//                 <label>Email: </label>
//                 <input type="text" name="email" placeholder="Enter your email" required />
//               </div>

//               <div className="input-section">
//                 <label>Password: </label>
//                 <input type="password" name="password" placeholder="Enter your password" required />
//               </div>
//               <button className='login-btn' type="submit">Login</button>
//             </div>

//           </form>
//         </div>
        
//         <div className="lower-section">
//           <p>Don't have an account? <Link to="/register">Register</Link></p>
//           <p><a href="/jobs">Enter as a guest</a></p>
//         </div>
//       </div>
//     </div>
//     </>
//   );
// }
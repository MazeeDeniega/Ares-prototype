import React from 'react';
import './styles/navbar.css';

export default function NavBar(props) {
  const { user, csrf } = window.__LARAVEL__;

  const handleLogout = async () => {
    await fetch('/logout', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf}
    });
    window.location.href = 'login';
  };

  return (
    <>
    <nav className='navbar'>
      <div className="nav-left">
        <p>ARES</p>
      </div>

      <div className="nav-center">
        Welcome {props.name}
      </div>

      <div className="nav-right">
        {user ? (
          <button className='logout-btn' onClick={handleLogout}>
          Log out
        </button>) : ( <p></p>
        )}
        
      </div>
    </nav>
    </>
  )
}
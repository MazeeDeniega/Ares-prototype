import React from 'react';
import './styles/navbar.css';

export default function NavBar(props) {

  return (
    <>
      <nav className='navbar'>
        <div className="nav-left">
          ARES
        </div>

        <div className="nav-center">
          {props.title} Dashboard
        </div>

        <div className="nav-right">
          <button className='logout-btn'>
            Log out
          </button>
        </div>

      </nav>
    </>
  )
}
import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { BsSliders, BsPeopleFill, BsFillGridFill, BsBoxArrowLeft } from 'react-icons/bs';
import aresLogo from '../assets/ares_logo_blue.png';
import '../../css/components/sidebar.css';

export default function Sidebar({ isOpen, onClose }) {
  const { user, csrf } = window.__LARAVEL__ ?? {};
  console.log('User active:', user); 

  const navigate = useNavigate();

  const handleLogout = async () => {
    await fetch('/logout', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf}
    });
    navigate('/login');
  };

  const navItems = [
    { label: 'Job List', to: '/recruiter', icon: <BsFillGridFill /> },
    { label: 'All Candidates', to: '/candidates', icon: <BsPeopleFill /> }, // Not in routes yet
    { label: 'Default Preferences', to: '/preferences/edit', icon: <BsSliders /> },
  ];

  const sidebarContent = (
    <aside className="sidebar">
      {/* Brand */}
      <div className="sidebar__brand">
        <span className="sidebar__brand-name">
          <img src={aresLogo} />
        </span>
      </div>

      {/* Nav */}
      <nav className="sidebar__nav" aria-label="Main navigation">
        {navItems.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            onClick={onClose}
            className={({ isActive }) =>
              'sidebar__nav-item' + (isActive ? ' sidebar__nav-item--active' : '')
            }
          >
            <span className="sidebar__nav-icon">{item.icon}</span>
            {item.label}
          </NavLink>
        ))}
      </nav>

      {/* Log out */}
      <button className="sidebar__logout" onClick={handleLogout}>
        <span className="sidebar__nav-icon"><BsBoxArrowLeft /></span>
        Log out
      </button>
    </aside>
  );

  return (
    <>
      {/* Desktop: static sidebar (always visible) */}
      <div className="sidebar-desktop">
        {sidebarContent}
      </div>

      {/* Mobile: backdrop + drawer */}
      <div
        className={`sidebar-backdrop${isOpen ? ' sidebar-backdrop--open' : ''}`}
        onClick={onClose}
        aria-hidden="true"
      />
      <div className={`sidebar sidebar--drawer${isOpen ? ' sidebar--drawer-open' : ''}`}>
        {/* Reuse the same inner content */}
        <div className="sidebar__brand">
          <span className="sidebar__brand-icon"><BsPeopleFill /></span>
          <span className="sidebar__brand-name">ARES Logo</span>
        </div>
        <nav className="sidebar__nav" aria-label="Main navigation">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              onClick={onClose}
              className={({ isActive }) =>
                'sidebar__nav-item' + (isActive ? ' sidebar__nav-item--active' : '')
              }
            >
              <span className="sidebar__nav-icon">{item.icon}</span>
              {item.label}
            </NavLink>
          ))}
        </nav>
        <button className="sidebar__logout" onClick={handleLogout}>
          <span className="sidebar__nav-icon"><BsBoxArrowLeft /></span>
          Log out
        </button>
      </div>
    </>
  );
};
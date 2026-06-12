import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { BsSliders, BsPeopleFill, BsFillGridFill, BsBoxArrowLeft } from 'react-icons/bs';
import '../../css/components/sidebar.css';

export default function Sidebar({ isOpen, onClose }) {
  const { user, csrf } = window.__LARAVEL__;
  console.log('User active:', user); 

  const navigate = useNavigate();

  const handleLogout = (async) => {
    // await fetch('/logout', {
    //   method: 'POST',
    //   headers: { 'X-CSRF-TOKEN': csrf}
    // });
    navigate('/login');
  };

  const navItems = [
    { label: 'Job List', to: '/jobs', icon: <GridIcon /> },
    { label: 'All Candidates', to: '/candidates', icon: <UsersIcon /> }, // Not in routes yet
    { label: 'Default Preferences', to: '/preferences/edit', icon: <SlidersIcon /> },
  ];

  const sidebarContent = (
    <aside className="sidebar">
      {/* Brand */}
      <div className="sidebar__brand">
        <span className="sidebar__brand-icon"><BrandIcon /></span>
        <span className="sidebar__brand-name">ARES Logo</span>
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
        <span className="sidebar__nav-icon"><LogoutIcon /></span>
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
          <span className="sidebar__brand-icon"><BrandIcon /></span>
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

/* ── Inline SVG icons ── */
const BrandIcon = () => (
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
  </svg>
);

const GridIcon = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
  </svg>
);

const UsersIcon = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/>
    <path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.87"/>
  </svg>
);

const SlidersIcon = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/>
    <line x1="4" y1="18" x2="20" y2="18"/>
    <circle cx="8"  cy="6"  r="2" fill="currentColor" stroke="none"/>
    <circle cx="16" cy="12" r="2" fill="currentColor" stroke="none"/>
    <circle cx="10" cy="18" r="2" fill="currentColor" stroke="none"/>
  </svg>
);

const LogoutIcon = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
    <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
  </svg>
);

export const HamburgerIcon = () => (
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
    <line x1="3" y1="6"  x2="21" y2="6"/>
    <line x1="3" y1="12" x2="21" y2="12"/>
    <line x1="3" y1="18" x2="21" y2="18"/>
  </svg>
);
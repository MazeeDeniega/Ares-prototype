import React from 'react';
import { Link } from 'react-router-dom'
import { BsClipboard2 } from "react-icons/bs";
import bgimg from '../assets/blue_bg.png';
import aresLogo from '../assets/ares_logo_blue.png'
import '../../css/authform.css';

export default function AuthForm({
  title,
  fields = [],
  submitLabel = 'Submit',
  onSubmit,
  footer,
  secondaryBtns = [],
  processing = false,
}) {
  return (
    <div className="auth-page">
      {/* Left bg img */}
      <aside className="auth-branding">
        <img
          src={bgimg}
          alt=""
          className="auth-branding__bg"
          aria-hidden="true"
        />
        <div className="auth-branding__content">
          <div className="auth-branding__logo">
            
            {/* <BsClipboard2 />
            <span className="auth-branding__logo-text">ARES</span> */}
            <img src={aresLogo} alt='Logo' />
          </div>
          <p className="auth-branding__tagline">
            Smarter Hiring Decisions, Powered by Your Preferences.
          </p>
        </div>
      </aside>

      {/* Right Form Panel */}
      <main className="auth-form-panel">
        <div className="auth-card">
          {title && <h1 className="auth-card__title">{title}</h1>}

          <form onSubmit={onSubmit} noValidate>
            {fields.map((field) => (
              <div key={field.id}>
                <div className="auth-input-group">
                  {field.icon && (
                    <span className="auth-input-group__icon" aria-hidden="true">
                      {field.icon}
                    </span>
                  )}
                  <input
                    id={field.id}
                    name={field.name}
                    type={field.type || 'text'}
                    placeholder={field.placeholder}
                    value={field.value}
                    onChange={field.onChange}
                    autoComplete={field.autoComplete}
                    required={field.required !== false}
                  />
                </div>
                {field.error && (
                  <p className="auth-error" role="alert">
                    {field.error}
                  </p>
                )}
              </div>
            ))}

            <button
              type="submit"
              className="auth-btn-primary"
              disabled={processing}
            >
              {processing ? 'Please wait…' : submitLabel}
            </button>
          </form>

          {/* Desktop footer links */}
          {footer && (
            <div className="auth-footer auth-footer--desktop">{footer}</div>
          )}

          {/* Mobile alt-action buttons */}
          {secondaryBtns.length > 0 && (
            <div className="auth-btn-row">
              {secondaryBtns.map((action) => (
                <Link
                  key={action.label} 
                  to={action.to}
                  className="auth-btn-outline"
                >
                  {action.label}
                </Link>
              ))}
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
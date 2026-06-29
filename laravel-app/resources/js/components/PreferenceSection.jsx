import React, { useEffect, useRef } from 'react';
import '../../css/components/preferencesection.css';

/**
 * Top-level preference card (e.g. Final Score Weights).
 */
export default function PreferenceSection({ title, subtitle, children }) {
  return (
    <section className="pref-section">
      <div className="pref-section__header">
        <h2 className="pref-section__title">{title}</h2>
        {subtitle && <span className="pref-section__subtitle">{subtitle}</span>}
      </div>
      <div className="pref-section__body">
        {children}
      </div>
    </section>
  );
}

/**
 * Nested sub-group with a persistent header and show/hide toggle.
 * Opens automatically when parent slider crosses from 0 to >= 1; closes when parent hits 0.
 */
export function PreferenceSubGroup({
  parentValue,
  title,
  subtitle,
  children,
  expanded = false,
  onExpandedChange,
}) {
  const prevParentValue = useRef(parentValue);

  useEffect(() => {
    if (prevParentValue.current < 1 && parentValue >= 1) {
      onExpandedChange?.(true);
    }
    if (parentValue < 1) {
      onExpandedChange?.(false);
    }
    prevParentValue.current = parentValue;
  }, [parentValue, onExpandedChange]);

  return (
    <div className="pref-sub-group">
      <div className="pref-sub-group__header">
        <div className="pref-sub-group__header-text">
          {title && <h3 className="pref-sub-group__title">{title}</h3>}
          {subtitle && <span className="pref-sub-group__subtitle">{subtitle}</span>}
        </div>
        <button
          type="button"
          className="pref-sub-group__toggle-btn"
          onClick={() => onExpandedChange?.(!expanded)}
        >
          {expanded ? 'Hide' : 'Show'}
        </button>
      </div>
      {expanded && <div className="pref-sub-group__body">{children}</div>}
    </div>
  );
}
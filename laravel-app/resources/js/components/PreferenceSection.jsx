import React from 'react';
import '../../css/components/preferencesection.css';

/**
 * This is for preference groups section
 * For example:
 * Title:    Preference 1
 * Subtitle: Description about pref 1
 * Children: Sliders, inputs, selectors
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
};
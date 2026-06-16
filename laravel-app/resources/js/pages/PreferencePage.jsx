import React, { useState } from 'react';
import DashboardLayout    from '../layouts/DashboardLayout';
import PreferenceSection  from '../components/PreferenceSection';
import '../../css/pages/preferences.css';

export default function PreferencePage({ title, subtitle, postUrl }) {
  const { csrf, pref, flash } = window.__LARAVEL__ ?? {};

  const [qualWeight,       setQualWeight]       = useState(pref?.qual_weight        ?? 50);
  const [presentWeight,    setPresentWeight]    = useState(pref?.present_weight     ?? 50); // Fix: was "presesent_weight"
  const [keywordWeight,    setKeywordWeight]    = useState(pref?.keyword_weight     ?? 40);
  const [semanticWeight,   setSemanticWeight]   = useState(pref?.semantic_weight    ?? 60);
  const [skillsWeight,     setSkillsWeight]     = useState(pref?.skills_weight      ?? 45);
  const [experienceWeight, setExperienceWeight] = useState(pref?.experience_weight  ?? 20);
  const [educationWeight,  setEducationWeight]  = useState(pref?.education_weight   ?? 25);
  const [certWeight,       setCertWeight]       = useState(pref?.cert_weight        ?? 10);

  const [prefFormatting,   setPrefFormatting]   = useState(!!pref?.pref_formatting);
  const [prefLanguage,     setPrefLanguage]     = useState(!!pref?.pref_language);
  const [prefConciseness,  setPrefConciseness]  = useState(!!pref?.pref_conciseness);
  const [prefOrganization, setPrefOrganization] = useState(!!pref?.pref_organization);

  const [error,   setError]   = useState('');
  const [success, setSuccess] = useState(flash?.success ?? '');

  const blendTotal = keywordWeight + semanticWeight;
  const qualTotal  = skillsWeight + experienceWeight + educationWeight + certWeight;

  const getPresNote = () => {
    const items = [
      { label: 'Formatting',   checked: prefFormatting },
      { label: 'Language',     checked: prefLanguage },
      { label: 'Conciseness',  checked: prefConciseness },
      { label: 'Organization', checked: prefOrganization },
    ];
    const active = items.filter((i) => i.checked);
    if (active.length === 0)
      return 'No selection — all four categories will be weighted equally at 25% each.';
    const each      = Math.floor(100 / active.length);
    const remainder = 100 % active.length;
    return (
      'Active split → ' +
      active.map((v, i) => `${v.label}: ${each + (i === 0 ? remainder : 0)}%`).join(', ')
    );
  };

  const handleSubmit = async (e) => {
    if (e?.preventDefault) e.preventDefault();
    setError('');
    setSuccess('');

    // Fix: validate totals before sending
    if (blendTotal !== 100) {
      setError('TF-IDF + Semantic weights must total 100%.');
      return;
    }
    if (qualTotal !== 100) {
      setError('Qualification sub-weights must total 100%.');
      return;
    }

    try {
      const response = await fetch(postUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept':        'application/json',
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
        body: JSON.stringify({
          qual_weight:        qualWeight,
          present_weight:     presentWeight,    // Fix: was missing entirely
          keyword_weight:     keywordWeight,
          semantic_weight:    semanticWeight,
          skills_weight:      skillsWeight,
          experience_weight:  experienceWeight,
          education_weight:   educationWeight,
          cert_weight:        certWeight,
          pref_formatting:    prefFormatting   ? 1 : 0,
          pref_language:      prefLanguage     ? 1 : 0,
          pref_conciseness:   prefConciseness  ? 1 : 0,
          pref_organization:  prefOrganization ? 1 : 0,
        }),
      });

      if (response.ok) {
        setSuccess('Preferences saved successfully!');
      } else {
        try {
          const data = await response.json();
          setError(`Failed to save preferences: ${data.message}`); // Fix: was setError('...', data.message)
        } catch {
          setError(`Server error (${response.status}) — check Laravel logs.`);
        }
      }
    } catch (err) {
      setError('Network error — could not reach the server.');
    }
  };

  /* Slider + number input row */
  const WeightRow = ({ label, value, onChange, disabled = false }) => {
    const trackStyle = {
      background: disabled
        ? `linear-gradient(to right,
            var(--color-primary-light) 0%,
            var(--color-primary-light) 100%)`
        : `linear-gradient(to right,
            var(--color-primary-dark) 0%,
            var(--color-primary-dark) ${value}%,
            var(--color-primary-light) ${value}%,
            var(--color-primary-light) 100%)`,
    };

    return (
      <div className="pref-slider">
        <span className="pref-slider__label">{label}</span>
        <div className="pref-slider__row">
          <input
            className="pref-slider__input"
            type="range"
            min={0}
            max={100}
            value={value}
            disabled={disabled}
            style={{ ...trackStyle, opacity: disabled ? 0.5 : 1 }}
            onChange={(e) => !disabled && onChange(parseInt(e.target.value))}
          />
          <input
            type="number"
            min={0}
            max={100}
            value={value}
            disabled={disabled}
            className="pref-slider__number"
            onChange={(e) => !disabled && onChange(parseInt(e.target.value) || 0)}
          />
        </div>
      </div>
    );
  };

  /* Total validation row */
  const TotalRow = ({ total }) => (
    <p className={`pref-total-row${total === 100 ? ' pref-total-row--ok' : ' pref-total-row--bad'}`}>
      Total:{' '}
      <strong>
        {total}% {total === 100 ? '✓' : '(must equal 100%)'}
      </strong>
    </p>
  );

  return (
    <DashboardLayout title={title} subtitle={subtitle}>
      <form className="pref-page" onSubmit={handleSubmit}>

        {/* Desktop page header */}
        <div className="pref-page__header">
          <div className="pref-page__header-text">
            <h1 className="pref-page__title">{title}</h1>
            {subtitle && <span className="pref-page__subtitle">{subtitle}</span>}
          </div>
          <button type="submit" className="pref-page__save-btn">Save</button>
        </div>

        {/* Flash messages */}
        {error   && <div className="pref-flash pref-flash--error">{error}</div>}
        {success && <div className="pref-flash pref-flash--success">{success}</div>}

        <div className="pref-page__content">

          {/* Final Score Weights */}
          <PreferenceSection
            title="Final Score Weights"
            subtitle="How much each component contributes to the final ranking score (must total 100%)."
          >
            <WeightRow
              label="Qualifications"
              value={qualWeight}
              onChange={(val) => setQualWeight(Math.min(100, Math.max(0, val)))}
            />
            <WeightRow
              label="Presentation"
              value={presentWeight}
              onChange={(val) => setPresentWeight(Math.min(100, Math.max(0, val)))}
            />
            <p className="pref-note">Presentation = 100 − Qualifications (auto-set).</p>
          </PreferenceSection>

          {/* Qualifications */}
          <PreferenceSection
            title="Qualifications"
            subtitle="Skills Matching — TF-IDF + Semantic (blend must total 100%)."
          >
            <WeightRow label="TF-IDF (Keyword)" value={keywordWeight}  onChange={setKeywordWeight} />
            <WeightRow label="Semantic (AI)"    value={semanticWeight} onChange={setSemanticWeight} />
            <TotalRow total={blendTotal} />
          </PreferenceSection>

          {/* Presentation */}
          <PreferenceSection
            title="Presentation"
            subtitle="Check which categories to score. Checked categories split 100% equally. If none are checked, all four share 25% each."
          >
            <div className="pref-selector__grid">
              {[
                { label: 'Formatting & Visuals',     desc: 'Section spacing, B&W layout',                   value: prefFormatting,   setter: setPrefFormatting },
                { label: 'Language Quality',         desc: 'Action verbs, formal tone, no typos',            value: prefLanguage,     setter: setPrefLanguage },
                { label: 'Conciseness',              desc: 'Word count, page length, minimal repetition',    value: prefConciseness,  setter: setPrefConciseness },
                { label: 'Organization & Structure', desc: 'Sections, margins, reverse-chronological order', value: prefOrganization, setter: setPrefOrganization },
              ].map(({ label, desc, value, setter }) => (
                <label
                  key={label}
                  className={`pref-selector__btn${value ? ' pref-selector__btn--active' : ''}`}
                  style={{ cursor: 'pointer' }}
                >
                  <input
                    type="checkbox"
                    checked={value}
                    onChange={(e) => setter(e.target.checked)}
                    style={{ marginRight: 7, width: 15, height: 15, accentColor: '#2563eb', verticalAlign: 'middle' }}
                  />
                  <span className="pref-selector__btn-label" style={{ verticalAlign: 'middle' }}>{label}</span>
                  <span className="pref-selector__btn-desc" style={{ display: 'block', marginTop: 3 }}>{desc}</span>
                </label>
              ))}
            </div>
            <p className="pref-pres-note">{getPresNote()}</p>
          </PreferenceSection>

          {/* Qualification Sub-weights */}
          <PreferenceSection
            title="Qualification Sub-weights"
            subtitle="(must total 100%)"
          >
            <div className="pref-sub-section">
              <h4 className="pref-sub-section__title"></h4>
              <WeightRow label="Skills Match"  value={skillsWeight}     onChange={setSkillsWeight} />
              <WeightRow label="Experience"    value={experienceWeight} onChange={setExperienceWeight} />
              <WeightRow label="Education"     value={educationWeight}  onChange={setEducationWeight} />
              <WeightRow label="Certification" value={certWeight}       onChange={setCertWeight} />
              <TotalRow total={qualTotal} />
            </div>
          </PreferenceSection>

        </div>

        {/* Mobile sticky save bar */}
        <div className="pref-page__mobile-save">
          <button type="submit" className="pref-page__mobile-save-btn">Save Preferences</button>
        </div>

      </form>
    </DashboardLayout>
  );
}
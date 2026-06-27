import React, { useState } from 'react';
import DashboardLayout    from '../layouts/DashboardLayout';
import PreferenceSection  from '../components/PreferenceSection';
import '../../css/pages/preferences.css';

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

/** Total validation row */
const TotalRow = ({ total }) => (
  <p className={`pref-total-row${total === 100 ? ' pref-total-row--ok' : ' pref-total-row--bad'}`}>
    Total:{' '}
    <strong>
      {total}% {total === 100 ? '✓' : '(must equal 100%)'}
    </strong>
  </p>
);

/**
 * Rebalances a group of weights so the group always sums to exactly 100.
 * The slider at `changedIndex` is set to `newValue`, and the remaining
 * amount (100 - newValue) is distributed across the other sliders
 * proportionally to their current values (or equally if they are all 0).
 * Largest-remainder rounding keeps every value an integer with an exact 100 total.
 *
 * @param {number[]} values  current values of the group
 * @param {number}   changedIndex  index of the slider the user moved
 * @param {number}   newValue  the new value for that slider
 * @returns {number[]} the rebalanced group values
 */
function rebalanceGroup(values, changedIndex, newValue) {
  const clamped = Math.min(100, Math.max(0, Math.round(newValue) || 0));
  const next = [...values];
  next[changedIndex] = clamped;

  const otherIndices = values.map((_, i) => i).filter((i) => i !== changedIndex);
  const remaining = 100 - clamped;

  if (otherIndices.length === 0) return next;

  const othersSum = otherIndices.reduce((sum, i) => sum + values[i], 0);

  // Raw (fractional) target for each other slider.
  const raw = otherIndices.map((i) =>
    othersSum === 0 ? remaining / otherIndices.length : (values[i] / othersSum) * remaining
  );

  // Largest-remainder method: floor everything, then hand out leftover units
  // to the sliders with the biggest fractional parts so the total hits 100.
  const floored = raw.map((r) => Math.floor(r));
  let leftover = remaining - floored.reduce((a, b) => a + b, 0);

  const order = raw
    .map((r, k) => ({ k, frac: r - floored[k] }))
    .sort((a, b) => b.frac - a.frac);

  const result = [...floored];
  for (let n = 0; n < leftover; n++) {
    result[order[n % order.length].k] += 1;
  }

  otherIndices.forEach((i, k) => {
    next[i] = result[k];
  });
  return next;
}

export default function PreferencePage({ title, subtitle, postUrl }) {
  const { csrf, pref, flash } = window.__LARAVEL__ ?? {};

  const [qualWeight,       setQualWeight]       = useState(pref?.qual_weight        ?? 80);
  const [presentationWeight,    setPresentWeight]    = useState(pref?.presentation_weight     ?? 20);
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

  const [error, setError]   = useState('');
  const [success, setSuccess] = useState(flash?.success ?? '');

  // const presWeight = 100 - qualWeight;
  const blendTotal = keywordWeight + semanticWeight;
  const qualTotal  = skillsWeight + experienceWeight + educationWeight + certWeight;

  // Each group auto-rebalances so its sliders always sum to exactly 100.
  const setFinalGroup = (index, val) => {
    const [q, p] = rebalanceGroup([qualWeight, presentationWeight], index, val);
    setQualWeight(q);
    setPresentWeight(p);
  };

  const setBlendGroup = (index, val) => {
    const [k, s] = rebalanceGroup([keywordWeight, semanticWeight], index, val);
    setKeywordWeight(k);
    setSemanticWeight(s);
  };

  const setSubGroup = (index, val) => {
    const [sk, ex, ed, ce] = rebalanceGroup(
      [skillsWeight, experienceWeight, educationWeight, certWeight],
      index,
      val
    );
    setSkillsWeight(sk);
    setExperienceWeight(ex);
    setEducationWeight(ed);
    setCertWeight(ce);
  };

  const getPresNote = () => {
    const items = [
      { label: 'Formatting', checked: prefFormatting },
      { label: 'Language', checked: prefLanguage },
      { label: 'Conciseness', checked: prefConciseness },
      { label: 'Organization', checked: prefOrganization },
    ];
    const active = items.filter((i) => i.checked);
    if (active.length === 0)
      return 'No selection — all four categories will be weighted equally at 25% each.';
    const each = Math.floor(100 / active.length);
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
          presentation_weight:     presentationWeight, // This is not being saved
          keyword_weight:     keywordWeight,
          semantic_weight:    semanticWeight,
          skills_weight:      skillsWeight,
          experience_weight:  experienceWeight,
          education_weight:   educationWeight,
          cert_weight:        certWeight,
          pref_formatting:    prefFormatting ? 1 : 0,
          pref_language:      prefLanguage ? 1 : 0,
          pref_conciseness:   prefConciseness ? 1 : 0,
          pref_organization:  prefOrganization ? 1 : 0,
        }),
      });
 
      if (response.ok) {
        setSuccess('Preferences saved successfully!');
      } else {
        try {
          const data = await response.json();
          setError('Failed to save preferences: ', data.message);
        } catch {
          setError(`Server error (${response.status}) — check Laravel logs.`);
        }
      }
    } catch (err) {
      setError('Network error — could not reach the server.');
    }
  };

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
              onChange={(val) => setFinalGroup(0, val)}
            />
            <WeightRow
              label="Presentation"
              value={presentationWeight}
              onChange={(val) => setFinalGroup(1, val)}
            />
            <TotalRow total={qualWeight + presentationWeight} />
            <p className="pref-note">Presentation = 100 − Qualifications (auto-set).</p>
          </PreferenceSection>
 
          {/* Qualifications */}
          <PreferenceSection
            title="Qualifications"
            subtitle="Skills Matching — TF-IDF + Semantic (blend must total 100%)."
          >
            <WeightRow label="TF-IDF (Keyword)" value={keywordWeight}  onChange={(val) => setBlendGroup(0, val)} />
            <WeightRow label="Semantic (AI)"    value={semanticWeight} onChange={(val) => setBlendGroup(1, val)} />
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

          <PreferenceSection
            title="Qualification Sub-weights"
            subtitle="(must total 100%)">
            <div className="pref-sub-section">
              <h4 className="pref-sub-section__title">

              </h4>
              <WeightRow label="Skills Match"  value={skillsWeight}     onChange={(val) => setSubGroup(0, val)} />
              <WeightRow label="Experience"    value={experienceWeight} onChange={(val) => setSubGroup(1, val)} />
              <WeightRow label="Education"     value={educationWeight}  onChange={(val) => setSubGroup(2, val)} />
              <WeightRow label="Certification" value={certWeight}       onChange={(val) => setSubGroup(3, val)} />
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
};
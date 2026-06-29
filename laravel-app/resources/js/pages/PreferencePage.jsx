import React, { useState, useEffect, useRef } from 'react';
import DashboardLayout    from '../layouts/DashboardLayout';
import PreferenceSection, { PreferenceSubGroup } from '../components/PreferenceSection';
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
  const { csrf, pref, flash, job } = window.__LARAVEL__ ?? {};

  const [qualWeight,       setQualWeight]       = useState(pref?.qual_weight        ?? 80);
  const [layoutWeight,    setLayoutWeight]      = useState(pref?.layout_weight      ?? 20);
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

  const [qualSubExpanded,    setQualSubExpanded]    = useState((pref?.qual_weight        ?? 80) >= 1);
  const [skillsSubExpanded,  setSkillsSubExpanded]  = useState((pref?.skills_weight      ?? 45) >= 1);
  const [presSubExpanded,    setPresSubExpanded]    = useState((pref?.layout_weight      ?? 20) >= 1);

  const [error, setError]   = useState('');
  const [success, setSuccess] = useState(flash?.success ?? '');
  const [secondsLeft, setSecondsLeft] = useState(5);
  const [redirect, setRedirect] = useState(false);
  const targetTimeRef = useRef(null);
  const intervalRef = useRef(null);

  // const presWeight = 100 - qualWeight;
  const blendTotal = keywordWeight + semanticWeight;
  const qualTotal  = skillsWeight + experienceWeight + educationWeight + certWeight;

  // Each group auto-rebalances so its sliders always sum to exactly 100.
  const setFinalGroup = (index, val) => {
    const [q, p] = rebalanceGroup([qualWeight, layoutWeight], index, val);
    setQualWeight(q);
    setLayoutWeight(p);
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
          layout_weight:     layoutWeight, // This is not being saved
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
        setSuccess('Preferences saved successfully! ');
        if (postUrl === `/jobs/${job.id}/preferences`)
          setRedirect(true);

      } else {
        try {
          const data = await response.json();
          setError('Failed to save preferences: ', data.message);
        } catch {
          setError(`Server error (${response.status}) — check Laravel logs.`);
        }
      }
    } catch (err) {
      console.error('Network error:', err);
      setError('Network error — could not reach the server.');
    }
  };

  useEffect(() => {
    if (redirect){
    
    targetTimeRef.current = Date.now() + (secondsLeft * 1000); // 5 seconds from now
    intervalRef.current = setInterval(() => {
      const now = Date.now();
      const remaining = Math.max(0, Math.round((targetTimeRef.current - now) / 1000));
      setSecondsLeft(remaining);
      console.log('Seconds left:', remaining);
      if (remaining <= 0) {
        clearInterval(intervalRef.current);
        window.location.href = `/screening/${job.id}`;
      }
    }, 1000);
    return () => clearInterval(intervalRef.current);
  }}, [redirect]);


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
        {success && <div className="pref-flash pref-flash--success">
          {success} 
          Redirecting to evaluation in <strong>{secondsLeft} second{secondsLeft !== 1 ? 's' : ''}</strong>...
        </div>}
 
        <div className="pref-page__content">

          <PreferenceSection
            title="Final Score Weights"
            subtitle="How much each component contributes to the final ranking score."
          >
            <WeightRow
              label="Qualifications"
              value={qualWeight}
              onChange={(val) => setFinalGroup(0, val)}
            />

            <PreferenceSubGroup
              parentValue={qualWeight}
              title="Sub-Weights"
              expanded={qualSubExpanded}
              onExpandedChange={setQualSubExpanded}
            >
              <WeightRow
                label="Skills Match"
                value={skillsWeight}
                onChange={(val) => setSubGroup(0, val)}
              />

              <PreferenceSubGroup
                parentValue={skillsWeight}
                title="Qualifications"
                subtitle="Skills Matching — TF-IDF + Semantic."
                expanded={skillsSubExpanded}
                onExpandedChange={setSkillsSubExpanded}
              >
                <WeightRow
                  label="TF-IDF (Keyword)"
                  value={keywordWeight}
                  onChange={(val) => setBlendGroup(0, val)}
                />
                <WeightRow
                  label="Semantic (AI)"
                  value={semanticWeight}
                  onChange={(val) => setBlendGroup(1, val)}
                />
              </PreferenceSubGroup>

              <WeightRow
                label="Experience"
                value={experienceWeight}
                onChange={(val) => setSubGroup(1, val)}
              />
              <WeightRow
                label="Education"
                value={educationWeight}
                onChange={(val) => setSubGroup(2, val)}
              />
              <WeightRow
                label="Certification"
                value={certWeight}
                onChange={(val) => setSubGroup(3, val)}
              />
            </PreferenceSubGroup>

            <WeightRow
              label="Presentation"
              value={layoutWeight}
              onChange={(val) => setFinalGroup(1, val)}
            />

            <PreferenceSubGroup
              parentValue={layoutWeight}
              title="Selections"
              expanded={presSubExpanded}
              onExpandedChange={setPresSubExpanded}
            >
              <p className="pref-pres-note">{getPresNote()}</p>
              <div className="pref-selector">
                {[
                  { label: 'Organization & Structure', desc: 'Sections, margins, reverse-chronological order', value: prefOrganization, setter: setPrefOrganization },
                  { label: 'Conciseness',              desc: 'Word count, page length, minimal repetition',    value: prefConciseness,  setter: setPrefConciseness },
                  { label: 'Language Quality',         desc: 'Action verbs, formal tone, no typos',            value: prefLanguage,     setter: setPrefLanguage },
                  { label: 'Formatting & Visuals',     desc: 'Section spacing, B&W layout',                   value: prefFormatting,   setter: setPrefFormatting },
                ].map(({ label, desc, value, setter }) => (
                  <label
                    key={label}
                    className={`pref-selector__btn${value ? ' pref-selector__btn--active' : ''}`}
                  >
                    <input
                      type="checkbox"
                      checked={value}
                      onChange={(e) => setter(e.target.checked)}
                    />
                    <span className="pref-selector__btn-label">{label}</span>
                    <span className="pref-selector__btn-desc">{desc}</span>
                  </label>
                ))}
              </div>
            </PreferenceSubGroup>


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
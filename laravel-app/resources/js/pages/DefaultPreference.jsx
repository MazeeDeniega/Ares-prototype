import { useState, useEffect } from 'react';
import PreferencePage from './PreferencePage';
// import './styles/preference.css';

export default function DefaultPreferences() {

  return (
    <>
      <PreferencePage
        title="Default Preferences"
        subtitle="Applied to all jobs unless overridden by a job-specific preference."
        postUrl="/preferences"
      />
    </>
  );
}
import PreferencePage from './PreferencePage';

export default function JobPreference() {
  return(
    <>
      <PreferencePage
        title="Default Preferences"
        subtitle="Applied to all jobs unless overridden by a job-specific preference."
        postUrl="/jobs/${job.id}/preferences"
      />
    </>
  );
}
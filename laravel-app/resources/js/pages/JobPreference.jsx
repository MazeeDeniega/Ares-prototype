import PreferencePage from './PreferencePage';

export default function JobPreference() {
  const { job } = window.__LARAVEL__;
  return(
    <>
      <PreferencePage
        title={`Job Preference - ${job.title}`}
        subtitle="Overrides your default preferences for this job only."
        postUrl="/jobs/${job.id}/preferences"
      />
    </>
  );
}
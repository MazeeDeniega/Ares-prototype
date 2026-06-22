import DashboardLayout from '../layouts/DashboardLayout';
import JobListTable from '../components/JobListTable';
import { useState } from 'react';

export default function DashboardEvaluate() {
  const { job, csrf } = window.__LARAVEL__ ?? {};
  const applications = job?.applications ?? [];
  const [loading, setLoading] = useState(false);
 
  document.title = `Evaluate - ${job?.title ?? 'Job'}`;
 
  // Map raw application records into the shape JobListTable expects.
  const candidates = applications.map((app, index) => ({
    '#': index + 1,
    Name: `${app.first_name ?? ''} ${app.last_name ?? ''}`.trim(),
    Email: app.email,
    status: app.status ?? 'Pending',
    Resume: app.resume_path ? `/files/${app.id}/resume` : null,
  }));
 
  const handleEvaluate = () => {
    if (!job) return;
    setLoading(true);
    // Full navigation — runs evaluation server-side, lands on the results view.
    window.location.href = `/screening/${job.id}/evaluate`;
  };
 
  return (
    <DashboardLayout title={job?.title ?? 'Job'} subtitle="Applicants for this job">
      <JobListTable
        jobTitle={job?.title ?? 'Job'}
        candidateCount={applications.length}
        candidates={candidates}
        onEvaluate={handleEvaluate}
      />
      {loading && <p style={{ padding: '0 24px', color: '#555' }}>Evaluating…</p>}
    </DashboardLayout>
  );
}
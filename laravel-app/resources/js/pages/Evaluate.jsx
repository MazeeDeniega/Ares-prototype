import { useState } from 'react';

export default function Evaluate() {
  const { job, csrf } = window.__LARAVEL__ ?? {};
  const [applications, setApplications] = useState(job?.applications ?? []);
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState('');
  const [error, setError] = useState('');

  const handleEvaluate = async () => {
    setLoading(true);
    setError('');

    const response = await fetch(`/screening/${job.id}/evaluate`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf }
    });

    if (response.ok || response.redirected) {
      setSuccess('Evaluation complete!');
      // Refresh applications to show updated statuses
      const updated = await fetch(`/screening/${job.id}/applications`, {
        headers: { 'X-CSRF-TOKEN': csrf }
      });
      if (updated.ok) {
        const data = await updated.json();
        setApplications(data);
      }
    } else {
      setError('Evaluation failed. Please try again.');
    }

    setLoading(false);
  };

  return (
    <div style={{ padding: 20 }}>
      <p><a href="/recruiter">← Back to Dashboard</a></p>
      <h2>Applicants for: {job?.title}</h2>
      <hr />

      {success && <p style={{ color: 'green' }}>{success}</p>}
      {error   && <p style={{ color: 'red' }}>{error}</p>}

      {applications.length > 0 ? (
        <>
        <button
          onClick={handleEvaluate}
          disabled={loading}
          style={{ padding: '10px 20px', background: 'green', color: 'white', cursor: 'pointer' }}
        >
          {loading ? 'Evaluating...' : 'Evaluate All Applicants'}
        </button>

        <h3>Applicant List ({applications.length})</h3>
        <table border="1" cellPadding="5">
          <thead>
            <tr>
              <th>#</th>
              <th>Candidate Name</th>
              <th>Email</th>
              <th>Status</th>
              <th>Resume</th>
            </tr>
          </thead>
          <tbody>
            {applications.map((app, index) => (
              <tr key={app.id}>
                <td>{index + 1}</td>
                <td>{app.user?.name}</td>
                <td>{app.user?.email}</td>
                <td>{app.status}</td>
                <td>
                  {app.resume_path
                    ? <a href={`/files/${app.id}/resume`} target="_blank">View Resume</a>
                    : 'No Resume'
                  }
                </td>
              </tr>
            ))}
        </tbody>
        </table>
        </>
      ) : (
          <p>No applicants yet.</p>
      )}
    </div>
  );
}
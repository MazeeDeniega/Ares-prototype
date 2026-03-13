import { useState } from 'react';
import { Link } from 'react-router';
import './styles/evaluate.css';
import NavBar from "../components/NavBar";

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
      window.location.href = `/screening/${job.id}/evaluate`;
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
    <>
    <NavBar name="Recruiter" />
    <div className="eval-main-cont">
      
      <div className="eval-inner-cont">

        <div className="eval-upper-cont">
          
            <p><a href="/recruiter">← Back to Dashboard</a></p>
            
        </div>

        <div className="eval-lower-cont">

          <div className="eval-header">
            <h2>Applicants for: {job?.title}</h2>
          </div>
          
          <hr />
          <div className="flash">
            {success && <p style={{ color: 'green' }}>{success}</p>}
            {error   && <p style={{ color: 'red' }}>{error}</p>}
          </div>

          {applications.length > 0 ? (
          <div className="eval-table-cont">
            <div className="eval-table-header">
              <h3>Applicant List ({applications.length})</h3>
              <button
                onClick={handleEvaluate}
                disabled={loading}
              >
                {loading ? 'Evaluating...' : 'Evaluate All Applicants'}
              </button>
            </div>
            
            <div className="eval-table-cont">
              <table>
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
                      <td>{index + 1}</td> {/* hard coded id, need to get right id */}
                      <td>{app.first_name} {app.last_name}</td>
                      <td>{app.email}</td>
                      <td>{app.status}</td>
                      <td>
                        {app.resume_path
                          ? <a href={`/files/${app.id}/resume`} target="_blank">View Resume</a>
                          : 'No Resume'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            
          </div>
        ) : (
            <p>No applicants yet.</p>
        )}
        </div>
        
        
      </div>
    </div>
    </>
  );
}
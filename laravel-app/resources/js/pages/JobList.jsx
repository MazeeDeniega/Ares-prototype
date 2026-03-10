import { useState } from 'react';
import NavBar from '../components/NavBar';
import './styles/joblist.css'

export default function JobList() {
  const { user, jobs } = window.__LARAVEL__ ?? {};
  const [jobList] = useState(jobs ?? []);

  const truncate = (text, length = 100) =>
      text.length > length ? text.slice(0, length) + '...' : text;

  return (
    <>
    <div>
      {user ? (
          <NavBar />
      ) : (
        <p>
          <a href="/login">Login to Apply</a> | <a href="/register">Register</a>
        </p>
      )}
    <div className="job-list-main-cont">
      <h2>Available Jobs</h2>

      {jobList.length > 0 ? (
        jobList.map(job => (
          <div key={job.id} style={{ border: '1px solid #ccc', padding: 10, margin: '10px 0' }}>
            <h3>{job.title}</h3>
            <p><strong>Posted by:</strong> {job.user?.name ?? 'Unknown'}</p>
            <p>{truncate(job.description)}</p>
            <a href={`/jobs/${job.id}`}>View Details</a> |{' '}
            <a href={`/apply/${job.id}`}>Apply Now</a>
          </div>
        ))
      ) : (
        <p>No jobs available.</p>
      )}
      </div>
    </div>
    </>
  );
}
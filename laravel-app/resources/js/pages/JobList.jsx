import { useState } from 'react';
import NavBar from '../components/NavBar';
import './styles/joblist.css'

export default function JobList() {
  const { user, jobs } = window.__LARAVEL__ ?? {};
  const [jobList] = useState(jobs ?? []);

  const truncate = (text, length = 200) =>
      text.length > length ? text.slice(0, length) + '...' : text;

  return (
    <>
    <div className='job-list-body'>
          <NavBar name="to ARES!"/>
      
    <div className="job-list-main-cont">
      <div className="job-list-header">
        <h2>Available Jobs</h2>
      </div>

      {jobList.length > 0 ? (
        jobList.map(job => (
          <div className="job-list-body">
            <div className="job-list" key={job.id}>

              <div className="job-list-top">
                <h3><a href={`/jobs/${job.id}`}>{job.title}</a></h3>
                <p><strong>Posted by:</strong> {job.user?.name ?? 'Unknown'}</p>
              </div>

              <div className="job-list-desc">
                <p>{truncate(job.description)}</p>
              </div>

              <div className="job-list-action-btn">
                <a href={`/apply/${job.id}`}><button className="apply-btn" type='button'>Apply Now</button></a>
              </div>

            </div>
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
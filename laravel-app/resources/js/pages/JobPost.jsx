import './styles/jobpost.css';
import { Link } from 'react-router';

export default function JobPost() {
  const { job, user } = window.__LARAVEL__ ?? {};

  return (
    <div className='jobpost-main-cont'>
      <p><a href="/jobs">← Back to Jobs</a></p>

      <div className="jobpost-inner-cont">

        <div className="jobpost-header">
        <h2>{job?.title}</h2>
        <p><strong>Recruiter:</strong> {job?.user?.name}</p>
        <hr />
        </div>
        <div className="jobpost-body">
          <div className="jobpost-desc">
            <p>{job?.description}</p>
            <br /> <br />
            <hr />
          </div>
          <div className="jobpost-action-btn">
              <Link to={`/apply/${job?.id}`}>
                <button className='apply-btn' type='button'>Apply Now</button></Link>
          </div>
          
        </div>
        
      </div>
    </div>
  );
}
import { useState } from 'react';
import { Link } from 'react-router-dom';
import '../../css/joblist.css'

export default function JobList() {
  const { jobs } = window.__LARAVEL__ ?? {};
  const [jobList] = useState(jobs ?? []);

  const truncate = (text, length = 200) =>
      text.length > length ? text.slice(0, length) + '...' : text;

  return (
    <>
    <div className="job-openings-page">
 
      {/* Heading*/}
      <header className="job-openings-header">
        <h1 className="job-openings-header__title">Current Job Openings</h1>
        <p className="job-openings-header__subtitle">
          See a job that fits you? Apply now!
        </p>
      </header>
 
      {/* Job list */}
      <section className="job-openings-list" aria-label="Job listings">

        {jobList.length > 0 ? (
        jobList.map((job) => (
        <article className="job-card" key={job.id}>
          <h2 className="job-card__title"><a href={`/jobs/${job.id}`}>{job.title}</a></h2>
    
          <div className="job-card__meta">
            <span className="job-card__meta-label">Posted by:</span>
            <span className="job-card__meta-value">{job.user?.name ?? 'Unknown'}</span>
          </div>
    
          <p className="job-card__description">{job.description}</p>
    
          <div className="job-card__footer">
            <a href={`/apply/${job.id}`}>
            <button className="job-card__apply-btn">
              Apply now
            </button>
            </a>
          </div>
        </article>
        ))) : (<p>No jobs available.</p>) }
      </section>
 
    </div>


    {/* <div className='job-list-body'>

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
    </div> */}
    </>
  );
}
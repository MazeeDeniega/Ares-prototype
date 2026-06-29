import '../../css/pages/jobpost.css';
import { Link } from 'react-router-dom';
import { BsArrowLeft } from 'react-icons/bs';

export default function JobPost() {
  const { job, user } = window.__LARAVEL__ ?? {};

  console.log(job);

  if (!job) {
    return (
      <div className='job-desc-page'>
        <p className='job-desc-state'>Job not found.</p>
      </div>
    );
  }
  const htmlContent = job.description;

  return (
    <>
      <div className="job-desc-page">
        <div className="job-desc-card">
  
          {/* header */}
          <div className="job-desc-hero">
            <a href="/jobs" className="job-desc-back">
              <BsArrowLeft />
              Job Openings
            </a>
  
            <h1 className="job-desc-title">{job?.title}</h1>
  
            <div className="job-desc-meta">
              <span className="job-desc-meta__item">
                <span className="job-desc-meta__label">Posted by: </span>
                <span className="job-desc-meta__value">{job?.user?.name}</span>
              </span>
            </div>
          </div>
  
          <div className="job-desc-body">
            {/* Main description paragraph */}
              <div
              className="job-desc-quill-content ql-editor"
              dangerouslySetInnerHTML={{ __html: htmlContent }}
              />
            
            {/* {job?.description && <p>{job?.description}</p>} */}
  
  
            {/* Apply button */}
            <div className="job-desc-footer">
              <Link to={`/apply/${job?.id}`}>
                <button className="job-desc-apply-btn">
                  Apply now
                </button>
              </Link>
            </div>
          </div>

        </div>
      </div>
    
    </>
    // <div className='jobpost-main-cont'>
    //   <p><a href="/jobs">← Back to Jobs</a></p>

    //   <div className="jobpost-inner-cont">

    //     <div className="jobpost-header">
    //     <h2>{job?.title}</h2>
    //     <p><strong>Recruiter:</strong> {job?.user?.name}</p>
    //     <hr />
    //     </div>
    //     <div className="jobpost-body">
    //       <div className="jobpost-desc">
    //         <p>{job?.description}</p>
    //         <br /> <br />
    //         <hr />
    //       </div>
    //       <div className="jobpost-action-btn">
    //           <Link to={`/apply/${job?.id}`}>
    //             <button className='apply-btn' type='button'>Apply Now</button></Link>
    //       </div>
          
    //     </div>
        
    //   </div>
    // </div>
  );
}
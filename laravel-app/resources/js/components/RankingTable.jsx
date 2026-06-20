import "./styles/Tables.css";
import * as React from "react";
import { useTable } from "react-table";

function RankBadge({ rank }) {
  return <span className="rank-badge">{rank}</span>;
}

function NameCell({ row }) {
  const { first_name, last_name, engagement_type } = row.original;
  return (
    <div>
      <strong>{first_name} {last_name}</strong>
      <div style={{ fontSize: "0.85rem", color: "#666" }}>{engagement_type}</div>
    </div>
  );
}

function ContactCell({ row }) {
  const { email, phone, city, province } = row.original;
  return (
    <div style={{ fontSize: "0.87rem" }}>
      <div>{email}</div>
      <div>{phone}</div>
      <div>{[city, province].filter(Boolean).join(", ")}</div>
    </div>
  );
}

function BreakdownBar({ label, value, colorClass }) {
  const safeValue = Math.min(Math.max(value || 0, 0), 100);
  return (
    <div className="bar-row">
      <span className="bar-label">{label}</span>
      <div className="bar-bg">
        <div className={colorClass} style={{ width: `${safeValue}%` }} />
      </div>
      <span className="bar-val">{Math.round(safeValue)}</span>
    </div>
  );
}

function FinalScoreCell({ row, pref }) {
  const r = row.original;
  const qualWeight = pref.qual_weight ?? 100;
  const presWeight = pref.pres_weight ?? 0;

  const presItems = [
    { label: "Formatting", value: r.formatting_score, weight: pref.formatting_weight ?? 25 },
    { label: "Language", value: r.language_score, weight: pref.language_weight ?? 25 },
    { label: "Conciseness", value: r.concise_score, weight: pref.concise_weight ?? 25 },
    { label: "Organization", value: r.organization_score, weight: pref.organization_weight ?? 25 },
  ].filter((item) => item.weight > 0);

  const layoutTips = r.layout_feedback ? Object.values(r.layout_feedback).flat() : [];

  return (
    <div className="hoverable">
      <span className="score-num">{r.final_score}</span>
      <span className="score-denom">/100</span>

      <div className="hover-detail">
        <div className="sub-header">
          Qualifications — {r.qualifications_score}/100
          <span className="sub-header-weight"> (×{qualWeight}%)</span>
        </div>
        <div className="breakdown">
          <BreakdownBar label="Skills" value={r.qualifications_score} colorClass="bar-fill-blue" />
          <BreakdownBar label="Experience" value={Math.min((r.experience / 5) * 100, 100)} colorClass="bar-fill-blue" />
        </div>
        {r.feedback?.length > 0 && (
          <ul className="fb">
            {r.feedback.map((f, i) => (
              <li key={i} className="fb-qual">{f}</li>
            ))}
          </ul>
        )}

        <div className="score-divider" />

        <div className="sub-header">
          Presentation — {r.presentation_score}/100
          <span className="sub-header-weight"> (×{presWeight}%)</span>
        </div>
        <div className="breakdown">
          {presItems.map((item) => (
            <BreakdownBar key={item.label} label={item.label} value={item.value} colorClass="bar-fill-purple" />
          ))}
        </div>
        {layoutTips.length > 0 && (
          <ul className="fb" style={{ marginTop: 4 }}>
            {layoutTips.map((tip, i) => (
              <li key={i} className="fb-pres">{tip}</li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

function DetailsCell({ row }) {
  const { skills, experience, highest_education, date_available } = row.original;
  return (
    <div style={{ fontSize: "0.87rem" }}>
      <div><strong>Skills:</strong> {Array.isArray(skills) && skills.length ? skills.join(", ") : "—"}</div>
      <div><strong>Experience:</strong> {experience} yr(s)</div>
      <div><strong>Education:</strong> {highest_education ?? "N/A"}</div>
      <div><strong>Available:</strong> {date_available ?? "N/A"}</div>
    </div>
  );
}

function DocumentsCell({ row }) {
  const { application_id, resume_path, tor_path, cert_path } = row.original;
  return (
    <div className="docs">
      {resume_path && <a href={`/files/${application_id}/resume`} target="_blank" rel="noopener noreferrer">📄 Resume</a>}
      {tor_path && <a href={`/files/${application_id}/tor`} target="_blank" rel="noopener noreferrer">📋 TOR</a>}
      {cert_path && <a href={`/files/${application_id}/cert`} target="_blank" rel="noopener noreferrer">🏅 Certificate</a>}
    </div>
  );
}

function EditPreferencesButton({ onClick }) {
  return (
    <button onClick={onClick} className="edit-pref-btn-ranking">
      Edit Preferences
    </button>
  );
}

function PrefBar({ pref }) {
  const presLabels = [
    pref.formatting_weight > 0 ? `Formatting ${pref.formatting_weight}%` : null,
    pref.language_weight > 0 ? `Language ${pref.language_weight}%` : null,
    pref.concise_weight > 0 ? `Conciseness ${pref.concise_weight}%` : null,
    pref.organization_weight > 0 ? `Organization ${pref.organization_weight}%` : null,
  ].filter(Boolean);

  return (
    <div className="pref-bar">
      <strong>Final Score:</strong> Qualifications {pref.qual_weight ?? 100}% · Presentation {pref.pres_weight ?? 0}%
      &ensp;|&ensp;
      <strong>Qualifications:</strong> Skills {pref.skills_weight ?? 35}% · Experience {pref.experience_weight ?? 20}% · Education {pref.education_weight ?? 25}% · Cert {pref.cert_weight ?? 10}%
      &ensp;|&ensp;
      <strong>Presentation:</strong> {presLabels.length ? presLabels.join(" · ") : "all equal (25% each)"}
    </div>
  );
}

// rankings: array of result objects (same shape ScreeningController::evaluateApplicants returns)
// pref: weight preferences object
function RankingTable({ jobTitle = "Job", rankings = [], pref = {}, onEditPreferences = () => {} }) {
  const data = React.useMemo(() => rankings, [rankings]);
  const columns = React.useMemo(
    () => [
      { Header: "Rank", id: "rank", accessor: (row, i) => i + 1, Cell: ({ value }) => <RankBadge rank={value} /> },
      { Header: "Candidate", accessor: "first_name", Cell: NameCell },
      { Header: "Contact", accessor: "email", Cell: ContactCell },
      { Header: "Final Score", accessor: "final_score", Cell: (props) => <FinalScoreCell {...props} pref={pref} /> },
      { Header: "Details", accessor: "skills", Cell: DetailsCell },
      { Header: "Documents", accessor: "resume_path", Cell: DocumentsCell },
    ],
    [pref]
  );

  const { getTableProps, getTableBodyProps, headerGroups, rows, prepareRow } =
    useTable({ columns, data });

  return (
    <div className="RankingTable">
      <div className="container">
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px" }}>
          <h1 style={{ margin: 0 }}>Ranking Results — {jobTitle}</h1>
          <EditPreferencesButton onClick={onEditPreferences} />
        </div>

        <PrefBar pref={pref} />

        <div className="themed-table-wrapper">
          <table {...getTableProps()}>
            <thead>
              {headerGroups.map((headerGroup) => (
                <tr {...headerGroup.getHeaderGroupProps()}>
                  {headerGroup.headers.map((column) => (
                    <th {...column.getHeaderProps()}>{column.render("Header")}</th>
                  ))}
                </tr>
              ))}
            </thead>
            <tbody {...getTableBodyProps()}>
              {rows.map((row) => {
                prepareRow(row);
                return (
                  <tr {...row.getRowProps()}>
                    {row.cells.map((cell) => (
                      <td {...cell.getCellProps()}>{cell.render("Cell")}</td>
                    ))}
                  </tr>
                );
              })}
              {rows.length === 0 && (
                <tr>
                  <td colSpan={6} style={{ textAlign: "center", padding: "20px" }}>
                    No ranked candidates yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

export default RankingTable;
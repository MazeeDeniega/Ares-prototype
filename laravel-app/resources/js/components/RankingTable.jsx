import "./Candidates.css";
import * as React from "react";
import { useTable } from "react-table";

function NameCell({ value, row }) {
  return (
    <div>
      <div style={{ fontWeight: 500 }}>{value}</div>
      <div style={{ fontSize: "0.85rem", color: "#3a6fd1" }}>
        {row.original.employment_type}
      </div>
    </div>
  );
}

function ContactCell({ row }) {
  const { email, phone, city, province } = row.original;
  return (
    <div style={{ fontSize: "0.9rem" }}>
      <div>{email}</div>
      <div>{phone}</div>
      <div>
        {city}, {province}
      </div>
    </div>
  );
}

function FinalScoreCell({ value }) {
  return (
    <div style={{ display: "flex", alignItems: "baseline", justifyContent: "center", gap: "2px" }}>
      <span style={{ fontSize: "1.5rem", fontWeight: 700 }}>{value}</span>
      <span style={{ fontSize: "0.85rem", color: "#555" }}>/100</span>
    </div>
  );
}

function DetailsCell({ row }) {
  const { skills, experience_years, education, available_date } = row.original;
  return (
    <div style={{ fontSize: "0.85rem", textAlign: "left" }}>
      <div>
        <strong>Skills:</strong> {skills || "—"}
      </div>
      <div>
        <strong>Experience:</strong> {experience_years} yr(s)
      </div>
      <div>
        <strong>Education:</strong> {education}
      </div>
      <div>
        <strong>Available:</strong> {available_date}
      </div>
    </div>
  );
}

function DocumentsCell({ row }) {
  const { resume_url, tor_url, certificate_url } = row.original;
  const linkStyle = { color: "#1a73e8", textDecoration: "underline", display: "block" };
  return (
    <div style={{ fontSize: "0.9rem" }}>
      {resume_url && (
        <a href={resume_url} target="_blank" rel="noopener noreferrer" style={linkStyle}>
          Resume
        </a>
      )}
      {tor_url && (
        <a href={tor_url} target="_blank" rel="noopener noreferrer" style={linkStyle}>
          TOR
        </a>
      )}
      {certificate_url && (
        <a href={certificate_url} target="_blank" rel="noopener noreferrer" style={linkStyle}>
          Certificate
        </a>
      )}
    </div>
  );
}

function EditPreferencesButton({ onClick }) {
  return (
    <button
      onClick={onClick}
      style={{
        backgroundColor: "#fff",
        color: "#3a6fd1",
        border: "1px solid #b3c7ee",
        borderRadius: "8px",
        padding: "8px 20px",
        fontSize: "0.95rem",
        fontWeight: "500",
        cursor: "pointer",
      }}
    >
      Edit Preferences
    </button>
  );
}

function RankingTable({ onEditPreferences }) {
  const data = React.useMemo(() => [], []);
  const columns = React.useMemo(
    () => [
      {
        Header: "Rank",
        accessor: "rank",
      },
      {
        Header: "Name",
        accessor: "name",
        Cell: NameCell,
      },
      {
        Header: "Contact",
        accessor: "email",
        Cell: ContactCell,
      },
      {
        Header: "Final Score",
        accessor: "final_score",
        Cell: FinalScoreCell,
      },
      {
        Header: "Details",
        accessor: "details",
        Cell: DetailsCell,
      },
      {
        Header: "Documents",
        accessor: "documents",
        Cell: DocumentsCell,
      },
    ],
    []
  );

  const { getTableProps, getTableBodyProps, headerGroups, rows, prepareRow } =
    useTable({ columns, data });

  return (
    <div className="RankingTable">
      <div className="container">
        <div
          style={{
            display: "flex",
            justifyContent: "flex-end",
            marginBottom: "12px",
          }}
        >
          <EditPreferencesButton onClick={onEditPreferences} />
        </div>

        <table {...getTableProps()}>
          <thead>
            {headerGroups.map((headerGroup) => (
              <tr {...headerGroup.getHeaderGroupProps()}>
                {headerGroup.headers.map((column) => (
                  <th {...column.getHeaderProps()}>
                    {column.render("Header")}
                  </th>
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
                    <td {...cell.getCellProps()}> {cell.render("Cell")} </td>
                  ))}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default RankingTable;

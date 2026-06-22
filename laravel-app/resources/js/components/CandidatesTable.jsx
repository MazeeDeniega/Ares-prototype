import "./styles/Tables.css";
import * as React from "react";
import { useTable } from "react-table";

const statusStyles = {
  Pending: { backgroundColor: "#FF8C00", color: "#fff" },
  Approved: { backgroundColor: "#28a745", color: "#fff" },
  Rejected: { backgroundColor: "#dc3545", color: "#fff" },
  Interview: { backgroundColor: "#FFEB3B", color: "#000" },
};

function StatusBadge({ status }) {
  const style = statusStyles[status] || {};
  return (
    <span
      style={{
        ...style,
        padding: "4px 12px",
        borderRadius: "12px",
        fontWeight: "bold",
        fontSize: "0.85rem",
        display: "inline-block",
      }}
    >
      {status}
    </span>
  );
}

function CandidatesTable() {
  const [data, setData] = React.useState([]);

  React.useEffect(() => {
    fetch('/api/candidates', {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
    })
      .then(res => res.json())
      .then(json => setData(json))
      .catch(err => console.error('Failed to fetch candidates:', err));
  }, []);

  const columns = React.useMemo(
    () => [
      { Header: "Name", accessor: "Name" },
      { Header: "Contact", accessor: "Contact" },
      { Header: "Job Position", accessor: "job_position" },
      {
        Header: "Status",
        accessor: "status",
        Cell: ({ value }) => <StatusBadge status={value} />,
      },
      {
        Header: "Details",
        accessor: "details",
        Cell: ({ value }) => (
          <a href={value} target="_blank" rel="noopener noreferrer" style={{ color: "#1a73e8", textDecoration: "underline" }}>
            View Resume
          </a>
        ),
      },
    ],
    []
  );

  const { getTableProps, getTableBodyProps, headerGroups, rows, prepareRow } =
    useTable({ columns, data });

  return (
    <div className="candidates-page">
      <div className="themed-table-wrapper">
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
                    <td {...cell.getCellProps()}>{cell.render("Cell")}</td>
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

export default CandidatesTable;
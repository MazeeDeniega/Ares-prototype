import * as React from "react";

export const CANDIDATE_STATUSES = [
  { value: "pending", label: "Pending" },
  { value: "approved", label: "Approved" },
  { value: "rejected", label: "Rejected" },
  { value: "interview", label: "Interview" },
];

export const statusStyles = {
  Pending: { backgroundColor: "#f6b26b", color: "#000" },
  Approved: { backgroundColor: "#93f283", color: "#000" },
  Rejected: { backgroundColor: "#e05151", color: "#fff" },
  Interview: { backgroundColor: "#fff176", color: "#000" },
};

const badgeBaseStyle = {
  padding: "4px 12px",
  borderRadius: "12px",
  fontWeight: "bold",
  fontSize: "0.85rem",
  display: "inline-block",
};

export function formatStatusLabel(status) {
  const normalized = String(status ?? "pending").toLowerCase();
  if (normalized === "approved" || normalized === "accepted") return "Approved";
  if (normalized === "rejected") return "Rejected";
  if (normalized === "interview") return "Interview";
  return "Pending";
}

export function normalizeStatusValue(status) {
  const normalized = String(status ?? "pending").toLowerCase();
  if (normalized === "approved" || normalized === "accepted") return "approved";
  if (normalized === "rejected") return "rejected";
  if (normalized === "interview") return "interview";
  return "pending";
}

export function StatusBadge({ status }) {
  const label = formatStatusLabel(status);
  const style = statusStyles[label] || statusStyles.Pending;

  return (
    <span style={{ ...badgeBaseStyle, ...style }}>
      {label}
    </span>
  );
}

export function StatusSelect({ value, onChange, disabled = false }) {
  const normalizedValue = normalizeStatusValue(value);
  const label = formatStatusLabel(normalizedValue);
  const style = statusStyles[label] || statusStyles.Pending;

  return (
    <select
      className="candidate-status-select"
      value={normalizedValue}
      onChange={(e) => onChange(e.target.value)}
      disabled={disabled}
      style={{ ...badgeBaseStyle, ...style, border: "none", cursor: disabled ? "not-allowed" : "pointer" }}
      aria-label="Candidate status"
    >
      {CANDIDATE_STATUSES.map((option) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  );
}

import React from "react";

/** Reusable app sidebar + main area chrome. Matches the HTML video's app-shell UI. */

interface AppShellProps {
  activeNav: string;
  children: React.ReactNode;
}

const NAV_ITEMS = [
  { icon: "🏠", label: "Dashboard" },
  { icon: "🤝", label: "Partnerships" },
  { icon: "🔍", label: "Find Partners" },
  { icon: "🧾", label: "Cashier" },
  { icon: "⚙️", label: "Settings" },
];

export const AppShell: React.FC<AppShellProps> = ({ activeNav, children }) => (
  <div style={{ background: "#f3f4f6", borderRadius: 16, overflow: "hidden", boxShadow: "0 40px 100px rgba(0,0,0,0.5)", display: "flex", height: 480 }}>
    <div style={{ width: 200, background: "white", padding: "20px 14px", flexShrink: 0, borderRight: "1px solid #e5e7eb" }}>
      <div style={{ fontSize: 15, fontWeight: 800, color: "#111827", padding: "6px 10px 18px" }}>
        Hyper<span style={{ color: "#6366F1" }}>local</span>
      </div>
      {NAV_ITEMS.map((item) => (
        <div key={item.label} style={{
          display: "flex", alignItems: "center", padding: "9px 10px",
          borderRadius: 8, fontSize: 13, fontWeight: 500, marginBottom: 2,
          background: activeNav === item.label ? "#eef2ff" : "transparent",
          color: activeNav === item.label ? "#4F46E5" : "#6b7280",
        }}>
          <span style={{ marginRight: 8, fontSize: 15 }}>{item.icon}</span>
          {item.label}
        </div>
      ))}
    </div>
    <div style={{ flex: 1, padding: 24, overflow: "hidden", background: "#f9fafb" }}>
      {children}
    </div>
  </div>
);

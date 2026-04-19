import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, Easing } from "remotion";
import { FPS } from "../constants";

const popEase = Easing.bezier(0.22, 1, 0.36, 1);
function pop(frame: number, delay: number) {
  return {
    opacity: interpolate(frame, [delay, delay + 15], [0, 1], { extrapolateRight: "clamp", extrapolateLeft: "clamp" }),
    transform: `translateY(${interpolate(frame, [delay, delay + 15], [30, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp", easing: popEase })}px)`,
  };
}

function fmtNum(n: number): string {
  return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function counter(frame: number, start: number, target: number, dur: number, prefix = ""): string {
  const val = Math.round(interpolate(frame, [start, start + dur], [0, target], { extrapolateRight: "clamp", extrapolateLeft: "clamp", easing: Easing.out(Easing.cubic) }));
  return prefix + fmtNum(val);
}

export const Metrics: React.FC = () => {
  const frame = useCurrentFrame();
  const cStart = 18; // 0.6s delay

  return (
    <AbsoluteFill style={{
      background: "linear-gradient(180deg, #060610, #0d0d1f)",
      display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
      padding: "50px 80px", textAlign: "center" as const,
      fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
    }}>
      <div style={{ ...pop(frame, 0), fontSize: 13, fontWeight: 600, letterSpacing: "0.12em", textTransform: "uppercase" as const, color: "rgba(255,255,255,0.4)", marginBottom: 12 }}>Intelligence</div>
      <div style={{ ...pop(frame, 9), fontSize: 44, fontWeight: 800, color: "white", letterSpacing: -2, marginBottom: 4 }}>
        Know which partnerships<br/><span style={{ color: "#818cf8" }}>actually work.</span>
      </div>
      <div style={{ ...pop(frame, 9), fontSize: 12, color: "rgba(255,255,255,0.25)", marginBottom: 6 }}>Track ROI, retention, and reciprocity.</div>

      {/* 3 metric cards */}
      <div style={{ display: "flex", justifyContent: "center", gap: 24, margin: "30px 0 20px" }}>
        {[
          { color: "#818cf8", border: "rgba(99,102,241,0.25)", val: counter(frame, cStart, 247, 36), label: "New Customers", delay: 18 },
          { color: "#34d399", border: "rgba(34,197,94,0.25)", val: counter(frame, cStart + 6, 184000, 45, "₹"), label: "Revenue Generated", delay: 27 },
          { color: "#fbbf24", border: "rgba(251,191,36,0.25)", val: counter(frame, cStart + 12, 42600, 45, "₹"), label: "Net Referral Profit", delay: 36 },
        ].map((m) => (
          <div key={m.label} style={{
            ...pop(frame, m.delay), flex: 1, maxWidth: 260,
            background: "rgba(255,255,255,0.04)", border: `1px solid ${m.border}`,
            borderRadius: 18, padding: "28px 20px", textAlign: "center" as const,
          }}>
            <div style={{ fontSize: 42, fontWeight: 900, color: m.color, lineHeight: 1, marginBottom: 6 }}>{m.val}</div>
            <div style={{ fontSize: 13, color: "rgba(255,255,255,0.4)", fontWeight: 500 }}>{m.label}</div>
          </div>
        ))}
      </div>

      {/* Retention + ledger line */}
      <div style={{ display: "flex", justifyContent: "center", gap: 16, marginBottom: 24 }}>
        <div style={{ ...pop(frame, 36), fontSize: 13, color: "rgba(255,255,255,0.5)" }}>
          <span style={{ fontWeight: 800, color: "#34d399" }}>{counter(frame, cStart + 18, 65, 42)}%</span> return within 30 days
        </div>
        <div style={{ ...pop(frame, 48), fontSize: 13, color: "rgba(255,255,255,0.5)" }}>
          Net ledger: <span style={{ fontWeight: 800, color: "#34d399" }}>+₹{counter(frame, cStart + 24, 42000, 48)}</span>
        </div>
      </div>

      {/* Bullet row */}
      <div style={{ ...pop(frame, 48), display: "flex", flexWrap: "wrap" as const, justifyContent: "center", gap: "12px 28px" }}>
        {["No ad spend wasted", "Only pay when customers convert", "Every referral tracked", "ROI visible in real time"].map((t) => (
          <div key={t} style={{ display: "flex", alignItems: "center", fontSize: 15, color: "rgba(255,255,255,0.7)", fontWeight: 500 }}>
            <span style={{ color: "#22c55e", fontWeight: 800, marginRight: 8, fontSize: 14 }}>✓</span>{t}
          </div>
        ))}
      </div>
    </AbsoluteFill>
  );
};

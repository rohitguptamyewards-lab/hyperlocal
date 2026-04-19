import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, Easing } from "remotion";
import { AppShell } from "../shared/AppShell";

const popEase = Easing.bezier(0.22, 1, 0.36, 1);
function pop(frame: number, delay: number) {
  return {
    opacity: interpolate(frame, [delay, delay + 15], [0, 1], { extrapolateRight: "clamp", extrapolateLeft: "clamp" }),
    transform: `translateY(${interpolate(frame, [delay, delay + 15], [30, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp", easing: popEase })}px)`,
  };
}
function slideR(frame: number, delay: number) {
  return {
    opacity: interpolate(frame, [delay, delay + 18], [0, 1], { extrapolateRight: "clamp", extrapolateLeft: "clamp" }),
    transform: `translateX(${interpolate(frame, [delay, delay + 18], [80, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp", easing: popEase })}px)`,
  };
}

const Toggle: React.FC<{ label: string }> = ({ label }) => (
  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 6 }}>
    <div style={{ fontSize: 12, color: "#6b7280" }}>{label}</div>
    <div style={{ width: 34, height: 18, background: "#4F46E5", borderRadius: 9, position: "relative" as const }}>
      <div style={{ position: "absolute" as const, right: 2, top: 2, width: 14, height: 14, background: "white", borderRadius: "50%" }} />
    </div>
  </div>
);

export const GoLive: React.FC = () => {
  const frame = useCurrentFrame();
  const livePulse = frame > 24 ? Math.sin((frame - 24) * 0.2) * 0.5 + 0.5 : 0;

  return (
    <AbsoluteFill style={{ backgroundColor: "#0a0f1e", display: "flex", alignItems: "center", justifyContent: "center", padding: 48, fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif" }}>
      <div style={{ maxWidth: 400 }}>
        <div style={{ ...pop(frame, 0), fontSize: 13, fontWeight: 600, letterSpacing: "0.12em", textTransform: "uppercase" as const, color: "rgba(255,255,255,0.4)", marginBottom: 14 }}>Step 2</div>
        <div style={{ ...pop(frame, 9), fontSize: 42, fontWeight: 800, color: "white", letterSpacing: -1.5, lineHeight: 1.1, marginBottom: 14 }}>
          Define your<br /><span style={{ color: "#34d399" }}>partnership.</span>
        </div>
        <div style={{ ...pop(frame, 18), fontSize: 17, fontWeight: 500, color: "rgba(255,255,255,0.5)" }}>Set offers, caps, and rules. Both sides stay in control.</div>
        <ul style={{ listStyle: "none", marginTop: 20, ...pop(frame, 27) }}>
          {["Pause or stop any time — no contracts", "Each side manages their own settings"].map((t) => (
            <li key={t} style={{ display: "flex", alignItems: "flex-start", fontSize: 16, color: "rgba(255,255,255,0.8)", lineHeight: 1.4, marginBottom: 12 }}>
              <span style={{ color: "#22c55e", fontWeight: 700, fontSize: 15, marginRight: 10, marginTop: 1, flexShrink: 0 }}>✓</span>{t}
            </li>
          ))}
        </ul>
      </div>
      <div style={{ flex: 1, marginLeft: 48, ...slideR(frame, 6) }}>
        <AppShell activeNav="Partnerships">
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 16 }}>
            <div><div style={{ fontSize: 18, fontWeight: 800, color: "#111827" }}>Brew & Co × FitZone</div><div style={{ fontSize: 11, color: "#9ca3af" }}>Brand-wide · Created today</div></div>
            <div style={{ fontSize: 12, padding: "6px 14px", borderRadius: 20, fontWeight: 700, background: "#dcfce7", color: "#16a34a", boxShadow: `0 0 ${livePulse * 10}px rgba(22,163,74,${livePulse * 0.5})` }}>● Live</div>
          </div>
          <div style={{ background: "#eef2ff", border: "1px solid #c7d2fe", borderRadius: 10, padding: "14px 16px", marginBottom: 12 }}>
            <div style={{ fontSize: 13, fontWeight: 800, color: "#3730a3", marginBottom: 6 }}>Partnership Agreement</div>
            <div style={{ fontSize: 13, color: "#3730a3", lineHeight: 1.5 }}>
              Customers of <strong>Brew & Co</strong> visiting <strong>FitZone</strong><br />get up to <strong>10% off</strong> (max ₹500). Min bill: ₹200.
            </div>
          </div>
          <div style={{ background: "white", border: "1px solid #e5e7eb", borderRadius: 12, padding: "12px 14px" }}>
            <div style={{ fontSize: 12, fontWeight: 700, color: "#111827", marginBottom: 8 }}>My settings</div>
            <Toggle label="Issue tokens" />
            <Toggle label="Accept redemptions" />
          </div>
          <div style={{ ...pop(frame, 27), textAlign: "center" as const, marginTop: 14 }}>
            <div style={{ display: "inline-flex", alignItems: "center", background: "rgba(79,70,229,0.08)", border: "1px solid rgba(79,70,229,0.2)", borderRadius: 14, padding: "10px 24px" }}>
              <span style={{ fontSize: 14, fontWeight: 800, color: "#4F46E5" }}>☕ Brew & Co</span>
              <span style={{ fontSize: 24, margin: "0 12px" }}>🤝</span>
              <span style={{ fontSize: 14, fontWeight: 800, color: "#16a34a" }}>FitZone 🏋️</span>
            </div>
          </div>
        </AppShell>
      </div>
    </AbsoluteFill>
  );
};

import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, Easing } from "remotion";

const popEase = Easing.bezier(0.22, 1, 0.36, 1);
function pop(frame: number, delay: number) {
  return {
    opacity: interpolate(frame, [delay, delay + 15], [0, 1], { extrapolateRight: "clamp", extrapolateLeft: "clamp" }),
    transform: `translateY(${interpolate(frame, [delay, delay + 15], [30, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp", easing: popEase })}px)`,
  };
}

/**
 * Ledger scene — "Every exchange is tracked"
 * Shows the shared ledger concept: what you gave vs what you received.
 */
export const Ledger: React.FC = () => {
  const frame = useCurrentFrame();

  const givenVal = Math.round(interpolate(frame, [24, 60], [0, 42000], { extrapolateRight: "clamp", easing: Easing.out(Easing.cubic) }));
  const earnedVal = Math.round(interpolate(frame, [30, 66], [0, 84000], { extrapolateRight: "clamp", easing: Easing.out(Easing.cubic) }));
  const netVal = Math.round(interpolate(frame, [36, 72], [0, 42000], { extrapolateRight: "clamp", easing: Easing.out(Easing.cubic) }));

  const fmt = (n: number) => n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");

  return (
    <AbsoluteFill style={{
      background: "linear-gradient(180deg, #060610, #0d0d1f)",
      display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
      padding: "50px 100px", fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
    }}>
      <div style={{ ...pop(frame, 0), fontSize: 13, fontWeight: 600, letterSpacing: "0.12em", textTransform: "uppercase" as const, color: "rgba(255,255,255,0.4)", marginBottom: 14 }}>Core system</div>
      <div style={{ ...pop(frame, 9), fontSize: 44, fontWeight: 800, color: "white", letterSpacing: -1.5, lineHeight: 1.1, textAlign: "center" as const, marginBottom: 8 }}>
        Every exchange is <span style={{ color: "#818cf8" }}>tracked.</span>
      </div>
      <div style={{ ...pop(frame, 15), fontSize: 17, color: "rgba(255,255,255,0.45)", textAlign: "center" as const, marginBottom: 36 }}>
        A shared ledger records what each merchant gives and receives.
      </div>

      {/* Ledger card */}
      <div style={{ ...pop(frame, 18), width: 560, background: "rgba(255,255,255,0.04)", border: "1px solid rgba(255,255,255,0.1)", borderRadius: 20, padding: "32px 40px" }}>
        {/* Partnership header */}
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 24 }}>
          <div>
            <div style={{ fontSize: 16, fontWeight: 800, color: "white" }}>Brew & Co × FitZone</div>
            <div style={{ fontSize: 12, color: "rgba(255,255,255,0.3)", marginTop: 2 }}>Monthly settlement · March 2026</div>
          </div>
          <div style={{ fontSize: 11, fontWeight: 700, color: "#34d399", background: "rgba(34,197,94,0.12)", padding: "4px 12px", borderRadius: 8 }}>Settled</div>
        </div>

        {/* Cost row */}
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "14px 0", borderBottom: "1px solid rgba(255,255,255,0.06)" }}>
          <div style={{ fontSize: 14, color: "rgba(255,255,255,0.5)" }}>Benefit given to partner's customers</div>
          <div style={{ fontSize: 22, fontWeight: 800, color: "#f87171" }}>₹{fmt(givenVal)}</div>
        </div>

        {/* Revenue row */}
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "14px 0", borderBottom: "1px solid rgba(255,255,255,0.06)" }}>
          <div style={{ fontSize: 14, color: "rgba(255,255,255,0.5)" }}>Revenue from partner's customers visiting you</div>
          <div style={{ fontSize: 22, fontWeight: 800, color: "#34d399" }}>₹{fmt(earnedVal)}</div>
        </div>

        {/* Net row */}
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "16px 0 4px", marginTop: 4 }}>
          <div style={{ fontSize: 15, fontWeight: 700, color: "white" }}>Net value</div>
          <div style={{ fontSize: 28, fontWeight: 900, color: "#34d399" }}>+₹{fmt(netVal)}</div>
        </div>

        <div style={{ ...pop(frame, 48), marginTop: 16, padding: "10px 16px", background: "rgba(34,197,94,0.08)", borderRadius: 10, fontSize: 12, color: "rgba(255,255,255,0.4)", textAlign: "center" as const }}>
          Both sides see the same records. Automated monthly settlements.
        </div>
      </div>
    </AbsoluteFill>
  );
};

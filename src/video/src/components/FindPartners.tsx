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

export const FindPartners: React.FC = () => {
  const frame = useCurrentFrame();
  const Badge: React.FC<{ bg: string; color: string; children: React.ReactNode }> = ({ bg, color, children }) => (
    <span style={{ display: "inline-block", padding: "4px 10px", borderRadius: 20, fontSize: 11, fontWeight: 700, background: bg, color }}>{children}</span>
  );

  return (
    <AbsoluteFill style={{ backgroundColor: "#0d0d1a", display: "flex", alignItems: "center", justifyContent: "center", padding: 48, fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif" }}>
      {/* Left text */}
      <div style={{ maxWidth: 400 }}>
        <div style={{ ...pop(frame, 0), fontSize: 13, fontWeight: 600, letterSpacing: "0.12em", textTransform: "uppercase" as const, color: "rgba(255,255,255,0.4)", marginBottom: 14 }}>Step 1</div>
        <div style={{ ...pop(frame, 9), fontSize: 42, fontWeight: 800, color: "white", letterSpacing: -1.5, lineHeight: 1.1, marginBottom: 14 }}>
          Find nearby<br /><span style={{ color: "#818cf8" }}>partner businesses.</span>
        </div>
        <div style={{ ...pop(frame, 18), fontSize: 17, fontWeight: 500, color: "rgba(255,255,255,0.5)" }}>Matched by location, category, and customer fit.</div>
      </div>

      {/* Right: App Shell */}
      <div style={{ flex: 1, marginLeft: 48, ...slideR(frame, 6) }}>
        <AppShell activeNav="Find Partners">
          <div style={{ fontSize: 18, fontWeight: 800, color: "#111827", marginBottom: 3 }}>Find Partners</div>
          <div style={{ fontSize: 12, color: "#9ca3af", marginBottom: 16 }}>Auto-suggested matches near you</div>
          <div style={{ display: "flex", marginBottom: 18 }}>
            <div style={{ flex: "0 0 80px", background: "white", border: "1px solid #e5e7eb", borderRadius: 8, padding: "7px 10px", fontSize: 12, color: "#6b7280", marginRight: 8 }}>Mumbai</div>
            <div style={{ flex: 1, background: "white", border: "1px solid #e5e7eb", borderRadius: 8, padding: "7px 10px", fontSize: 12, color: "#6b7280", marginRight: 8 }}>All categories</div>
            <div style={{ flex: "0 0 70px", background: "#4F46E5", borderRadius: 8, padding: 7, fontSize: 12, color: "white", fontWeight: 700, textAlign: "center" as const }}>Search</div>
          </div>

          {/* Card 1: Best match */}
          <div style={{ ...pop(frame, 18), background: "white", border: "1px solid #e0e7ff", borderRadius: 12, padding: "16px 18px", marginBottom: 10 }}>
            <div style={{ display: "flex", alignItems: "center" }}>
              <div style={{ width: 40, height: 40, borderRadius: 10, background: "#f0fdf4", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 20, flexShrink: 0, marginRight: 10 }}>🏋️</div>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 14, fontWeight: 800, color: "#111827" }}>FitZone Gyms</div>
                <div style={{ fontSize: 11, color: "#9ca3af", marginTop: 1 }}>Gym · 2 outlets · Bandra</div>
              </div>
              <div style={{ textAlign: "right" as const }}>
                <div style={{ marginBottom: 6 }}><Badge bg="#dcfce7" color="#16a34a">90% match</Badge></div>
                <div style={{ background: "#4F46E5", color: "white", fontSize: 11, fontWeight: 700, padding: "5px 12px", borderRadius: 7 }}>Partner up</div>
              </div>
            </div>
            <div style={{ marginTop: 8, background: "#f9fafb", borderRadius: 7, padding: "7px 10px" }}>
              <div style={{ display: "flex", alignItems: "center" }}>
                <div style={{ fontSize: 10, fontWeight: 800, color: "#6366F1", letterSpacing: "0.05em", marginRight: 8 }}>90% FIT</div>
                <div style={{ flex: 1, height: 5, background: "#e0e7ff", borderRadius: 3 }}><div style={{ width: "90%", height: "100%", background: "#6366F1", borderRadius: 3 }} /></div>
              </div>
              <div style={{ fontSize: 10, color: "#16a34a", fontWeight: 700, marginTop: 4 }}>Most likely to send you new customers</div>
            </div>
          </div>

          {/* Card 2 */}
          <div style={{ ...pop(frame, 27), background: "white", border: "1px solid #e5e7eb", borderRadius: 12, padding: "16px 18px", marginBottom: 10 }}>
            <div style={{ display: "flex", alignItems: "center" }}>
              <div style={{ width: 40, height: 40, borderRadius: 10, background: "#fff7ed", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 20, flexShrink: 0, marginRight: 10 }}>📚</div>
              <div style={{ flex: 1 }}><div style={{ fontSize: 14, fontWeight: 800, color: "#111827" }}>BookNook</div><div style={{ fontSize: 11, color: "#9ca3af" }}>Bookstore · Bandra</div></div>
              <Badge bg="#fef3c7" color="#d97706">72% match</Badge>
            </div>
          </div>

          {/* Card 3 */}
          <div style={{ ...pop(frame, 36), opacity: 0.5, background: "white", border: "1px solid #e5e7eb", borderRadius: 12, padding: "16px 18px" }}>
            <div style={{ display: "flex", alignItems: "center" }}>
              <div style={{ width: 40, height: 40, borderRadius: 10, background: "#fef2f2", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 20, flexShrink: 0, marginRight: 10 }}>🍕</div>
              <div style={{ flex: 1 }}><div style={{ fontSize: 14, fontWeight: 800, color: "#111827" }}>Pizza Palace</div><div style={{ fontSize: 11, color: "#9ca3af" }}>Restaurant · Bandra</div></div>
              <Badge bg="#f3f4f6" color="#6b7280">58%</Badge>
            </div>
          </div>
        </AppShell>
      </div>
    </AbsoluteFill>
  );
};

import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, Easing } from "remotion";

const popEase = Easing.bezier(0.22, 1, 0.36, 1);
function pop(frame: number, delay: number) {
  return {
    opacity: interpolate(frame, [delay, delay + 15], [0, 1], { extrapolateRight: "clamp", extrapolateLeft: "clamp" }),
    transform: `translateY(${interpolate(frame, [delay, delay + 15], [30, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp", easing: popEase })}px)`,
  };
}

export const CTA: React.FC = () => {
  const frame = useCurrentFrame();
  const pulse = Math.sin(frame * 0.1) * 0.5 + 0.5;
  const glow = 30 + pulse * 30;

  return (
    <AbsoluteFill style={{
      backgroundColor: "#0a0a0f",
      display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
      fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
      position: "relative" as const, overflow: "hidden",
    }}>
      {/* Floating particles */}
      {Array.from({ length: 30 }).map((_, i) => {
        const x = ((i * 43 + 17) % 100);
        const speed = 4 + (i % 5) * 1.5;
        const size = 1.5 + (i % 3);
        const y = ((frame * speed * 0.01 + i * 7) % 120) - 10;
        const colors = ["#6366F1", "#22c55e", "#f59e0b", "#818cf8", "#34d399"];
        return (
          <div key={i} style={{
            position: "absolute" as const, left: `${x}%`, bottom: `${100 - y}%`,
            width: size, height: size, borderRadius: "50%",
            background: colors[i % 5], opacity: 0.4, pointerEvents: "none" as const,
          }} />
        );
      })}

      {/* Expanding rings */}
      {[0, 1, 2, 3, 4].map((i) => {
        const ringFrame = (frame + i * 24) % 120;
        const scale = 0.3 + (ringFrame / 120) * 2.2;
        const opacity = 0.6 * (1 - ringFrame / 120);
        return (
          <div key={i} style={{
            position: "absolute" as const, left: "50%", top: "40%",
            width: 200 + i * 100, height: 200 + i * 100,
            marginLeft: -(100 + i * 50), marginTop: -(100 + i * 50),
            borderRadius: "50%", border: "1px solid rgba(99,102,241,0.15)",
            transform: `scale(${scale})`, opacity, pointerEvents: "none" as const,
          }} />
        );
      })}

      <div style={{ position: "relative" as const, zIndex: 2, textAlign: "center" as const }}>
        {/* Logo */}
        <div style={{ ...pop(frame, 0), display: "flex", alignItems: "center", justifyContent: "center", marginBottom: 28 }}>
          <div style={{ width: 52, height: 52, borderRadius: 14, background: "#4F46E5", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 26, fontWeight: 900, color: "white", marginRight: 14, boxShadow: `0 0 ${glow}px rgba(79,70,229,0.4)` }}>H</div>
          <div style={{ fontSize: 30, fontWeight: 800, color: "white", letterSpacing: -1 }}><span style={{ color: "#818cf8" }}>Hyper</span>local Network</div>
        </div>

        <div style={{ ...pop(frame, 9), fontSize: 48, fontWeight: 800, color: "white", letterSpacing: -2, lineHeight: 1.1, maxWidth: 720, margin: "0 auto 14px" }}>
          Ready to see which brands<br/><span style={{ color: "#818cf8" }}>near you are a match?</span>
        </div>
        <div style={{ ...pop(frame, 18), fontSize: 20, fontWeight: 500, color: "rgba(255,255,255,0.5)" }}>
          Connect with your eWards team to get started.
        </div>
        <div style={{ ...pop(frame, 27) }}>
          <div style={{ display: "inline-block", background: "linear-gradient(135deg, #4F46E5, #7C3AED)", color: "white", padding: "18px 48px", borderRadius: 14, fontSize: 20, fontWeight: 800, marginTop: 36, boxShadow: `0 0 ${glow}px rgba(79,70,229,0.4)` }}>
            Visit hyperlocal.ewards.com →
          </div>
        </div>
        <div style={{ ...pop(frame, 36), background: "rgba(99,102,241,0.1)", border: "1px solid rgba(99,102,241,0.25)", color: "#a5b4fc", fontSize: 14, fontWeight: 600, padding: "8px 22px", borderRadius: 20, marginTop: 28, display: "inline-block" }}>
          hyperlocal.ewards.com · Talk to your eWards account manager
        </div>
      </div>
    </AbsoluteFill>
  );
};

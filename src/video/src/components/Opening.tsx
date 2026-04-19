import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, Easing } from "remotion";
import { COLORS } from "../constants";

const popEase = Easing.bezier(0.22, 1, 0.36, 1);

export const Opening: React.FC = () => {
  const frame = useCurrentFrame();
  const logoOpacity = interpolate(frame, [10, 25], [0, 1], { extrapolateRight: "clamp" });
  const logoScale = interpolate(frame, [10, 25], [0.8, 1], { extrapolateRight: "clamp", easing: popEase });
  const nameOpacity = interpolate(frame, [20, 35], [0, 1], { extrapolateRight: "clamp" });
  const nameY = interpolate(frame, [20, 35], [20, 0], { extrapolateRight: "clamp", easing: popEase });
  const glow = Math.sin(frame * 0.08) * 15 + 25;

  return (
    <AbsoluteFill style={{
      backgroundColor: COLORS.bg,
      display: "flex", flexDirection: "column",
      alignItems: "center", justifyContent: "center",
      fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
    }}>
      {/* Logo */}
      <div style={{
        opacity: logoOpacity,
        transform: `scale(${logoScale})`,
        width: 80, height: 80, borderRadius: 22,
        background: COLORS.brand,
        display: "flex", alignItems: "center", justifyContent: "center",
        fontSize: 42, fontWeight: 900, color: "white",
        boxShadow: `0 0 ${glow}px rgba(79,70,229,0.5)`,
        marginBottom: 20,
      }}>
        H
      </div>

      {/* Name */}
      <div style={{
        opacity: nameOpacity,
        transform: `translateY(${nameY}px)`,
        textAlign: "center" as const,
      }}>
        <div style={{ fontSize: 36, fontWeight: 800, color: "white", letterSpacing: -1 }}>
          <span style={{ color: COLORS.indigo }}>Hyper</span>local Network
        </div>
        <div style={{ fontSize: 14, color: "rgba(255,255,255,0.25)", marginTop: 8, fontStyle: "italic" }}>
          A collaboration layer for nearby merchants
        </div>
      </div>
    </AbsoluteFill>
  );
};

import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, Easing } from "remotion";
import { COLORS } from "../constants";

export const Closing: React.FC = () => {
  const frame = useCurrentFrame();
  const opacity = interpolate(frame, [0, 20], [0, 1], { extrapolateRight: "clamp" });
  const fadeOut = interpolate(frame, [120, 150], [1, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp" });

  return (
    <AbsoluteFill style={{
      backgroundColor: COLORS.bg,
      display: "flex", flexDirection: "column",
      alignItems: "center", justifyContent: "center",
      fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
      opacity: opacity * fadeOut,
    }}>
      <div style={{ fontSize: 20, fontWeight: 800, color: "white", letterSpacing: -0.5, marginBottom: 6 }}>
        <span style={{ color: COLORS.indigo }}>Hyper</span>local Network
      </div>
      <div style={{ fontSize: 13, color: "rgba(255,255,255,0.25)", marginTop: 4 }}>
        myewards.com
      </div>
    </AbsoluteFill>
  );
};

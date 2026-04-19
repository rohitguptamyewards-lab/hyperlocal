import React from "react";
import {
  AbsoluteFill,
  useCurrentFrame,
  interpolate,
  Easing,
  Img,
  staticFile,
} from "remotion";
import { COLORS } from "../constants";

const popEase = Easing.bezier(0.22, 1, 0.36, 1);

interface ScreenshotSlideProps {
  caption?: string;
  headline: string;
  subtext?: string;
  screenshotFile: string;
}

export const ScreenshotSlide: React.FC<ScreenshotSlideProps> = ({
  caption,
  headline,
  subtext,
  screenshotFile,
}) => {
  const frame = useCurrentFrame();

  const textOpacity = interpolate(frame, [0, 15], [0, 1], { extrapolateRight: "clamp" });
  const textY = interpolate(frame, [0, 15], [30, 0], { extrapolateRight: "clamp", easing: popEase });
  const imgOpacity = interpolate(frame, [9, 24], [0, 1], { extrapolateRight: "clamp" });
  const imgScale = interpolate(frame, [9, 24], [0.95, 1], { extrapolateRight: "clamp", easing: popEase });

  return (
    <AbsoluteFill
      style={{
        backgroundColor: COLORS.bg,
        fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
      }}
    >
      {/* Top section: text — takes ~35% height */}
      <div style={{
        position: "absolute", top: 0, left: 0, right: 0, height: "35%",
        display: "flex", flexDirection: "column",
        alignItems: "center", justifyContent: "center",
        padding: "20px 60px",
        opacity: textOpacity,
        transform: `translateY(${textY}px)`,
      }}>
        {caption && (
          <div style={{
            fontSize: 12, fontWeight: 600, letterSpacing: "0.12em",
            textTransform: "uppercase" as const, color: "rgba(255,255,255,0.4)",
            marginBottom: 10,
          }}>
            {caption}
          </div>
        )}
        <div
          style={{
            fontSize: 32, fontWeight: 800, color: COLORS.white,
            letterSpacing: -1, lineHeight: 1.2, textAlign: "center" as const,
          }}
          dangerouslySetInnerHTML={{ __html: headline }}
        />
        {subtext && (
          <div style={{
            fontSize: 14, fontWeight: 400, color: "rgba(255,255,255,0.4)",
            lineHeight: 1.5, textAlign: "center" as const,
            marginTop: 8, maxWidth: 600,
          }}>
            {subtext}
          </div>
        )}
      </div>

      {/* Bottom section: screenshot — takes ~65% height, centered with padding */}
      <div style={{
        position: "absolute", top: "33%", left: 0, right: 0, bottom: 0,
        display: "flex", alignItems: "center", justifyContent: "center",
        padding: "0 60px 30px",
        opacity: imgOpacity,
        transform: `scale(${imgScale})`,
      }}>
        <div style={{
          borderRadius: 12, overflow: "hidden",
          boxShadow: "0 20px 60px rgba(0,0,0,0.6)",
          border: "1px solid rgba(255,255,255,0.1)",
          maxHeight: "100%",
        }}>
          <Img
            src={staticFile(`screenshots/${screenshotFile}`)}
            style={{ maxHeight: 420, width: "auto", display: "block" }}
          />
        </div>
      </div>
    </AbsoluteFill>
  );
};

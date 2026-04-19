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

/**
 * HighlightScreenshotSlide
 *
 * Like ScreenshotSlide but with an optional soft vignette that dims the
 * non-highlighted region of the screenshot, drawing the viewer's eye to the
 * most important area (e.g. fit scores, KPI cards, terms fields).
 *
 * highlight regions:
 *   'top'    — spotlight the top third (KPI cards, dashboard headers)
 *   'bottom' — spotlight the bottom two-thirds (forms, terms fields)
 *   'left'   — spotlight the left half
 *   'right'  — spotlight the right half (fit score cards)
 *   'center' — spotlight the centre (modals, single CTA)
 *   undefined — no vignette, behaves identically to ScreenshotSlide
 */

type HighlightRegion = "top" | "bottom" | "left" | "right" | "center";

interface HighlightScreenshotSlideProps {
  caption?: string;
  headline: string;
  subtext?: string;
  screenshotFile: string;
  highlight?: HighlightRegion;
}

const popEase = Easing.bezier(0.22, 1, 0.36, 1);

/** Returns a CSS gradient mask that dims outside the highlighted region */
function vignetteGradient(region: HighlightRegion): string {
  // A dark semi-transparent overlay with a "hole" over the highlighted region.
  // We use a radial/linear gradient as a background on an overlay div.
  switch (region) {
    case "top":
      return "linear-gradient(to bottom, transparent 0%, transparent 38%, rgba(0,0,0,0.55) 65%, rgba(0,0,0,0.65) 100%)";
    case "bottom":
      return "linear-gradient(to bottom, rgba(0,0,0,0.55) 0%, rgba(0,0,0,0.35) 25%, transparent 45%, transparent 100%)";
    case "left":
      return "linear-gradient(to right, transparent 0%, transparent 45%, rgba(0,0,0,0.55) 70%, rgba(0,0,0,0.65) 100%)";
    case "right":
      return "linear-gradient(to right, rgba(0,0,0,0.55) 0%, rgba(0,0,0,0.35) 30%, transparent 50%, transparent 100%)";
    case "center":
      return "radial-gradient(ellipse 50% 45% at 50% 50%, transparent 0%, transparent 40%, rgba(0,0,0,0.55) 75%, rgba(0,0,0,0.65) 100%)";
    default:
      return "none";
  }
}

export const HighlightScreenshotSlide: React.FC<HighlightScreenshotSlideProps> = ({
  caption,
  headline,
  subtext,
  screenshotFile,
  highlight,
}) => {
  const frame = useCurrentFrame();

  const textOpacity = interpolate(frame, [0, 15], [0, 1], { extrapolateRight: "clamp" });
  const textY       = interpolate(frame, [0, 15], [30, 0], { extrapolateRight: "clamp", easing: popEase });
  const imgOpacity  = interpolate(frame, [9, 24],  [0, 1], { extrapolateRight: "clamp" });
  const imgScale    = interpolate(frame, [9, 24],  [0.95, 1], { extrapolateRight: "clamp", easing: popEase });

  // Subtle pulse on the highlighted region (vignette slightly breathes)
  const pulse = highlight
    ? interpolate(Math.sin(frame * 0.07), [-1, 1], [0.85, 1.0])
    : 1;

  return (
    <AbsoluteFill
      style={{
        backgroundColor: COLORS.bg,
        fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
      }}
    >
      {/* Text — top 35% */}
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
            textTransform: "uppercase" as const,
            color: "rgba(255,255,255,0.4)", marginBottom: 10,
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

      {/* Screenshot — bottom 65% */}
      <div style={{
        position: "absolute", top: "33%", left: 0, right: 0, bottom: 0,
        display: "flex", alignItems: "center", justifyContent: "center",
        padding: "0 60px 30px",
        opacity: imgOpacity,
        transform: `scale(${imgScale})`,
      }}>
        <div style={{
          position: "relative",
          borderRadius: 12, overflow: "hidden",
          boxShadow: "0 20px 60px rgba(0,0,0,0.6)",
          border: "1px solid rgba(255,255,255,0.1)",
          maxHeight: "100%",
        }}>
          <Img
            src={staticFile(`screenshots/${screenshotFile}`)}
            style={{ maxHeight: 420, width: "auto", display: "block" }}
          />
          {/* Vignette overlay — dims non-highlighted regions */}
          {highlight && (
            <div style={{
              position: "absolute", inset: 0,
              background: vignetteGradient(highlight),
              opacity: pulse,
              pointerEvents: "none" as const,
              borderRadius: 12,
            }} />
          )}
        </div>
      </div>
    </AbsoluteFill>
  );
};

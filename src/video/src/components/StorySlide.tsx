import React from "react";
import {
  AbsoluteFill,
  useCurrentFrame,
  interpolate,
  Easing,
} from "remotion";
import { COLORS } from "../constants";
import { StoryLine } from "../types";

/**
 * StorySlide — Text-on-dark with staggered pop-in animation.
 * Replicates the `.pop` CSS animation from the HTML video.
 * Used for all 24 story slides (19 standard + 5 inhibition variants handled separately).
 */

interface StorySlideProps {
  lines: StoryLine[];
  background?: string;
}

const STYLE_MAP: Record<
  StoryLine["style"],
  { fontSize: number; fontWeight: number; color: string; lineHeight: number }
> = {
  headline: {
    fontSize: 46,
    fontWeight: 800,
    color: COLORS.white,
    lineHeight: 1.2,
  },
  subhead: {
    fontSize: 22,
    fontWeight: 400,
    color: COLORS.dimText,
    lineHeight: 1.4,
  },
  small: {
    fontSize: 20,
    fontWeight: 500,
    color: "rgba(255,255,255,0.55)",
    lineHeight: 1.5,
  },
  dim: {
    fontSize: 13,
    fontWeight: 600,
    color: "rgba(255,255,255,0.4)",
    lineHeight: 1.4,
  },
};

/** Custom easing matching cubic-bezier(0.22, 1, 0.36, 1) */
const popEasing = Easing.bezier(0.22, 1, 0.36, 1);

const PopLine: React.FC<{
  line: StoryLine;
}> = ({ line }) => {
  const frame = useCurrentFrame();
  const delays = [0, 9, 18, 27, 36, 48]; // POP_DELAYS d0-d5
  const delay = delays[line.delayIndex] || 0;

  const opacity = interpolate(frame, [delay, delay + 15], [0, 1], {
    extrapolateRight: "clamp",
    extrapolateLeft: "clamp",
  });
  const translateY = interpolate(frame, [delay, delay + 15], [30, 0], {
    extrapolateRight: "clamp",
    extrapolateLeft: "clamp",
    easing: popEasing,
  });
  const scale = interpolate(frame, [delay, delay + 15], [0.95, 1], {
    extrapolateRight: "clamp",
    extrapolateLeft: "clamp",
    easing: popEasing,
  });

  const base = STYLE_MAP[line.style];

  return (
    <div
      style={{
        opacity,
        transform: `translateY(${translateY}px) scale(${scale})`,
        fontSize: line.fontSize || base.fontSize,
        fontWeight: base.fontWeight,
        color: line.color || base.color,
        lineHeight: base.lineHeight,
        letterSpacing: line.style === "headline" ? "-1.5px" : undefined,
        marginBottom: 8,
        textAlign: "center",
      }}
      dangerouslySetInnerHTML={{ __html: line.text }}
    />
  );
};

export const StorySlide: React.FC<StorySlideProps> = ({
  lines,
  background,
}) => {
  return (
    <AbsoluteFill
      style={{
        backgroundColor: background || COLORS.bg,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        flexDirection: "column",
        padding: "0 120px",
        fontFamily:
          "-apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', sans-serif",
      }}
    >
      {lines.map((line, i) => (
        <PopLine key={i} line={line} />
      ))}
    </AbsoluteFill>
  );
};

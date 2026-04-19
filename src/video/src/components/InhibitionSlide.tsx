import React from "react";
import {
  AbsoluteFill,
  useCurrentFrame,
  interpolate,
  Easing,
} from "remotion";
import { COLORS } from "../constants";

/**
 * InhibitionSlide — Question (grey italic) → Answer (bold white).
 * Used for slides 18-22 (Act 5: killing objections).
 */

interface InhibitionSlideProps {
  question: string;
  answerLines: string[];
  answerColor?: string;
}

const popEasing = Easing.bezier(0.22, 1, 0.36, 1);

export const InhibitionSlide: React.FC<InhibitionSlideProps> = ({
  question,
  answerLines,
  answerColor,
}) => {
  const frame = useCurrentFrame();

  // Question fades in at frame 0
  const qOpacity = interpolate(frame, [0, 12], [0, 1], {
    extrapolateRight: "clamp",
    extrapolateLeft: "clamp",
  });
  const qY = interpolate(frame, [0, 12], [20, 0], {
    extrapolateRight: "clamp",
    extrapolateLeft: "clamp",
    easing: popEasing,
  });

  // Answer fades in at frame 15 (0.5s)
  const aOpacity = interpolate(frame, [15, 27], [0, 1], {
    extrapolateRight: "clamp",
    extrapolateLeft: "clamp",
  });
  const aY = interpolate(frame, [15, 27], [30, 0], {
    extrapolateRight: "clamp",
    extrapolateLeft: "clamp",
    easing: popEasing,
  });
  const aScale = interpolate(frame, [15, 27], [0.95, 1], {
    extrapolateRight: "clamp",
    extrapolateLeft: "clamp",
    easing: popEasing,
  });

  return (
    <AbsoluteFill
      style={{
        backgroundColor: COLORS.bg,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        flexDirection: "column",
        padding: "0 120px",
        fontFamily:
          "-apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', sans-serif",
      }}
    >
      {/* Question */}
      <div
        style={{
          opacity: qOpacity,
          transform: `translateY(${qY}px)`,
          fontSize: 20,
          fontWeight: 500,
          color: "rgba(255,255,255,0.35)",
          fontStyle: "italic",
          marginBottom: 16,
          textAlign: "center",
        }}
      >
        "{question}"
      </div>

      {/* Answer */}
      <div
        style={{
          opacity: aOpacity,
          transform: `translateY(${aY}px) scale(${aScale})`,
          textAlign: "center",
        }}
      >
        {answerLines.map((line, i) => (
          <div
            key={i}
            style={{
              fontSize: 40,
              fontWeight: 800,
              color: answerColor || COLORS.white,
              letterSpacing: "-1px",
              lineHeight: 1.2,
            }}
            dangerouslySetInnerHTML={{ __html: line }}
          />
        ))}
      </div>
    </AbsoluteFill>
  );
};

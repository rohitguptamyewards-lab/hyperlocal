import React from "react";
import { AbsoluteFill, Audio, Series, staticFile, interpolate, useCurrentFrame } from "remotion";
import { SCENES, COLORS, TOTAL_FRAMES } from "./constants";
import { StorySlide } from "./components/StorySlide";
import { ScreenshotSlide } from "./components/ScreenshotSlide";
import { CTA } from "./components/CTA";
import { Opening } from "./components/Opening";
import { Closing } from "./components/Closing";
import { STORY_SLIDES } from "./data/slides";

const Gap: React.FC = () => <AbsoluteFill style={{ backgroundColor: COLORS.bg }} />;

/**
 * v13 — Retailer-first.
 * Business truth (2 slides) → Product flow as steps (5 screenshots) → Trust (2) → Close (3)
 */
function renderScene(id: string): React.ReactNode {
  if (id === "open") return <Opening />;
  if (id === "close") return <Closing />;

  const slide = STORY_SLIDES.find((s) => s.id === id);
  if (slide) return <StorySlide lines={slide.lines} background={slide.background} />;

  switch (id) {
    case "find":
      return (
        <ScreenshotSlide
          caption="Step 1"
          headline={`Find the right brands <span style="color:${COLORS.green}">near you.</span>`}
          subtext="Search by city and category. See fit scores. Propose a partnership — go live in minutes."
          screenshotFile="final/M-05-find-partners.png"
        />
      );
    case "control":
      return (
        <ScreenshotSlide
          caption="Step 2"
          headline={`Define your offer. Set your <span style="color:${COLORS.indigo}">limits.</span>`}
          subtext="Cap per bill. Monthly ceiling. Minimum spend. Pause anytime. Each brand controls their own side."
          screenshotFile="final/M-04-partnership-detail.png"
        />
      );
    case "cust":
      return (
        <ScreenshotSlide
          caption="Step 3"
          headline={`A nearby customer <span style="color:${COLORS.green}">walks into your store.</span>`}
          subtext="Claims a reward and walks in — with a simple reward flow."
          screenshotFile="final/C-03-rewards.png"
        />
      );
    case "results":
      return (
        <ScreenshotSlide
          caption="Step 4"
          headline={`You see <span style="color:${COLORS.green}">exactly</span> what happened.`}
          subtext="Track new customers, revenue, benefit cost, and repeat visits — per partner."
          screenshotFile="final/M-02-dashboard.png"
        />
      );
    case "reach":
      return (
        <ScreenshotSlide
          caption="Go further"
          headline={`Send offers to partner audiences<br/>via <span style="color:${COLORS.indigo}">WhatsApp.</span>`}
          subtext="Pre-approved templates. Delivery tracking. Without ever seeing their customer data."
          screenshotFile="final/M-06-campaigns.png"
        />
      );
    case "cta": return <CTA />;
    default: return <Gap />;
  }
}

const BackgroundMusic: React.FC = () => {
  const frame = useCurrentFrame();
  const fadeIn = interpolate(frame, [0, 60], [0, 1], { extrapolateRight: "clamp" });
  const fadeOut = interpolate(frame, [TOTAL_FRAMES - 90, TOTAL_FRAMES], [1, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp" });
  return (
    <Audio
      src={staticFile("audio/ambient-pad.mp3")}
      volume={fadeIn * fadeOut * 0.35}
    />
  );
};

export const Video: React.FC = () => (
  <AbsoluteFill>
    <BackgroundMusic />
    <Series>
      {SCENES.map((scene) => (
        <Series.Sequence key={scene.id} durationInFrames={scene.durationFrames}>
          {renderScene(scene.id)}
        </Series.Sequence>
      ))}
    </Series>
  </AbsoluteFill>
);

/**
 * VIDEO B — "Your Neighbourhood Works For You" (~81s) — v17 VIRAL
 *
 * Audience: cold merchant — social, sales reel, scroll-stopping context.
 * Angle: proof-first. Opens on a named, specific result. No preamble.
 *
 * Structure:
 *   Named proof → local insight → what it is → product in 30s →
 *   customer moment → numbers → payoff → CTA
 *
 * First screenshot appears at ~22s. 4 screenshots total.
 * All text-only scenes use StorySlide. All existing components.
 */
import React from "react";
import { AbsoluteFill, Audio, Series, staticFile, interpolate, useCurrentFrame } from "remotion";
import { COLORS } from "./constants";
import { StorySlide } from "./components/StorySlide";
import { ScreenshotSlide } from "./components/ScreenshotSlide";
import { HighlightScreenshotSlide } from "./components/HighlightScreenshotSlide";
import { CTA } from "./components/CTA";
import { Opening } from "./components/Opening";
import { Closing } from "./components/Closing";
import { StoryLine } from "./types";

const FPS = 30;
const TOTAL_SEC = 81;
export const VIDEO_B_FRAMES = TOTAL_SEC * FPS;

function s(id: string, start: number, end: number) {
  return { id, durationFrames: Math.round((end - start) * FPS) };
}

const SCENES = [
  s("open",     0,   3),  // Logo
  s("proof",    3,   9),  // 400 FitZone members. 20 new customers. Zero ads.
  s("insight",  9,  16),  // They were passing Brew & Co every week
  s("how",     16,  22),  // Hyperlocal Network lets nearby businesses...
  s("find",    22,  31),  // Screenshot M-05 — find the right partner
  s("offer",   31,  40),  // Screenshot M-03b — set your offer
  s("customer",40,  50),  // Screenshot C-04-expanded — what the customer sees
  s("numbers", 50,  59),  // Screenshot M-02 — the result
  s("payoff",  59,  66),  // Your neighbourhood has been working against you. Now it works for you.
  s("cta",     66,  77),  // CTA
  s("close",   77,  81),  // End card
];

const C = COLORS;

const SLIDES: Record<string, StoryLine[]> = {
  proof: [
    { text: `<span style="color:${C.amber}">400 FitZone members.</span>`, style: "headline", fontSize: 50, delayIndex: 0 },
    { text: `One café 200 metres away.`, style: "subhead", fontSize: 22, delayIndex: 1 },
    { text: `<span style="color:${C.green}">20 new customers last month. Zero ads.</span>`, style: "headline", fontSize: 36, delayIndex: 2 },
  ],
  insight: [
    { text: `They were passing Brew & Co every week.`, style: "subhead", fontSize: 22, delayIndex: 0 },
    { text: `They just needed a reason to walk in.`, style: "headline", fontSize: 44, delayIndex: 1 },
  ],
  how: [
    { text: `Hyperlocal Network lets nearby businesses`, style: "subhead", fontSize: 20, delayIndex: 0 },
    { text: `<span style="color:${C.indigo}">send each other the right customers —</span>`, style: "headline", fontSize: 38, delayIndex: 1 },
    { text: `<span style="color:${C.green}">with full control.</span>`, style: "headline", fontSize: 38, delayIndex: 2 },
  ],
  payoff: [
    { text: `Your neighbourhood has been working against you.`, style: "subhead", fontSize: 22, delayIndex: 0 },
    { text: `<span style="color:${C.green}">Now it works for you.</span>`, style: "headline", fontSize: 58, delayIndex: 1 },
  ],
};

function renderScene(id: string): React.ReactNode {
  if (id === "open")  return <Opening />;
  if (id === "close") return <Closing />;
  if (id === "cta")   return <CTA />;

  const slide = SLIDES[id];
  if (slide) return <StorySlide lines={slide} />;

  switch (id) {
    case "find":
      return <HighlightScreenshotSlide
        caption="Find the right partner"
        headline={`Fit score tells you <span style="color:${C.green}">who to approach first.</span>`}
        subtext="Search by city and category. Complementary brands surface at the top — no guessing."
        screenshotFile="final/M-05-find-partners.png"
        highlight="right"
      />;
    case "offer":
      return <HighlightScreenshotSlide
        caption="Set your offer"
        headline={`Your rules. Your caps. <span style="color:${C.indigo}">Your terms.</span>`}
        subtext="Cap per bill. Max benefit. Minimum spend. The other brand agrees before anything goes live."
        screenshotFile="final/M-03b-create-modal.png"
        highlight="bottom"
      />;
    case "customer":
      return <ScreenshotSlide
        caption="What the customer sees"
        headline={`They see it. They walk in. <span style="color:${C.green}">No app. No friction.</span>`}
        subtext="Outlet name, address, max benefit, exact conditions — all shown before they leave home."
        screenshotFile="final/C-04-rewards-expanded.png"
      />;
    case "numbers":
      return <HighlightScreenshotSlide
        caption="The result"
        headline={`<span style="color:${C.green}">20 new customers.</span> ₹32,680 net value.`}
        subtext="Every visit tracked. New vs existing vs reactivated. ROI per partner — visible at a glance."
        screenshotFile="final/M-02-dashboard.png"
        highlight="top"
      />;
    default:
      return <AbsoluteFill style={{ backgroundColor: C.bg }} />;
  }
}

const Music: React.FC = () => {
  const frame = useCurrentFrame();
  const vol = interpolate(frame, [0, 60], [0, 1], { extrapolateRight: "clamp" })
    * interpolate(frame, [VIDEO_B_FRAMES - 90, VIDEO_B_FRAMES], [1, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp" })
    * 0.35;
  return <Audio src={staticFile("audio/ambient-pad.mp3")} volume={vol} />;
};

export const VideoB: React.FC = () => (
  <AbsoluteFill>
    <Music />
    <Series>
      {SCENES.map((scene) => (
        <Series.Sequence key={scene.id} durationInFrames={scene.durationFrames}>
          {renderScene(scene.id)}
        </Series.Sequence>
      ))}
    </Series>
  </AbsoluteFill>
);

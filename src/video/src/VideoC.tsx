/**
 * VIDEO C — "The Business Playbook" (~120s) — v17 SALES DEMO
 *
 * Audience: merchant already interested — sales meeting, product walkthrough.
 * Angle: structured business playbook. Answers every objection in order.
 *
 * Structure:
 *   Problem → concept → 3 explicit use cases → easy start →
 *   manage → results → safety → mutual → CTA
 *
 * First screenshot at ~35s. 6 screenshots total.
 * Scenes 1–6 are all text — no product until the use cases section.
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
const TOTAL_SEC = 120;
export const VIDEO_C_FRAMES = TOTAL_SEC * FPS;

function s(id: string, start: number, end: number) {
  return { id, durationFrames: Math.round((end - start) * FPS) };
}

const SCENES = [
  s("open",    0,   3),  // Logo
  s("hook",    3,  10),  // Getting new customers is a grind
  s("insight",10,  16),  // Right customers are already nearby
  s("gap",    16,  22),  // No simple way to help each other grow
  s("flip",   22,  28),  // What if brands around you could send customers?
  s("concept",28,  35),  // Hyperlocal Network — full control, structured ways
  s("uc1",    35,  44),  // Screenshot M-03b — distribute offers
  s("uc2",    44,  53),  // Screenshot M-06 — campaigns to partner audiences
  s("uc3",    53,  62),  // Screenshot C-04-expanded — extend loyalty
  s("start",  62,  68),  // Start small
  s("setup",  68,  76),  // Screenshot M-05 — find, propose, go live
  s("control",76,  84),  // Screenshot M-04 — stay in control
  s("results",84,  93),  // Screenshot M-02 — 25+ customers, ₹35k revenue
  s("safety", 93,  99),  // Your customers stay yours
  s("mutual", 99, 105),  // They send you. You send them.
  s("cta",   105, 116),  // CTA
  s("close", 116, 120),  // End card
];

const C = COLORS;

const SLIDES: Record<string, StoryLine[]> = {
  hook: [
    { text: `<span style="color:${C.amber}">Getting new customers is a grind.</span>`, style: "headline", fontSize: 46, delayIndex: 0 },
    { text: `Ads are expensive.`, style: "subhead", fontSize: 22, delayIndex: 1 },
    { text: `Discounts don't build loyalty.`, style: "subhead", fontSize: 22, delayIndex: 2 },
  ],
  insight: [
    { text: `But the right customers are already nearby.`, style: "subhead", fontSize: 22, delayIndex: 0 },
    { text: `<span style="color:${C.green}">They're spending in your area — every week.</span>`, style: "headline", fontSize: 42, delayIndex: 1 },
  ],
  gap: [
    { text: `And yet, they've never walked into your store.`, style: "headline", fontSize: 36, delayIndex: 0 },
    { text: `Because there's no simple way for nearby businesses to help each other grow.`, style: "subhead", fontSize: 18, delayIndex: 1 },
  ],
  flip: [
    { text: `What if the brands around you`, style: "subhead", fontSize: 24, delayIndex: 0 },
    { text: `<span style="color:${C.green}">could actually send customers to you?</span>`, style: "headline", fontSize: 50, delayIndex: 1 },
  ],
  concept: [
    { text: `Hyperlocal Network helps nearby businesses grow together —`, style: "subhead", fontSize: 20, delayIndex: 0 },
    { text: `<span style="color:${C.indigo}">with full control.</span>`, style: "headline", fontSize: 46, delayIndex: 1 },
    { text: `No data sharing. No chaos.`, style: "subhead", fontSize: 18, delayIndex: 2 },
    { text: `<em>Just structured ways to bring each other better customers.</em>`, style: "subhead", fontSize: 16, delayIndex: 3 },
  ],
  start: [
    { text: `<span style="color:${C.green}">Start small.</span>`, style: "headline", fontSize: 60, delayIndex: 0 },
    { text: `One nearby brand. One simple offer. One campaign.`, style: "subhead", fontSize: 20, delayIndex: 1 },
    { text: `That's it.`, style: "headline", fontSize: 42, delayIndex: 2 },
  ],
  safety: [
    { text: `Your customers stay yours.`, style: "headline", fontSize: 38, delayIndex: 0 },
    { text: `<span style="color:${C.indigo}">Your data stays yours.</span>`, style: "headline", fontSize: 38, delayIndex: 1 },
    { text: `<em>No contracts. No lock-in.</em>`, style: "subhead", fontSize: 20, delayIndex: 2 },
  ],
  mutual: [
    { text: `They send you customers.`, style: "headline", fontSize: 38, delayIndex: 0 },
    { text: `You send them customers.`, style: "headline", fontSize: 38, delayIndex: 1 },
    { text: `<span style="color:${C.green}">Your neighbourhood starts working for you.</span>`, style: "subhead", fontSize: 20, delayIndex: 2 },
  ],
};

function renderScene(id: string): React.ReactNode {
  if (id === "open")  return <Opening />;
  if (id === "close") return <Closing />;
  if (id === "cta")   return <CTA />;

  const slide = SLIDES[id];
  if (slide) return <StorySlide lines={slide} />;

  switch (id) {
    case "uc1":
      return <HighlightScreenshotSlide
        caption="1 — Let partner brands distribute your offers"
        headline={`Nearby brands share your rewards — <span style="color:${C.green}">based on rules you define.</span>`}
        subtext="You control who sees it, what they get, and how much you give. Cap per bill. Max benefit. Minimum spend."
        screenshotFile="final/M-03b-create-modal.png"
        highlight="bottom"
      />;
    case "uc2":
      return <ScreenshotSlide
        caption="2 — Send campaigns to partner audiences"
        headline={`Reach nearby customers <span style="color:${C.indigo}">through trusted brands.</span>`}
        subtext="Without ever seeing their customer list. Select audience → set offer → launch. No spam. No data leakage."
        screenshotFile="final/M-06-campaigns.png"
      />;
    case "uc3":
      return <ScreenshotSlide
        caption="3 — Extend your loyalty beyond your store"
        headline={`Your brand becomes more valuable — <span style="color:${C.green}">everywhere nearby.</span>`}
        subtext="Customers see where their rewards work, what they get, and exact conditions — before they walk in."
        screenshotFile="final/C-04-rewards-expanded.png"
      />;
    case "setup":
      return <HighlightScreenshotSlide
        caption="How you start"
        headline={`Find the right partner. Set your rules. <span style="color:${C.green}">Go live.</span>`}
        subtext="Search by city. Fit score surfaces the most complementary brands. Propose, agree, activate — in minutes."
        screenshotFile="final/M-05-find-partners.png"
        highlight="right"
      />;
    case "control":
      return <ScreenshotSlide
        caption="How you stay in control"
        headline={`Nothing runs <span style="color:${C.indigo}">without your approval.</span>`}
        subtext="Cap per bill. Max benefit. Minimum spend. Pause anytime. Each side manages its own settings independently."
        screenshotFile="final/M-04-partnership-detail.png"
      />;
    case "results":
      return <HighlightScreenshotSlide
        caption="What it brings you"
        headline={`<span style="color:${C.green}">25+ new customers.</span> ₹35,000+ revenue.`}
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
    * interpolate(frame, [VIDEO_C_FRAMES - 90, VIDEO_C_FRAMES], [1, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp" })
    * 0.35;
  return <Audio src={staticFile("audio/ambient-pad.mp3")} volume={vol} />;
};

export const VideoC: React.FC = () => (
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

/**
 * VERSION A — "The Platform" (~2m 10s)
 *
 * Audience: investors, enterprise buyers, platform operators.
 * Angle: top-down — show all 3 layers as a single managed ecosystem.
 * What's new vs prior versions: SA layer shown for the first time;
 * C-01-login and C-04-rewards-expanded used for the first time.
 *
 * Structure:
 *   Hook → SA dashboard → SA brands → [bridge] → Find → Create → Live →
 *   Dashboard → [bridge] → Customer login → Rewards → Expanded → Close
 */
import React from "react";
import { AbsoluteFill, Audio, Series, staticFile, interpolate, useCurrentFrame } from "remotion";
import { COLORS } from "./constants";
import { StorySlide } from "./components/StorySlide";
import { ScreenshotSlide } from "./components/ScreenshotSlide";
import { CTA } from "./components/CTA";
import { Opening } from "./components/Opening";
import { Closing } from "./components/Closing";
import { StoryLine } from "./types";

const FPS = 30;
const TOTAL_SEC = 131;
export const VIDEO_A_FRAMES = TOTAL_SEC * FPS;

function s(id: string, start: number, end: number) {
  return { id, durationFrames: Math.round((end - start) * FPS) };
}

const SCENES = [
  s("open",       0,   3),
  s("hook",       3,  10),
  s("sa_dash",   10,  19),
  s("sa_brands", 19,  28),
  s("bridge_m",  28,  34),
  s("m_find",    34,  44),
  s("m_create",  44,  53),
  s("m_live",    53,  63),
  s("m_results", 63,  73),
  s("bridge_c",  73,  79),
  s("c_login",   79,  87),
  s("c_rewards", 87,  97),
  s("c_expanded",97, 107),
  s("close_line",107, 116),
  s("cta",       116, 127),
  s("close",     127, 131),
];

const C = COLORS;

const SLIDES: Record<string, StoryLine[]> = {
  hook: [
    { text: `One platform.`, style: "headline", fontSize: 52, delayIndex: 0 },
    { text: `<span style="color:${C.green}">Three user types.</span>`, style: "headline", fontSize: 52, delayIndex: 1 },
    { text: `<em>For every local brand in the city.</em>`, style: "subhead", fontSize: 20, delayIndex: 2 },
  ],
  bridge_m: [
    { text: `<em>Brand side.</em>`, style: "subhead", fontSize: 22, delayIndex: 0 },
    { text: `Let's log in as <span style="color:${C.indigo}">Brew & Co.</span>`, style: "headline", fontSize: 44, delayIndex: 1 },
  ],
  bridge_c: [
    { text: `<em>Customer side.</em>`, style: "subhead", fontSize: 22, delayIndex: 0 },
    { text: `Rahul. Bandra. <span style="color:${C.green}">FitZone member.</span>`, style: "headline", fontSize: 42, delayIndex: 1 },
  ],
  close_line: [
    { text: `Three layers.`, style: "headline", fontSize: 50, delayIndex: 0 },
    { text: `<span style="color:${C.green}">One ecosystem.</span>`, style: "headline", fontSize: 50, delayIndex: 1 },
    { text: `<em>Real-time. Transparent. In full control.</em>`, style: "subhead", fontSize: 19, delayIndex: 2 },
  ],
};

function renderScene(id: string): React.ReactNode {
  if (id === "open")  return <Opening />;
  if (id === "close") return <Closing />;
  if (id === "cta")   return <CTA />;

  const slide = SLIDES[id];
  if (slide) return <StorySlide lines={slide} />;

  switch (id) {
    case "sa_dash":
      return <ScreenshotSlide
        caption="Layer 1 — Super Admin"
        headline={`The operator monitors <span style="color:${C.green}">the entire ecosystem.</span>`}
        subtext="Live partnerships, brand count, WhatsApp credit health — all in one view."
        screenshotFile="final/SA-02-dashboard.png"
      />;
    case "sa_brands":
      return <ScreenshotSlide
        caption="Platform control"
        headline={`Every brand. Credits. Status. <span style="color:${C.indigo}">Ecosystem health.</span>`}
        subtext="Allocate WhatsApp credits, manage eWards integration, monitor ecosystem activity per brand."
        screenshotFile="final/SA-03-brands.png"
      />;
    case "m_find":
      return <ScreenshotSlide
        caption="Layer 2 — Brand dashboard"
        headline={`Find the right partner <span style="color:${C.green}">in your city.</span>`}
        subtext="Search by city and category. Fit scores surface the most complementary brands."
        screenshotFile="final/M-05-find-partners.png"
      />;
    case "m_create":
      return <ScreenshotSlide
        caption="Propose"
        headline={`Send a proposal. <span style="color:${C.indigo}">Set terms upfront.</span>`}
        subtext="Cap per bill. Monthly ceiling. Minimum spend. The other brand accepts or negotiates."
        screenshotFile="final/M-03b-create-modal.png"
      />;
    case "m_live":
      return <ScreenshotSlide
        caption="Go live"
        headline={`Live. QR codes issued. <span style="color:${C.green}">Every outlet activated.</span>`}
        subtext="Cashiers can issue claim tokens immediately. Each side controls their own settings independently."
        screenshotFile="final/M-04-partnership-detail.png"
      />;
    case "m_results":
      return <ScreenshotSlide
        caption="Results"
        headline={`New customers. Revenue. <span style="color:${C.green}">ROI per partner.</span>`}
        subtext="Analytics dashboard tracks every attribution — new, existing, reactivated — and net value."
        screenshotFile="final/M-02-dashboard.png"
      />;
    case "c_login":
      return <ScreenshotSlide
        caption="Layer 3 — Customer"
        headline={`Taps a link. Phone number. <span style="color:${C.green}">That's it.</span>`}
        subtext="No app download. No registration. Just a phone number and a one-time OTP."
        screenshotFile="final/C-01-login.png"
      />;
    case "c_rewards":
      return <ScreenshotSlide
        caption="Their rewards"
        headline={`Sees <span style="color:${C.indigo}">₹143 in points</span> across 2 brands.`}
        subtext="All loyalty balances from every partner brand — in a single view."
        screenshotFile="final/C-03-rewards.png"
      />;
    case "c_expanded":
      return <ScreenshotSlide
        caption="Full transparency"
        headline={`Every outlet. Every cap. <span style="color:${C.green}">No guessing.</span>`}
        subtext="Which store to visit, the max benefit per bill, minimum spend — all shown before they walk in."
        screenshotFile="final/C-04-rewards-expanded.png"
      />;
    default:
      return <AbsoluteFill style={{ backgroundColor: C.bg }} />;
  }
}

const Music: React.FC = () => {
  const frame = useCurrentFrame();
  const vol = interpolate(frame, [0, 60], [0, 1], { extrapolateRight: "clamp" })
    * interpolate(frame, [VIDEO_A_FRAMES - 90, VIDEO_A_FRAMES], [1, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp" })
    * 0.35;
  return <Audio src={staticFile("audio/ambient-pad.mp3")} volume={vol} />;
};

export const VideoA: React.FC = () => (
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

/**
 * Slide configs v13 — Retailer-first.
 *
 * Only 4 text-only slides:
 *   - demand (business truth opener)
 *   - gap (the missing piece)
 *   - both (both brands win — post-product, short)
 *   - trust (nothing merges — compressed)
 *
 * Rules:
 *   - "brand" not "merchant"
 *   - No QR/scanning language (not in v1 flow)
 *   - No unverified numbers
 *   - indigo = product features, green = money/positive
 *   - eWards mentioned ONCE
 */
import { StoryLine } from "../types";
import { COLORS } from "../constants";

export interface SlideEntry {
  id: string;
  type: "story";
  lines: StoryLine[];
  background?: string;
}

const C = COLORS;

export const STORY_SLIDES: SlideEntry[] = [

  // ═══ ACT 1: Business truth ═══

  { id: "demand", type: "story", lines: [
    { text: `Your next customers are<br/><span style="color:${C.green}">already nearby.</span>`, style: "headline", fontSize: 48, delayIndex: 0 },
    { text: `<em>The gym next door. The cafe around the corner.<br/>Their customers spend money in your area — every week.</em>`, style: "subhead", fontSize: 18, delayIndex: 1 },
  ]},

  { id: "gap", type: "story", lines: [
    { text: `But none of that demand<br/>is reaching <span style="color:${C.indigo}">your store.</span>`, style: "headline", fontSize: 46, delayIndex: 0 },
    { text: `<em>No structured way for nearby businesses to send customers to each other.</em>`, style: "subhead", fontSize: 17, delayIndex: 1 },
  ]},

  // ═══ ACT 3: Trust ═══

  { id: "both", type: "story", lines: [
    { text: `Both brands <span style="color:${C.green}">win.</span>`, style: "headline", fontSize: 48, delayIndex: 0 },
    { text: `Your brand gets better local customers.`, style: "small", fontSize: 20, delayIndex: 1 },
    { text: `Partner brands get measurable walk-ins.`, style: "small", fontSize: 20, delayIndex: 2 },
  ]},

  { id: "trust", type: "story", lines: [
    { text: `Nothing merges. <span style="color:${C.indigo}">Full control.</span>`, style: "headline", fontSize: 44, delayIndex: 0 },
    { text: `<em>Your customers stay yours. Your data stays yours.</em>`, style: "small", fontSize: 17, delayIndex: 1 },
    { text: `<em>No contracts. No lock-in. Pause anytime.</em>`, style: "small", fontSize: 17, delayIndex: 2 },
    { text: `Works within your existing POS.`, style: "small", fontSize: 16, color: C.indigo, delayIndex: 3 },
  ]},

  // ═══ ACT 4: Close ═══

  { id: "built", type: "story", lines: [
    { text: `<em>Built by the team at</em>`, style: "subhead", fontSize: 22, delayIndex: 0 },
    { text: `<span style="color:${C.indigo}">eWards.</span>`, style: "headline", fontSize: 60, delayIndex: 1 },
    { text: `<em>Designed for real retail workflows.</em>`, style: "subhead", fontSize: 18, color: "rgba(255,255,255,0.35)", delayIndex: 2 },
  ]},
];

/**
 * Hyperlocal Video v13 — Retailer-first.
 * 13 slides, ~90 seconds.
 * Structure: Business truth → Product flow → Proof → Trust → CTA
 * Retailer should think "this can make me money" not "interesting product"
 */

export const FPS = 30;
export const WIDTH = 1280;
export const HEIGHT = 720;

export interface SceneDef {
  id: string;
  startSec: number;
  endSec: number;
  durationFrames: number;
}

function s(id: string, start: number, end: number): SceneDef {
  return { id, startSec: start, endSec: end, durationFrames: Math.round((end - start) * FPS) };
}

export const SCENES: SceneDef[] = [
  // ACT 1: Business truth (0–15s)
  s('open', 0, 3),
  s('demand', 3, 9),       // "Your next customers are already nearby"
  s('gap', 9, 15),         // "But none of that demand is reaching your store"

  // ACT 2: Product flow (15–55s)
  s('find', 15, 23),       // Step 1: Find partners
  s('control', 23, 31),    // Step 2: Define offer + limits
  s('cust', 31, 39),       // Step 3: Customer walks in
  s('results', 39, 47),    // Step 4: See what happened
  s('reach', 47, 55),      // Go further: WhatsApp campaigns

  // ACT 3: Trust (55–70s)
  s('both', 55, 62),       // Both brands win
  s('trust', 62, 70),      // Nothing merges. Full control.

  // ACT 4: Close (70–90s)
  s('built', 70, 76),      // Built by eWards
  s('cta', 76, 86),        // CTA
  s('close', 86, 90),      // End
];

export const TOTAL_DURATION_SEC = 90;
export const TOTAL_FRAMES = TOTAL_DURATION_SEC * FPS;

export const COLORS = {
  bg: '#0a0a0f',
  indigo: '#818cf8',
  green: '#34d399',
  amber: '#fbbf24',
  red: '#f87171',
  white: '#ffffff',
  dimText: 'rgba(255,255,255,0.45)',
  brand: '#4F46E5',
};

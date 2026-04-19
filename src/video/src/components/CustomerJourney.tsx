import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, Easing } from "remotion";
import { FPS } from "../constants";

const popEase = Easing.bezier(0.22, 1, 0.36, 1);
function pop(frame: number, delay: number) {
  return {
    opacity: interpolate(frame, [delay, delay + 15], [0, 1], { extrapolateRight: "clamp", extrapolateLeft: "clamp" }),
    transform: `translateY(${interpolate(frame, [delay, delay + 15], [30, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp", easing: popEase })}px) scale(${interpolate(frame, [delay, delay + 15], [0.5, 1], { extrapolateRight: "clamp", extrapolateLeft: "clamp", easing: popEase })})`,
  };
}

/** 4 phases: 0=scan(0-4s), 1=token(4-8s), 2=walk(8-12s), 3=redeem(12-16s) */
function getPhase(frame: number): number {
  const sec = frame / FPS;
  if (sec < 4) return 0;
  if (sec < 8) return 1;
  if (sec < 12) return 2;
  return 3;
}

const STEPS = [
  { label: "Scan QR", title: "Customer scans QR at source store", sub: "Static QR at the counter — enters phone number" },
  { label: "Claim Token", title: "Claim token generated instantly", sub: "Delivered on screen + via WhatsApp" },
  { label: "Visit Partner", title: "Customer visits the partner store", sub: "Shows the claim token to the cashier" },
  { label: "Redeem", title: "Cashier validates and applies benefit", sub: "Tracked for both merchants. New customer verified." },
];

const ShopIcon: React.FC<{ emoji: string; name: string; bg: string; style?: React.CSSProperties }> = ({ emoji, name, bg, style: s }) => (
  <div style={{ position: "absolute" as const, ...s }}>
    <div style={{ width: 140, height: 170, borderRadius: 16, background: bg, display: "flex", flexDirection: "column" as const, alignItems: "center", justifyContent: "center", border: "1px solid rgba(255,255,255,0.1)" }}>
      <div style={{ fontSize: 48 }}>{emoji}</div>
      <div style={{ fontSize: 13, fontWeight: 700, color: "white", marginTop: 8 }}>{name}</div>
    </div>
  </div>
);

export const CustomerJourney: React.FC = () => {
  const frame = useCurrentFrame();
  const phase = getPhase(frame);

  // Dot position along path (phase 2)
  const walkProgress = phase >= 2
    ? interpolate(frame, [8 * FPS, 10 * FPS], [0, 1], { extrapolateRight: "clamp", extrapolateLeft: "clamp" })
    : 0;
  const dotX = 240 + (1040 - 240) * walkProgress;
  const dotY = 480 + Math.sin(walkProgress * Math.PI) * -130;

  // Path draw
  const pathOffset = phase >= 2
    ? interpolate(frame, [8 * FPS, 10 * FPS], [800, 0], { extrapolateRight: "clamp", extrapolateLeft: "clamp" })
    : 800;

  // Confetti (phase 3)
  const confetti = phase === 3 ? Array.from({ length: 40 }).map((_, i) => {
    const startF = 12 * FPS;
    const elapsed = Math.max(0, frame - startF - i * 0.5);
    const x = 30 + ((i * 37 + 13) % 40);
    const y = 10 + ((i * 23 + 7) % 20) + elapsed * 4;
    const rot = elapsed * 16;
    const opacity = Math.max(0, 1 - elapsed / 45);
    const colors = ["#6366F1", "#22c55e", "#f59e0b", "#f472b6", "#60a5fa"];
    return { x, y, rot, opacity, color: colors[i % 5], size: 4 + (i % 3) * 2 };
  }) : [];

  return (
    <AbsoluteFill style={{
      background: "linear-gradient(180deg, #0c0c1d 0%, #111128 60%, #1a1a2e 100%)",
      fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
    }}>
      {/* Step pills */}
      <div style={{ position: "absolute" as const, top: 30, left: 0, right: 0, textAlign: "center" as const, zIndex: 10, ...pop(frame, 0) }}>
        <div style={{ display: "inline-flex", background: "rgba(255,255,255,0.06)", borderRadius: 24, padding: 4 }}>
          {STEPS.map((s, i) => (
            <div key={i} style={{
              padding: "8px 18px", borderRadius: 20, fontSize: 12, fontWeight: 600,
              color: i < phase ? "#34d399" : i === phase ? "white" : "rgba(255,255,255,0.3)",
              background: i === phase ? "rgba(99,102,241,0.3)" : "transparent",
              transition: "all 0.4s",
            }}>{s.label}</div>
          ))}
        </div>
      </div>

      {/* Ground */}
      <div style={{ position: "absolute" as const, bottom: 0, left: 0, right: 0, height: 80, background: "linear-gradient(180deg, #1a1a2e, #16162b)" }}>
        <div style={{ position: "absolute" as const, top: 0, left: 0, right: 0, height: 3, background: "rgba(255,255,255,0.08)" }} />
      </div>

      {/* Shops */}
      <ShopIcon emoji="☕" name="Brew & Co" bg="linear-gradient(180deg, #312e81, #1e1b4b)" style={{ bottom: 100, left: 100 }} />
      <ShopIcon emoji="🏋️" name="FitZone" bg="linear-gradient(180deg, #065f46, #064e3b)" style={{ bottom: 100, right: 100 }} />

      {/* SVG path + dot */}
      <svg viewBox="0 0 1280 720" style={{ position: "absolute" as const, inset: 0, width: "100%", height: "100%", pointerEvents: "none" as const, zIndex: 5 }}>
        <defs>
          <linearGradient id="pg" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stopColor="#6366F1" /><stop offset="100%" stopColor="#22c55e" /></linearGradient>
          <filter id="dg"><feGaussianBlur stdDeviation="4" result="b" /><feMerge><feMergeNode in="b" /><feMergeNode in="SourceGraphic" /></feMerge></filter>
        </defs>
        <path d="M240,480 C400,350 880,350 1040,480" fill="none" stroke="url(#pg)" strokeWidth={3} strokeLinecap="round" strokeDasharray={800} strokeDashoffset={pathOffset} />
        {phase >= 2 && <circle cx={dotX} cy={dotY} r={8} fill="#818cf8" filter="url(#dg)" />}
      </svg>

      {/* Phone mockup (phase 0) */}
      {phase === 0 && (
        <div style={{ position: "absolute" as const, top: 150, left: 200, zIndex: 10, ...pop(frame, 6), width: 120, background: "#1f2937", borderRadius: 18, padding: 6, boxShadow: "0 20px 50px rgba(0,0,0,0.5)" }}>
          <div style={{ background: "white", borderRadius: 14, padding: 14, textAlign: "center" as const }}>
            <div style={{ fontSize: 9, fontWeight: 700, color: "#6366F1", letterSpacing: "0.1em", textTransform: "uppercase" as const, marginBottom: 6 }}>Scan · Enter phone</div>
            <svg width="70" height="70" viewBox="0 0 60 60"><rect x="5" y="5" width="20" height="20" rx="3" fill="#1f2937" /><rect x="8" y="8" width="14" height="14" rx="2" fill="white" /><rect x="11" y="11" width="8" height="8" rx="1" fill="#6366F1" /><rect x="35" y="5" width="20" height="20" rx="3" fill="#1f2937" /><rect x="38" y="8" width="14" height="14" rx="2" fill="white" /><rect x="41" y="11" width="8" height="8" rx="1" fill="#6366F1" /><rect x="5" y="35" width="20" height="20" rx="3" fill="#1f2937" /><rect x="8" y="38" width="14" height="14" rx="2" fill="white" /><rect x="11" y="41" width="8" height="8" rx="1" fill="#6366F1" /><rect x="35" y="35" width="6" height="6" fill="#1f2937" /><rect x="44" y="44" width="6" height="6" fill="#1f2937" /></svg>
          </div>
        </div>
      )}

      {/* Token (phase 1) */}
      {phase === 1 && (
        <div style={{ position: "absolute" as const, top: 160, left: "50%", marginLeft: -70, zIndex: 10, background: "white", borderRadius: 14, padding: "12px 20px", boxShadow: "0 8px 32px rgba(0,0,0,0.3)", textAlign: "center" as const, ...pop(frame - 4 * FPS, 0) }}>
          <div style={{ fontSize: 10, fontWeight: 700, letterSpacing: "0.1em", color: "#6366F1", textTransform: "uppercase" as const, marginBottom: 2 }}>Claim token</div>
          <div style={{ fontSize: 22, fontWeight: 900, letterSpacing: 3, color: "#1f2937", fontFamily: "'SF Mono', 'Fira Code', monospace" }}>HLP4X9K2</div>
          <div style={{ fontSize: 9, color: "#9ca3af", marginTop: 4 }}>Valid 48 hours · Sent via WhatsApp</div>
        </div>
      )}

      {/* Discount badge (phase 3) */}
      {phase === 3 && (
        <div style={{ position: "absolute" as const, top: 140, right: 180, zIndex: 15, ...pop(frame - 12 * FPS, 0) }}>
          <div style={{ width: 100, height: 100, borderRadius: "50%", background: "linear-gradient(135deg, #16a34a, #22c55e)", display: "flex", alignItems: "center", justifyContent: "center", flexDirection: "column" as const, boxShadow: "0 8px 32px rgba(22,163,74,0.4)", color: "white" }}>
            <div style={{ fontSize: 28, fontWeight: 900 }}>₹50</div>
            <div style={{ fontSize: 10, fontWeight: 700, letterSpacing: "0.05em", opacity: 0.9 }}>OFF</div>
          </div>
          <div style={{ textAlign: "center" as const, marginTop: 8, fontSize: 12, fontWeight: 700, color: "#34d399" }}>New walk-in verified</div>
        </div>
      )}

      {/* Confetti (phase 3) */}
      {confetti.map((c, i) => (
        <div key={i} style={{ position: "absolute" as const, left: `${c.x}%`, top: `${c.y}%`, width: c.size, height: c.size, borderRadius: 2, background: c.color, transform: `rotate(${c.rot}deg)`, opacity: c.opacity, pointerEvents: "none" as const }} />
      ))}

      {/* Bottom text */}
      <div style={{ position: "absolute" as const, bottom: 100, left: 0, right: 0, textAlign: "center" as const, zIndex: 10 }}>
        <div style={{ fontSize: 32, fontWeight: 800, color: "white", letterSpacing: -1, marginBottom: 6 }}>{STEPS[phase].title}</div>
        <div style={{ fontSize: 16, color: "rgba(255,255,255,0.4)" }}>{STEPS[phase].sub}</div>
      </div>
    </AbsoluteFill>
  );
};

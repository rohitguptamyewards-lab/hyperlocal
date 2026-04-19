import React from "react";
import { Composition } from "remotion";
import { Video } from "./Video";
import { VideoA, VIDEO_A_FRAMES } from "./VideoA";
import { VideoB, VIDEO_B_FRAMES } from "./VideoB";
import { VideoC, VIDEO_C_FRAMES } from "./VideoC";
import { TOTAL_FRAMES, FPS, WIDTH, HEIGHT } from "./constants";

export const Root: React.FC = () => (
  <>
    {/* v13 — Current retailer-first version */}
    <Composition
      id="HyperlocalVideo"
      component={Video}
      durationInFrames={TOTAL_FRAMES}
      fps={FPS}
      width={WIDTH}
      height={HEIGHT}
    />
    {/* Version A — Story-led (Brew & Co case study) */}
    <Composition
      id="VersionA"
      component={VideoA}
      durationInFrames={VIDEO_A_FRAMES}
      fps={FPS}
      width={WIDTH}
      height={HEIGHT}
    />
    {/* Version B — Provocative hook (gym stat) */}
    <Composition
      id="VersionB"
      component={VideoB}
      durationInFrames={VIDEO_B_FRAMES}
      fps={FPS}
      width={WIDTH}
      height={HEIGHT}
    />
    {/* Version C — v13 + emotion layer (Brew & Co specificity) */}
    <Composition
      id="VersionC"
      component={VideoC}
      durationInFrames={VIDEO_C_FRAMES}
      fps={FPS}
      width={WIDTH}
      height={HEIGHT}
    />
  </>
);

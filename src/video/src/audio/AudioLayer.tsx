import React from "react";
import { Audio, Sequence, staticFile } from "remotion";
import { FPS } from "../constants";

/**
 * Voiceover mapping: slide ID → MP3 filename + start frame (within full composition).
 * Start frames are 15 frames (0.5s) after slide start to let text appear first.
 *
 * Silent slides (no VO): st1, st7, st9, st11
 * Gap/transition scenes: gap1, gap2
 */
interface VOEntry {
  file: string;
  /** Absolute start frame in the full composition */
  startFrame: number;
}

const VO_ENTRIES: VOEntry[] = [
  // ACT 1
  { file: "slide-02.mp3", startFrame: Math.round(5 * FPS) + 15 },     // st2 starts at 5s
  { file: "slide-03.mp3", startFrame: Math.round(11 * FPS) + 15 },    // st3 starts at 11s
  { file: "slide-04.mp3", startFrame: Math.round(18 * FPS) + 15 },    // st4 starts at 18s

  // ACT 2
  { file: "slide-05.mp3", startFrame: Math.round(24 * FPS) + 15 },    // st5 starts at 24s
  { file: "slide-06.mp3", startFrame: Math.round(31 * FPS) + 15 },    // st6 starts at 31s
  // st7 = silent
  { file: "slide-08.mp3", startFrame: Math.round(41 * FPS) + 15 },    // st8 starts at 41s

  // ACT 3
  // st9 = silent
  { file: "slide-10.mp3", startFrame: Math.round(53 * FPS) + 15 },    // st10 starts at 53s
  // st11 = silent
  { file: "slide-12.mp3", startFrame: Math.round(62 * FPS) + 15 },    // st12 starts at 62s

  // ACT 4 (tone shifts: slightly crisper)
  { file: "slide-13.mp3", startFrame: Math.round(69 * FPS) + 15 },    // st13 starts at 69s
  { file: "slide-14.mp3", startFrame: Math.round(74 * FPS) + 15 },    // st14 starts at 74s
  { file: "slide-15.mp3", startFrame: Math.round(79 * FPS) + 15 },    // st15 starts at 79s
  { file: "slide-16.mp3", startFrame: Math.round(84 * FPS) + 15 },    // st16 starts at 84s
  { file: "slide-17.mp3", startFrame: Math.round(90 * FPS) + 15 },    // st17 starts at 90s

  // ACT 5 (inhibitions)
  { file: "slide-18.mp3", startFrame: Math.round(95 * FPS) + 15 },    // st18 starts at 95s
  { file: "slide-19.mp3", startFrame: Math.round(101 * FPS) + 15 },   // st19 starts at 101s
  { file: "slide-20.mp3", startFrame: Math.round(107 * FPS) + 15 },   // st20 starts at 107s
  { file: "slide-21.mp3", startFrame: Math.round(113 * FPS) + 15 },   // st21 starts at 113s
  { file: "slide-22.mp3", startFrame: Math.round(119 * FPS) + 15 },   // st22 starts at 119s

  // ACT 6 (transition)
  { file: "slide-23.mp3", startFrame: Math.round(125 * FPS) + 15 },   // st23 starts at 125s
  { file: "slide-24.mp3", startFrame: Math.round(130 * FPS) + 15 },   // st24 starts at 130s
];

/**
 * AudioLayer — composes all voiceover tracks.
 * Each VO clip plays at its slide's start time + 0.5s delay.
 */
export const AudioLayer: React.FC = () => {
  return (
    <>
      {VO_ENTRIES.map((entry) => (
        <Sequence from={entry.startFrame} key={entry.file}>
          <Audio
            src={staticFile(`audio/voiceover/${entry.file}`)}
            volume={1.0}
          />
        </Sequence>
      ))}
    </>
  );
};

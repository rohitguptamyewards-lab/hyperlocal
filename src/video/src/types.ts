/**
 * Shared TypeScript interfaces for the Hyperlocal video.
 */

/** A single line of text in a story slide */
export interface StoryLine {
  text: string;
  style: 'headline' | 'subhead' | 'small' | 'dim';
  color?: string;
  fontSize?: number;
  delayIndex: number; // 0-5, maps to POP_DELAYS.d0-d5
}

/** Config for a story slide */
export interface SlideConfig {
  id: string;
  lines: StoryLine[];
  background?: string;
  durationFrames: number;
}

/** Config for an inhibition slide (Q→A format) */
export interface InhibitionConfig {
  id: string;
  question: string;
  answerLines: string[];
  answerColor?: string;
  durationFrames: number;
}

"""
Final music track for Hyperlocal video.
NOT a loop — a single progressive track that builds.

Structure (matches video acts):
  0-47s   ACT 1-2 (Problem): Sparse, soft piano chords. Reflective.
  48-68s  ACT 3 (Solution): Melody enters. Hope builds.
  69-95s  ACT 4 (System): Beat drops. Energy. Confidence.
  95-134s ACT 5-6 (Inhibitions+Transition): Full arrangement. Driving.
  134-180s PRODUCT DEMO: Peak energy. Everything playing.
  180-189s FINALE: Big crescendo → satisfying resolve chord.
"""

import struct
import wave
import math
import random
import subprocess
import os

OUT_DIR = os.path.dirname(os.path.abspath(__file__)) + "/public/audio"
WAV_PATH = "/tmp/hyperlocal_music.wav"
MP3_PATH = OUT_DIR + "/ambient-pad.mp3"

SR = 44100
BPM = 120
BEAT = 60.0 / BPM  # 0.5s
BAR = BEAT * 4  # 2s
DURATION = 195

# Notes
N = {
    'C2':65.41,'E2':82.41,'G2':98.00,
    'C3':130.81,'D3':146.83,'E3':164.81,'F3':174.61,'G3':196.00,'A3':220.00,'B3':246.94,
    'C4':261.63,'D4':293.66,'E4':329.63,'F4':349.23,'G4':392.00,'A4':440.00,'B4':493.88,
    'C5':523.25,'D5':587.33,'E5':659.25,'F5':698.46,'G5':783.99,'A5':880.00,
    'C6':1046.50,'E6':1318.51,
}

# Progressions
PROG = [
    [['C3','E3','G3'], ['C4','E4','G4']],   # C major
    [['F3','A3','C4'], ['F4','A4','C5']],   # F major
    [['A3','C4','E4'], ['A4','C5','E5']],   # A minor
    [['G3','B3','D4'], ['G4','B4','D5']],   # G major
]

# Melodies for different sections
MELODY_HOPE = ['E5','D5','C5','E5', 'D5','C5','B4','C5', 'E5','G5','E5','D5', 'C5','D5','E5','C5']
MELODY_ENERGY = ['C5','E5','G5','A5', 'G5','E5','C5','D5', 'E5','G5','A5','G5', 'E5','D5','C5','E5']
MELODY_PEAK = ['G5','A5','G5','E5', 'G5','A5','C6','A5', 'G5','E5','D5','E5', 'G5','A5','G5','E6']

def mix(track, samples, offset):
    for i, s in enumerate(samples):
        idx = offset + i
        if 0 <= idx < len(track):
            track[idx] += s

def tone(freq, dur, vol=0.3, wave_type='sine'):
    n = int(SR * dur)
    out = []
    attack = min(int(SR * 0.01), n // 4)
    release = min(int(SR * 0.05), n // 3)
    for i in range(n):
        t = i / SR
        if wave_type == 'sine':
            val = math.sin(2 * math.pi * freq * t)
        elif wave_type == 'triangle':
            phase = (t * freq) % 1.0
            val = 4 * abs(phase - 0.5) - 1
        elif wave_type == 'saw':
            val = 2 * ((t * freq) % 1.0) - 1
        elif wave_type == 'square':
            val = 1 if ((t * freq) % 1.0) < 0.5 else -1
        else:
            val = math.sin(2 * math.pi * freq * t)

        # Envelope
        env = 1.0
        if i < attack: env = i / attack
        if i > n - release: env = (n - i) / release
        out.append(val * vol * env)
    return out

def pad(freq, dur, vol=0.15):
    """Warm pad: layered triangle + detuned sine"""
    s1 = tone(freq, dur, vol * 0.7, 'triangle')
    s2 = tone(freq * 1.003, dur, vol * 0.3, 'sine')  # slight detune
    return [a + b for a, b in zip(s1, s2)]

def pluck(freq, dur, vol=0.2):
    """Plucky sound: quick attack, fast decay"""
    n = int(SR * dur)
    out = []
    for i in range(n):
        t = i / SR
        val = math.sin(2 * math.pi * freq * t)
        val += 0.3 * math.sin(2 * math.pi * freq * 2 * t)  # harmonic
        env = math.exp(-t * 8) * vol  # quick decay
        if i < 30: env *= i / 30
        out.append(val * env)
    return out

def kick(vol=0.8):
    n = int(SR * 0.15)
    out = []
    for i in range(n):
        t = i / SR
        freq = 150 * math.exp(-t * 25)
        env = math.exp(-t * 12) * vol
        out.append(math.sin(2 * math.pi * freq * t) * env)
    return out

def snare(vol=0.4):
    n = int(SR * 0.1)
    random.seed(None)
    return [(random.uniform(-1, 1) * vol * math.exp(-i / n * 5)) for i in range(n)]

def hihat(vol=0.15, dur=0.03):
    n = int(SR * dur)
    random.seed(None)
    return [(random.uniform(-1, 1) * vol * (1 - i/n)) for i in range(n)]

def riser(start_freq, end_freq, dur, vol=0.15):
    """Rising sweep for transitions — wow effect"""
    n = int(SR * dur)
    out = []
    for i in range(n):
        t = i / n
        freq = start_freq + (end_freq - start_freq) * (t ** 2)  # exponential rise
        val = math.sin(2 * math.pi * freq * (i / SR))
        env = t * vol  # gets louder as it rises
        out.append(val * env)
    return out

def crash(vol=0.5):
    """Crash cymbal for big moments"""
    n = int(SR * 1.5)
    random.seed(None)
    return [(random.uniform(-1, 1) * vol * math.exp(-i / n * 3)) for i in range(n)]

def generate():
    total = SR * DURATION
    track = [0.0] * total
    random.seed(42)

    print("=== Generating progressive music track ===")

    # ── SECTION 1: Calm reflective (0-47s) ──
    # Soft piano-like chords only. No beat. Sparse.
    print("Section 1: Calm (0-47s)...")
    for bar in range(int(47 / BAR)):
        t = bar * BAR
        chord_idx = bar % 4
        offset = int(t * SR)
        # Soft chord pads
        for note_name in PROG[chord_idx][0]:
            freq = N[note_name]
            mix(track, pad(freq, BAR * 0.9, 0.08), offset)
        # Very quiet high note on beat 1
        if bar % 2 == 0:
            freq = N[PROG[chord_idx][1][0]]
            mix(track, tone(freq, BEAT, 0.04, 'sine'), offset)

    # ── SECTION 2: Hope builds (48-68s) ──
    # Melody enters over chords. Still no drums.
    print("Section 2: Hope (48-68s)...")
    for bar in range(int(20 / BAR)):
        t = 48 + bar * BAR
        chord_idx = bar % 4
        offset = int(t * SR)
        # Warmer chords
        for note_name in PROG[chord_idx][0]:
            mix(track, pad(N[note_name], BAR * 0.9, 0.12), offset)
        # Melody
        for beat in range(4):
            mel_idx = (bar * 4 + beat) % len(MELODY_HOPE)
            note = MELODY_HOPE[mel_idx]
            mel_offset = offset + int(beat * BEAT * SR)
            mix(track, pluck(N[note], BEAT * 0.8, 0.15), mel_offset)

    # ── Rising transition at 66-68s ──
    print("  Riser at 66s...")
    mix(track, riser(200, 2000, 3, 0.12), int(66 * SR))

    # ── SECTION 3: Beat drops (69-95s) ──
    # Drums enter. Energy. Confidence.
    print("Section 3: Energy (69-95s)...")
    for bar in range(int(26 / BAR)):
        t = 69 + bar * BAR
        chord_idx = bar % 4
        offset = int(t * SR)

        # Chords (fuller)
        for note_name in PROG[chord_idx][0] + PROG[chord_idx][1]:
            mix(track, pad(N[note_name], BAR * 0.85, 0.08), offset)

        # Bass
        bass_note = PROG[chord_idx][0][0]
        mix(track, tone(N[bass_note] / 2, BAR * 0.9, 0.25, 'sine'), offset)

        for beat in range(4):
            b_offset = offset + int(beat * BEAT * SR)
            # Kick on 1 and 3
            if beat in [0, 2]:
                mix(track, kick(0.6), b_offset)
            # Snare on 2 and 4
            if beat in [1, 3]:
                mix(track, snare(0.25), b_offset)
            # Hi-hat every beat
            mix(track, hihat(0.1), b_offset)
            # Off-beat hi-hat
            mix(track, hihat(0.06, 0.02), b_offset + int(BEAT * 0.5 * SR))

        # Melody (more energetic)
        for beat in range(4):
            mel_idx = (bar * 4 + beat) % len(MELODY_ENERGY)
            mel_offset = offset + int(beat * BEAT * SR)
            mix(track, pluck(N[MELODY_ENERGY[mel_idx]], BEAT * 0.6, 0.18), mel_offset)

    # ── SECTION 4: Full arrangement (95-134s) ──
    print("Section 4: Driving (95-134s)...")
    for bar in range(int(39 / BAR)):
        t = 95 + bar * BAR
        chord_idx = bar % 4
        offset = int(t * SR)

        # Full chords
        for note_name in PROG[chord_idx][0] + PROG[chord_idx][1]:
            mix(track, pad(N[note_name], BAR * 0.85, 0.1), offset)

        # Deep bass
        bass_note = PROG[chord_idx][0][0]
        mix(track, tone(N[bass_note] / 2, BAR * 0.9, 0.3, 'sine'), offset)

        for beat in range(4):
            b_offset = offset + int(beat * BEAT * SR)
            if beat in [0, 2]: mix(track, kick(0.7), b_offset)
            if beat in [1, 3]: mix(track, snare(0.3), b_offset)
            mix(track, hihat(0.12), b_offset)
            mix(track, hihat(0.08, 0.02), b_offset + int(BEAT * 0.5 * SR))
            # 16th note hi-hats for drive
            mix(track, hihat(0.04, 0.015), b_offset + int(BEAT * 0.25 * SR))
            mix(track, hihat(0.04, 0.015), b_offset + int(BEAT * 0.75 * SR))

        # Melody
        for beat in range(4):
            mel_idx = (bar * 4 + beat) % len(MELODY_ENERGY)
            mel_offset = offset + int(beat * BEAT * SR)
            mix(track, pluck(N[MELODY_ENERGY[mel_idx]], BEAT * 0.5, 0.2), mel_offset)

    # ── Riser into product demo (132-134s) ──
    print("  Riser at 132s...")
    mix(track, riser(300, 4000, 2.5, 0.15), int(132 * SR))
    mix(track, crash(0.3), int(134 * SR))

    # ── SECTION 5: Peak energy — product demo (134-183s) ──
    print("Section 5: Peak (134-183s)...")
    for bar in range(int(49 / BAR)):
        t = 134 + bar * BAR
        chord_idx = bar % 4
        offset = int(t * SR)

        # Massive chords
        for note_name in PROG[chord_idx][0] + PROG[chord_idx][1]:
            mix(track, pad(N[note_name], BAR * 0.85, 0.12), offset)

        # Big bass
        bass_note = PROG[chord_idx][0][0]
        mix(track, tone(N[bass_note] / 2, BAR * 0.9, 0.35, 'sine'), offset)
        mix(track, tone(N[bass_note], BEAT * 0.3, 0.15, 'saw'), offset)  # bass punch

        for beat in range(4):
            b_offset = offset + int(beat * BEAT * SR)
            if beat in [0, 2]: mix(track, kick(0.8), b_offset)
            if beat in [1, 3]: mix(track, snare(0.35), b_offset)
            mix(track, hihat(0.14), b_offset)
            mix(track, hihat(0.09, 0.02), b_offset + int(BEAT * 0.5 * SR))
            mix(track, hihat(0.05, 0.015), b_offset + int(BEAT * 0.25 * SR))
            mix(track, hihat(0.05, 0.015), b_offset + int(BEAT * 0.75 * SR))

        # Peak melody
        for beat in range(4):
            mel_idx = (bar * 4 + beat) % len(MELODY_PEAK)
            mel_offset = offset + int(beat * BEAT * SR)
            mix(track, pluck(N[MELODY_PEAK[mel_idx]], BEAT * 0.5, 0.22), mel_offset)

        # Add crash on first beat of every 4th bar for wow
        if bar % 4 == 0:
            mix(track, crash(0.2), offset)

    # ── FINALE: Big crescendo + resolve (183-193s) ──
    print("Section 6: Finale crescendo...")
    # Rising sweep
    mix(track, riser(200, 6000, 4, 0.2), int(183 * SR))

    # Final big chord at 187s
    final_offset = int(187 * SR)
    mix(track, crash(0.5), final_offset)
    # Big C major chord — all octaves
    for note in ['C3','E3','G3','C4','E4','G4','C5','E5','G5']:
        mix(track, pad(N[note], 6, 0.15), final_offset)
    mix(track, tone(N['C2'], 6, 0.3, 'sine'), final_offset)  # deep bass

    # ── Global processing ──
    print("Processing...")
    # Fade in
    fade_in = int(3 * SR)
    for i in range(fade_in):
        track[i] *= i / fade_in
    # Fade out (last 3s)
    fade_out = int(3 * SR)
    for i in range(fade_out):
        track[total - 1 - i] *= i / fade_out

    # Normalize
    peak = max(abs(s) for s in track)
    if peak > 0:
        track = [s * (0.95 / peak) for s in track]

    # Write WAV
    print("Writing WAV...")
    with wave.open(WAV_PATH, 'w') as wf:
        wf.setnchannels(1)
        wf.setsampwidth(2)
        wf.setframerate(SR)
        for s in track:
            clamped = max(-1.0, min(1.0, s))
            wf.writeframes(struct.pack('<h', int(clamped * 32767)))

    # Convert to MP3 — LOUD
    print("Converting to loud MP3...")
    subprocess.run([
        'ffmpeg', '-y', '-i', WAV_PATH,
        '-af', 'volume=5,alimiter=limit=0.95,loudnorm=I=-8:TP=-1:LRA=5',
        '-codec:a', 'libmp3lame', '-b:a', '192k',
        MP3_PATH
    ], capture_output=True)

    os.remove(WAV_PATH)

    result = subprocess.run(
        ['ffmpeg', '-i', MP3_PATH, '-af', 'volumedetect', '-f', 'null', '-'],
        capture_output=True, text=True
    )
    for line in result.stderr.split('\n'):
        if 'mean_volume' in line or 'max_volume' in line:
            print(line.strip())

    print(f"Done: {MP3_PATH}")

if __name__ == '__main__':
    generate()

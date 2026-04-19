"""
Generate a proper corporate/ambient background music track.
Uses MIDI -> WAV -> MP3 pipeline.
Key: C major. Tempo: 90 BPM. Duration: 200 seconds.
Style: Warm, hopeful, building — like a premium SaaS explainer.
"""

import subprocess
import os
import math
import struct
import wave
import random

OUT_DIR = os.path.dirname(os.path.abspath(__file__)) + "/public/audio"
WAV_PATH = OUT_DIR + "/music.wav"
MP3_PATH = OUT_DIR + "/ambient-pad.mp3"

SAMPLE_RATE = 44100
DURATION = 200  # seconds
BPM = 90
BEAT = 60.0 / BPM  # 0.667s per beat

# ── Note frequencies ──
NOTE_FREQS = {
    'C3': 130.81, 'D3': 146.83, 'E3': 164.81, 'F3': 174.61, 'G3': 196.00, 'A3': 220.00, 'B3': 246.94,
    'C4': 261.63, 'D4': 293.66, 'E4': 329.63, 'F4': 349.23, 'G4': 392.00, 'A4': 440.00, 'B4': 493.88,
    'C5': 523.25, 'D5': 587.33, 'E5': 659.25, 'F5': 698.46, 'G5': 783.99,
}

# ── Chord progressions (C major, emotional/uplifting) ──
# I - V - vi - IV (pop progression)
PROG_A = [
    ['C4', 'E4', 'G4'],       # C major
    ['G3', 'B3', 'D4'],       # G major
    ['A3', 'C4', 'E4'],       # A minor
    ['F3', 'A3', 'C4'],       # F major
]
# I - IV - vi - V (hopeful)
PROG_B = [
    ['C4', 'E4', 'G4'],
    ['F3', 'A3', 'C4'],
    ['A3', 'C4', 'E4'],
    ['G3', 'B3', 'D4'],
]

# ── Melody patterns (notes over 4 bars) ──
MELODY_A = ['E5', 'D5', 'C5', 'D5', 'E5', 'E5', 'E5', 'D5', 'D5', 'D5', 'E5', 'G5', 'G5', 'E5', 'D5', 'C5']
MELODY_B = ['C5', 'D5', 'E5', 'G5', 'E5', 'D5', 'C5', 'D5', 'E5', 'C5', 'D5', 'E5', 'D5', 'C5', 'B4', 'C5']

def sine_wave(freq, duration, volume=0.3, sample_rate=SAMPLE_RATE):
    """Generate a sine wave with fade in/out."""
    n_samples = int(sample_rate * duration)
    samples = []
    fade_samples = min(int(sample_rate * 0.05), n_samples // 4)
    for i in range(n_samples):
        t = i / sample_rate
        val = math.sin(2 * math.pi * freq * t) * volume
        # Fade in/out
        if i < fade_samples:
            val *= i / fade_samples
        elif i > n_samples - fade_samples:
            val *= (n_samples - i) / fade_samples
        samples.append(val)
    return samples

def triangle_wave(freq, duration, volume=0.2, sample_rate=SAMPLE_RATE):
    """Generate a triangle wave (warmer than sine)."""
    n_samples = int(sample_rate * duration)
    samples = []
    fade_samples = min(int(sample_rate * 0.08), n_samples // 4)
    for i in range(n_samples):
        t = i / sample_rate
        phase = (t * freq) % 1.0
        val = (4 * abs(phase - 0.5) - 1) * volume
        if i < fade_samples:
            val *= i / fade_samples
        elif i > n_samples - fade_samples:
            val *= (n_samples - i) / fade_samples
        samples.append(val)
    return samples

def mix_into(target, source, offset=0):
    """Mix source samples into target at offset."""
    for i, s in enumerate(source):
        idx = offset + i
        if idx < len(target):
            target[idx] += s

def generate_track():
    total_samples = SAMPLE_RATE * DURATION
    track = [0.0] * total_samples

    random.seed(42)  # Deterministic

    # ── Bass line (low, steady) ──
    print("Generating bass...")
    bass_notes = ['C3', 'G3', 'A3', 'F3'] * 50  # repeating
    for i, note in enumerate(bass_notes):
        if i * BEAT * 4 >= DURATION:
            break
        offset = int(i * BEAT * 4 * SAMPLE_RATE)
        freq = NOTE_FREQS[note]
        # Bass: long sustained sine, quiet
        vol = 0.15
        if i * BEAT * 4 > 69:  # Act 4+: louder
            vol = 0.2
        if i * BEAT * 4 > 134:  # Product demo: fullest
            vol = 0.25
        samples = sine_wave(freq, BEAT * 3.5, vol)
        mix_into(track, samples, offset)

    # ── Chord pads (warm triangle waves) ──
    print("Generating chords...")
    prog = PROG_A
    bar_duration = BEAT * 4
    for bar in range(int(DURATION / bar_duration)):
        t = bar * bar_duration
        if t >= DURATION:
            break
        # Switch progression halfway
        if bar % 8 < 4:
            prog = PROG_A
        else:
            prog = PROG_B
        chord = prog[bar % 4]
        offset = int(t * SAMPLE_RATE)

        vol = 0.08
        if t > 47:   # Act 3+
            vol = 0.12
        if t > 69:   # Act 4+
            vol = 0.15
        if t > 134:  # Demo
            vol = 0.18

        for note_name in chord:
            freq = NOTE_FREQS[note_name]
            samples = triangle_wave(freq, bar_duration * 0.9, vol)
            mix_into(track, samples, offset)

    # ── Melody (appears from Act 3 onwards) ──
    print("Generating melody...")
    melody_start = 48  # seconds (Act 3)
    melodies = MELODY_A + MELODY_B
    note_dur = BEAT  # one beat per note
    for i, note_name in enumerate(melodies * 20):
        t = melody_start + i * note_dur
        if t >= DURATION - 5:
            break
        if t < melody_start:
            continue
        offset = int(t * SAMPLE_RATE)
        freq = NOTE_FREQS.get(note_name, 523.25)

        vol = 0.1
        if t > 90:  # Inhibitions
            vol = 0.08
        if t > 134:  # Demo
            vol = 0.15

        # Some notes are longer for variation
        dur = note_dur * (1.5 if i % 4 == 0 else 0.8)
        samples = sine_wave(freq, dur, vol)
        mix_into(track, samples, offset)

    # ── Light hi-hat / rhythm (from Act 4) ──
    print("Generating rhythm...")
    rhythm_start = 69  # Act 4
    for i in range(int((DURATION - rhythm_start) / (BEAT * 0.5))):
        t = rhythm_start + i * BEAT * 0.5
        if t >= DURATION - 5:
            break
        offset = int(t * SAMPLE_RATE)
        # Short noise burst (hi-hat-like)
        n = int(SAMPLE_RATE * 0.02)
        hat = [random.uniform(-0.04, 0.04) * (1 - j/n) for j in range(n)]
        if t > 134:
            hat = [s * 1.5 for s in hat]
        mix_into(track, hat, offset)

    # ── Global fade in/out ──
    print("Applying fades...")
    fade_in = int(4 * SAMPLE_RATE)
    fade_out = int(6 * SAMPLE_RATE)
    for i in range(fade_in):
        track[i] *= i / fade_in
    for i in range(fade_out):
        track[total_samples - 1 - i] *= i / fade_out

    # ── Clamp and normalize ──
    peak = max(abs(s) for s in track)
    if peak > 0:
        scale = 0.85 / peak  # Leave headroom
        track = [s * scale for s in track]

    # ── Write WAV ──
    print(f"Writing WAV ({DURATION}s)...")
    with wave.open(WAV_PATH, 'w') as wf:
        wf.setnchannels(1)
        wf.setsampwidth(2)
        wf.setframerate(SAMPLE_RATE)
        for s in track:
            clamped = max(-1.0, min(1.0, s))
            wf.writeframes(struct.pack('<h', int(clamped * 32767)))

    # ── Convert to MP3 ──
    print("Converting to MP3...")
    subprocess.run([
        'ffmpeg', '-y', '-i', WAV_PATH,
        '-af', 'loudnorm=I=-12:TP=-1:LRA=7',
        '-codec:a', 'libmp3lame', '-b:a', '192k',
        MP3_PATH
    ], capture_output=True)

    os.remove(WAV_PATH)

    # Verify
    result = subprocess.run(
        ['ffmpeg', '-i', MP3_PATH, '-af', 'volumedetect', '-f', 'null', '-'],
        capture_output=True, text=True
    )
    for line in result.stderr.split('\n'):
        if 'mean_volume' in line or 'max_volume' in line:
            print(line.strip())

    print(f"Done: {MP3_PATH}")

if __name__ == '__main__':
    generate_track()

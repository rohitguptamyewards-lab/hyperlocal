"""
Recreate the exact 128 BPM bouncy music from the HTML video's Web Audio engine.
Same chord progression (C-F-Am-G), same kick/clap/hat pattern, same arp melody.
Output: loud, clear, high-energy MP3.
"""

import struct
import wave
import math
import random
import subprocess
import os

OUT_DIR = os.path.dirname(os.path.abspath(__file__)) + "/public/audio"
WAV_PATH = OUT_DIR + "/bouncy.wav"
MP3_PATH = OUT_DIR + "/ambient-pad.mp3"

SR = 44100
BPM = 128
STEP = (60.0 / BPM) / 4  # 16th note = ~0.117s
STEPS = 16  # one bar
DURATION = 200  # seconds
TOTAL_BARS = int(DURATION / (STEP * STEPS))

# ── Note frequencies (from the HTML) ──
BASS_NOTES = [130.81]*4 + [174.61]*4 + [220.00]*4 + [196.00]*4
ARP_NOTES = [523.25,659.25,783.99,659.25, 698.46,880.00,1046.50,880.00, 880.00,1046.50,1318.51,1046.50, 783.99,987.77,1174.66,987.77]
CHORDS = [[261.63,329.63,392.00],[349.23,440.00,523.25],[440.00,523.25,659.25],[392.00,493.88,587.33]]

KICK_STEPS = [0,4,8,12]
CLAP_STEPS = [4,12]
HAT_STEPS = [0,2,4,6,8,10,12,14]
OHAT_STEPS = [6,14]
BASS_STEPS = [0,3,4,7,8,11,12,15]
CHORD_STEPS = [0,8]

def mix(track, samples, offset):
    for i, s in enumerate(samples):
        idx = offset + i
        if 0 <= idx < len(track):
            track[idx] += s

def sine(freq, dur, vol=0.3):
    n = int(SR * dur)
    out = []
    for i in range(n):
        t = i / SR
        env = 1.0
        if i < 200: env = i / 200
        if i > n - 200: env = (n - i) / 200
        out.append(math.sin(2 * math.pi * freq * t) * vol * env)
    return out

def saw(freq, dur, vol=0.3):
    n = int(SR * dur)
    out = []
    for i in range(n):
        t = i / SR
        phase = (t * freq) % 1.0
        val = (2 * phase - 1) * vol
        env = 1.0
        if i < 100: env = i / 100
        if i > n - 100: env = (n - i) / 100
        out.append(val * env)
    return out

def triangle(freq, dur, vol=0.2):
    n = int(SR * dur)
    out = []
    for i in range(n):
        t = i / SR
        phase = (t * freq) % 1.0
        val = (4 * abs(phase - 0.5) - 1) * vol
        env = 1.0
        if i < 200: env = i / 200
        if i > n - 500: env = (n - i) / 500
        out.append(val * env)
    return out

def square(freq, dur, vol=0.15):
    n = int(SR * dur)
    out = []
    for i in range(n):
        t = i / SR
        phase = (t * freq) % 1.0
        val = (1 if phase < 0.5 else -1) * vol
        # Quick decay for plucky sound
        env = max(0, 1 - (i / n) * 1.5)
        if i < 50: env *= i / 50
        out.append(val * env)
    return out

def noise(dur, vol=0.1):
    n = int(SR * dur)
    return [random.uniform(-vol, vol) * max(0, 1 - i/n * 2) for i in range(n)]

def kick(vol=1.0):
    """Pitch-dropping sine = punchy kick"""
    n = int(SR * 0.2)
    out = []
    for i in range(n):
        t = i / SR
        freq = 160 * math.exp(-t * 20)  # 160Hz dropping to ~40Hz
        env = max(0, 1 - t * 5)
        out.append(math.sin(2 * math.pi * freq * t) * env * vol)
    return out

def clap(vol=0.6):
    return noise(0.08, vol * 0.8)

def hihat(open_hat=False, vol=0.15):
    dur = 0.12 if open_hat else 0.03
    return noise(dur, vol)

def generate():
    total_samples = SR * DURATION
    track = [0.0] * total_samples
    random.seed(42)

    print(f"Generating {TOTAL_BARS} bars at {BPM} BPM...")

    for bar in range(TOTAL_BARS):
        for step in range(STEPS):
            global_step = bar * STEPS + step
            t = global_step * STEP
            if t >= DURATION - 1:
                break
            offset = int(t * SR)

            # Volume builds over time
            vol_mult = 0.7
            if t > 30: vol_mult = 0.85
            if t > 60: vol_mult = 1.0
            if t > 120: vol_mult = 1.1

            s = step  # 0-15

            # Kick
            if s in KICK_STEPS:
                mix(track, kick(0.8 * vol_mult), offset)

            # Clap
            if s in CLAP_STEPS:
                mix(track, clap(0.5 * vol_mult), offset)

            # Hi-hat
            if s in OHAT_STEPS:
                mix(track, hihat(True, 0.12 * vol_mult), offset)
            elif s in HAT_STEPS:
                mix(track, hihat(False, 0.08 * vol_mult), offset)

            # Bass (sawtooth, lowpassed feel from short duration)
            if s in BASS_STEPS:
                freq = BASS_NOTES[s]
                mix(track, saw(freq, STEP * 1.2, 0.35 * vol_mult), offset)

            # Chord (triangle pads on beats 0 and 8)
            if s in CHORD_STEPS:
                chord_idx = s // 4
                for freq in CHORDS[chord_idx]:
                    mix(track, triangle(freq, STEP * 7, 0.06 * vol_mult), offset)

            # Arp melody (plucky square)
            freq = ARP_NOTES[s]
            mix(track, square(freq, STEP * 0.8, 0.12 * vol_mult), offset)

            # Bell on every 4th step
            if s % 4 == 0:
                mix(track, sine(freq * 2, STEP * 1.5, 0.04 * vol_mult), offset)

    # Fade in/out
    print("Applying fades...")
    fade_in = int(2 * SR)
    fade_out = int(5 * SR)
    for i in range(fade_in):
        track[i] *= i / fade_in
    for i in range(fade_out):
        track[total_samples - 1 - i] *= i / fade_out

    # Normalize to loud
    peak = max(abs(s) for s in track)
    if peak > 0:
        track = [s * (0.9 / peak) for s in track]

    # Write WAV
    print("Writing WAV...")
    with wave.open(WAV_PATH, 'w') as wf:
        wf.setnchannels(1)
        wf.setsampwidth(2)
        wf.setframerate(SR)
        for s in track:
            clamped = max(-1.0, min(1.0, s))
            wf.writeframes(struct.pack('<h', int(clamped * 32767)))

    # Convert to MP3 — VERY loud
    print("Converting to MP3...")
    subprocess.run([
        'ffmpeg', '-y', '-i', WAV_PATH,
        '-af', 'volume=3,loudnorm=I=-6:TP=0:LRA=5',
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

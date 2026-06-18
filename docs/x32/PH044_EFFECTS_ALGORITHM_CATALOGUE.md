# PH044 — X32 Effects Algorithm Catalogue

**Status:** Initial verified catalogue (documentation only)  
**Date:** 2026-06-18  
**Authority:** `docs/x32/PH044_EFFECTS_DISCOVERY_AUDIT.md`  
**Source:** Unofficial X32/M32 OSC Remote Protocol appendix — Effects enums, names and preset names table (Patrick-Gilles Maillot)

---

## How to Read This Catalogue

| Column | Meaning |
|---|---|
| **Slot group** | `FX1-4` or `FX5-8` — enum integers are **not portable** between groups |
| **Enum ID** | Integer written to `/fx/{slot}/type` for slots in that group |
| **Code** | Four-letter canonical algorithm identity |
| **Name** | Human label from OSC appendix |
| **Category** | Application taxonomy for package planning |
| **X32/M32** | Listed in Maillot appendix — assumed available on M32 unless desk proves otherwise |
| **Package fit** | Initial suitability: Vocal, Horn, Drum, FOH, Special, Dub, Disco — not preset finalisation |

**Uncertainty:** Enum ID → audible algorithm mapping is **documentation-verified only**. Live desk should confirm type write round-trip before automation.

---

## FX1–FX4 Algorithms (enum 0…60)

Slots **FX1, FX2, FX3, FX4** accept this catalogue (`/fx/[1…4]/type` int [0…60]).

| Enum | Code | Name | Category | X32/M32 | Vocal | Horn | Drum | FOH | Special | Dub | Disco |
|---:|---|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 0 | HALL | Hall Reverb | Hall | ✓ | ✓ | | ✓ | | | | |
| 1 | AMBI | Ambiance | Reverb | ✓ | ✓ | ✓ | ✓ | | | ✓ | |
| 2 | RPLT | Rich Plate Reverb | Plate | ✓ | ✓ | | | | | | |
| 3 | ROOM | Room Reverb | Room | ✓ | ✓ | ✓ | ✓ | | | | |
| 4 | CHAM | Chamber Reverb | Room | ✓ | ✓ | ✓ | | | | | |
| 5 | PLAT | Plate Reverb | Plate | ✓ | ✓ | | | | | | |
| 6 | VREV | Vintage Reverb | Reverb | ✓ | ✓ | | | | ✓ | | |
| 7 | VRM | Vintage Room | Room | ✓ | ✓ | | | | | | |
| 8 | GATE | Gated Reverb | Special FX | ✓ | | | ✓ | | ✓ | | ✓ |
| 9 | RVRS | Reverse Reverb | Special FX | ✓ | | | | | ✓ | ✓ | |
| 10 | DLY | Stereo Delay | Delay | ✓ | ✓ | ✓ | ✓ | | | ✓ | ✓ |
| 11 | 3TAP | 3-Tap Delay | Delay | ✓ | | ✓ | ✓ | | | ✓ | ✓ |
| 12 | 4TAP | Rhythm Delay | Delay | ✓ | | | ✓ | | | | ✓ |
| 13 | CRS | Stereo Chorus | Chorus | ✓ | ✓ | ✓ | | | | | ✓ |
| 14 | FLNG | Stereo Flanger | Flanger | ✓ | | ✓ | | | | ✓ | ✓ |
| 15 | PHAS | Stereo Phaser | Flanger | ✓ | | ✓ | | | | | ✓ |
| 16 | DIMC | Dimension-C | Chorus | ✓ | ✓ | | | | ✓ | | |
| 17 | FILT | Mood Filter | Special FX | ✓ | | | | | ✓ | ✓ | ✓ |
| 18 | ROTA | Rotary Speaker | Special FX | ✓ | | ✓ | | | ✓ | | |
| 19 | PAN | Tremolo/Panner | Special FX | ✓ | | | | | ✓ | ✓ | ✓ |
| 20 | SUB | Suboctaver | Special FX | ✓ | | | ✓ | | ✓ | | |
| 21 | D/RV | Delay+Chamber | Delay | ✓ | ✓ | | | | | ✓ | |
| 22 | CR/R | Chorus+Chamber | Chorus | ✓ | ✓ | ✓ | | | | | |
| 23 | FL/R | Flanger+Chamber | Flanger | ✓ | | ✓ | | | | ✓ | |
| 24 | D/CR | Delay+Chorus | Delay | ✓ | ✓ | ✓ | | | | ✓ | ✓ |
| 25 | D/FL | Delay+Flanger | Delay | ✓ | | ✓ | | | | ✓ | ✓ |
| 26 | MODD | Modulation Delay | Dub Delay | ✓ | | | | | | ✓ | ✓ |
| 27 | GEQ2 | Dual Graphic EQ | Graphic EQ | ✓ | | | | ✓ | | | |
| 28 | GEQ | Stereo Graphic EQ | Graphic EQ | ✓ | | | | ✓ | | | |
| 29 | TEQ2 | Dual TrueEQ | Graphic EQ | ✓ | | | | ✓ | | | |
| 30 | TEQ | Stereo TrueEQ | Graphic EQ | ✓ | | | | ✓ | | | |
| 31 | DES2 | Dual DeEsser | Enhancer | ✓ | ✓ | | | | | | |
| 32 | DES | Stereo DeEsser | Enhancer | ✓ | ✓ | | | | | | |
| 33 | P1A | Stereo Xtec EQ1 | Graphic EQ | ✓ | ✓ | | | ✓ | | | |
| 34 | P1A2 | Dual Xtec EQ1 | Graphic EQ | ✓ | | | | ✓ | | | |
| 35 | PQ5 | Stereo Xtec EQ5 | Graphic EQ | ✓ | | | | ✓ | | | |
| 36 | PQ5S | Dual Xtec EQ5 | Graphic EQ | ✓ | | | | ✓ | | | |
| 37 | WAVD | Wave Designer | Special FX | ✓ | | | ✓ | | ✓ | | |
| 38 | LIM | Precision Limiter | Limiter | ✓ | | | | ✓ | | | |
| 39 | CMB | Combinator | Compressor | ✓ | | | | ✓ | ✓ | | |
| 40 | CMB2 | Dual Combinator | Compressor | ✓ | | | | ✓ | | | |
| 41 | FAC | Fair Comp | Compressor | ✓ | ✓ | | ✓ | ✓ | | | |
| 42 | FAC1M | M/S Fair Comp | Compressor | ✓ | | | | ✓ | | | |
| 43 | FAC2 | Dual Fair Comp | Compressor | ✓ | | | | ✓ | | | |
| 44 | LEC | Leisure Comp | Compressor | ✓ | ✓ | | | ✓ | | | |
| 45 | LEC2 | Dual Leisure Comp | Compressor | ✓ | | | | ✓ | | | |
| 46 | ULC | Ultimo Comp | Compressor | ✓ | | | ✓ | ✓ | | | |
| 47 | ULC2 | Dual Ultimo Comp | Compressor | ✓ | | | | ✓ | | | |
| 48 | ENH2 | Dual Enhancer | Enhancer | ✓ | ✓ | | | | | | |
| 49 | ENH | Stereo Enhancer | Enhancer | ✓ | ✓ | | | | | | |
| 50 | EXC2 | Dual Exciter | Enhancer | ✓ | ✓ | | | | | | |
| 51 | EXC | Stereo Exciter | Enhancer | ✓ | ✓ | | | | | | |
| 52 | IMG | Stereo Imager | Enhancer | ✓ | | | | ✓ | | | |
| 53 | EDI | Edison EX1 | Special FX | ✓ | | | | | ✓ | | |
| 54 | SON | Sound Maxer | Special FX | ✓ | | | | ✓ | ✓ | | |
| 55 | AMP2 | Dual Guitar Amp | Special FX | ✓ | | | | | ✓ | | |
| 56 | AMP | Stereo Guitar Amp | Special FX | ✓ | | | | | ✓ | | |
| 57 | DRV2 | Dual Tube Stage | Special FX | ✓ | | ✓ | | | ✓ | | |
| 58 | DRV | Stereo Tube Stage | Special FX | ✓ | | ✓ | | | ✓ | | |
| 59 | PIT2 | Dual Pitch Shifter | Special FX | ✓ | | | | | ✓ | | |
| 60 | PIT | Stereo Pitch | Special FX | ✓ | | | | | ✓ | | |

---

## FX5–FX8 Algorithms (enum 0…33)

Slots **FX5, FX6, FX7, FX8** accept this catalogue (`/fx/[5…8]/type` int [0…33]). **No reverb or standard delay algorithms** in this enum table.

| Enum | Code | Name | Category | X32/M32 | Vocal | Horn | Drum | FOH | Special | Dub | Disco |
|---:|---|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 0 | GEQ2 | Dual Graphic EQ | Graphic EQ | ✓ | | | | ✓ | | | |
| 1 | GEQ | Stereo Graphic EQ | Graphic EQ | ✓ | | | | ✓ | | | |
| 2 | TEQ2 | Dual TrueEQ | Graphic EQ | ✓ | | | | ✓ | | | |
| 3 | TEQ | Stereo TrueEQ | Graphic EQ | ✓ | | | | ✓ | | | |
| 4 | DES2 | Dual DeEsser | Enhancer | ✓ | ✓ | | | | | | |
| 5 | DES | Stereo DeEsser | Enhancer | ✓ | ✓ | | | | | | |
| 6 | P1A | Stereo Xtec EQ1 | Graphic EQ | ✓ | ✓ | | | ✓ | | | |
| 7 | P1A2 | Dual Xtec EQ1 | Graphic EQ | ✓ | | | | ✓ | | | |
| 8 | PQ5 | Stereo Xtec EQ5 | Graphic EQ | ✓ | | | | ✓ | | | |
| 9 | PQ5S | Dual Xtec EQ5 | Graphic EQ | ✓ | | | | ✓ | | | |
| 10 | WAVD | Wave Designer | Special FX | ✓ | | | ✓ | | ✓ | | |
| 11 | LIM | Precision Limiter | Limiter | ✓ | | | | ✓ | | | |
| 12 | FAC | Fair Comp | Compressor | ✓ | ✓ | | ✓ | ✓ | | | |
| 13 | FAC1M | M/S Fair Comp | Compressor | ✓ | | | | ✓ | | | |
| 14 | FAC2 | Dual Fair Comp | Compressor | ✓ | | | | ✓ | | | |
| 15 | LEC | Leisure Comp | Compressor | ✓ | ✓ | | | ✓ | | | |
| 16 | LEC2 | Dual Leisure Comp | Compressor | ✓ | | | | ✓ | | | |
| 17 | ULC | Ultimo Comp | Compressor | ✓ | | | ✓ | ✓ | | | |
| 18 | ULC2 | Dual Ultimo Comp | Compressor | ✓ | | | | ✓ | | | |
| 19 | ENH2 | Dual Enhancer | Enhancer | ✓ | ✓ | | | | | | |
| 20 | ENH | Stereo Enhancer | Enhancer | ✓ | ✓ | | | | | | |
| 21 | EXC2 | Dual Exciter | Enhancer | ✓ | ✓ | | | | | | |
| 22 | EXC | Stereo Exciter | Enhancer | ✓ | ✓ | | | | | | |
| 23 | IMG | Stereo Imager | Enhancer | ✓ | | | | ✓ | | | |
| 24 | EDI | Edison EX1 | Special FX | ✓ | | | | | ✓ | | |
| 25 | SON | Sound Maxer | Special FX | ✓ | | | | ✓ | ✓ | | |
| 26 | AMP2 | Dual Guitar Amp | Special FX | ✓ | | | | | ✓ | | |
| 27 | AMP | Stereo Guitar Amp | Special FX | ✓ | | | | | ✓ | | |
| 28 | DRV2 | Dual Tube Stage | Special FX | ✓ | | ✓ | | | ✓ | | |
| 29 | DRV | Stereo Tube Stage | Special FX | ✓ | | ✓ | | | ✓ | | |
| 30 | PHAS | Stereo Phaser | Flanger | ✓ | | ✓ | | | | | ✓ |
| 31 | FILT | Mood Filter | Special FX | ✓ | | | | | ✓ | ✓ | ✓ |
| 32 | PAN | Tremolo/Panner | Special FX | ✓ | | | | | ✓ | ✓ | ✓ |
| 33 | SUB | Suboctaver | Special FX | ✓ | | | ✓ | | ✓ | | |

---

## Parameter Reference — Package Candidate Algorithms

Read/write: `/fx/{slot}/par/{par#}` (zero-padded `01`…`64` in OSC practice). Types: `linf`, `logf`, `enum`, `level`.

### HALL — Hall Reverb (FX1–4 enum 0)

| Par# | Name | Type & range |
|---:|---|---|
| 1 | Pre Delay | linf [0…200] |
| 2 | Decay | logf [0.2…5] |
| 3 | Size | linf [2…100] |
| 4 | Damping | logf [1k…20k] |
| 5 | Diffuse | linf [1…30] |
| 6 | Level | linf [-12…+12] |
| 7 | Lo Cut | logf [10…500] |
| 8 | Hi Cut | logf [200…20k] |
| 9 | Bass Multi | logf [0.5…2] |
| 10 | Spread | linf [0…50] |
| 11 | Shape | linf [0…250] |
| 12 | Mod Speed | linf [0…100] |

### PLAT — Plate Reverb (FX1–4 enum 5)

| Par# | Name | Type & range |
|---:|---|---|
| 1 | Pre Delay | linf [0…200] |
| 2 | Decay | logf [0.5…10] |
| 3 | Size | linf [2…100] |
| 4 | Damping | logf [1k…20k] |
| 5 | Diffuse | linf [1…30] |
| 6 | Level | linf [-12…+12] |
| 7 | Lo Cut | logf [10…500] |
| 8 | Hi Cut | logf [200…20k] |
| 9 | Bass Multi | logf [0.5…2] |
| 10 | Xover | logf [10…500] |
| 11 | Mod Depth | linf [1…50] |
| 12 | Mod Speed | linf [0…100] |

### DLY — Stereo Delay (FX1–4 enum 10)

| Par# | Name | Type & range |
|---:|---|---|
| 1 | Mix | linf [0…100] |
| 2 | Time | linf [1…3000] |
| 3 | Mode | enum [ST, X, M] |
| 4 | Factor L | enum [1/4, 3/8, 1/2, 2/3, 1, 4/3, 3/2, 2, 3] |
| 5 | Factor R | enum [1/4, 3/8, 1/2, 2/3, 1, 4/3, 3/2, 2, 3] |
| 6 | Offset L/R | linf [-100…+100] |
| 7 | Lo Cut | logf [10…500] |
| 8 | Hi Cut | logf [200…20k] |
| 9 | Feed Lo Cut | logf [10…500] |
| 10 | Feed Left | linf [1…100] |
| 11 | Feed Right | linf [1…100] |
| 12 | Feed Hi Cut | logf [200…20k] |

### MODD — Modulation Delay (FX1–4 enum 26)

| Par# | Name | Type & range |
|---:|---|---|
| 1 | Time | linf [1…3000] |
| 2 | Delay | enum [1, 1/2, 2/3, 3/2] |
| 3 | Feed | linf [0…100] |
| 4 | Lo Cut | logf [10…500] |
| 5 | Hi Cut | logf [200…20k] |
| 6 | Depth | linf [0…100] |
| 7 | Rate | logf [0.05…10] |
| 8 | Setup | enum [PAR, SER] |
| 9 | Type | enum [AMB, CLUB, HALL] |
| 10 | Decay | linf [1…10] |
| 11 | Damping | logf [1k…20k] |
| 12 | Balance | linf [-100…+100] |
| 13 | Mix | linf [0…100] |

### GEQ — Stereo Graphic EQ (FX1–4 enum 28; FX5–8 enum 1)

| Par# | Name | Type & range |
|---:|---|---|
| 1…31 | Eq Level L/R (per band) | linf [-15…+15] each |
| 32 | Master Level L/R | linf [-15…+15] |

### LIM — Precision Limiter (FX1–4 enum 38; FX5–8 enum 11)

| Par# | Name | Type & range |
|---:|---|---|
| 1 | Input Gain | linf [0…18] |
| 2 | Out Gain | linf [-18…0] |
| 3 | Squeeze | linf [0…100] |
| 4 | Knee | linf [0…10] |
| 5 | Attack | logf [0.05…1] |
| 6 | Release | logf [20…2000] |
| 7 | Stereo Link | enum [OFF, ON] |
| 8 | Auto Gain | enum [OFF, ON] |

### FAC — Fair Comp (FX1–4 enum 41; FX5–8 enum 12)

Full parameter table in Maillot Effects Parameters chapter — includes threshold, ratio, knee, attack, release, mix, etc. **Not expanded here**; retrieve from protocol document before preset authoring.

---

## Algorithms Not Fully Parameterised Here

The Maillot document lists parameters for all 61 + 34 algorithms. This catalogue includes full tables only for **package candidate** algorithms. Remaining algorithms: see Maillot PDF Effects Parameters chapter or defer to PH044.02 live capture.

**Do not invent** parameter names, numbers, or ranges for algorithms omitted from this file.

---

## OSC Path Quick Reference

| Purpose | Path |
|---|---|
| Read/write algorithm type | `/fx/{01…08}/type` |
| Read/write parameter *n* | `/fx/{01…08}/par/{01…64}` |
| FX input source L/R (FX1–4 documented) | `/fx/{01…04}/source/l`, `/source/r` |
| FX return fader | `/fxrtn/{01…08}/mix/fader` |
| Effect preset library | `/-libs/fx/{001…100}/name`, `/type`, `/flags` |
| Load effect preset to slot | `/load ,si libfx {index} {slot}` (see Maillot load chapter) |

---

End of PH044 Effects Algorithm Catalogue

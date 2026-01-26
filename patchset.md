# Ed and the Shadow Boys — Lighting Patch (Single Universe)

**System:** 1x DMX Universe (512ch)  
**Control:** x32-controller / LightingEngine  
**Universe:** U1

## Fixture Summary
- 4x LED Bars — 48ch mode (8 segments × 6ch)
- 4x Wash Pars — 3ch RGB mode (spaced addressing)
- 3x Movers — 14ch mode

---

## DMX Patch Table (Universe 1)

### LED Bars (48ch each)
| Fixture | Start | End | Notes |
|---|---:|---:|---|
| Bar 1 | 1 | 48 | 8 segments × 6ch |
| Bar 2 | 49 | 96 |  |
| Bar 3 | 97 | 144 |  |
| Bar 4 | 145 | 192 |  |

**Bar segment channel map (per segment base = start + seg*6):**
1. Dimmer
2. Strobe
3. Red
4. Green
5. Blue
6. Program

---

### Washes (3ch RGB each)
> NOTE: washes are intentionally spaced by 4 addresses; the +3 channel is unused (“gap”) to help detect accidental 4th-channel writes.

| Fixture | Start | End | Mode |
|---|---:|---:|---|
| Wash 1 | 232 | 234 | 3ch RGB |
| Wash 2 | 236 | 238 | 3ch RGB |
| Wash 3 | 240 | 242 | 3ch RGB |
| Wash 4 | 244 | 246 | 3ch RGB |

**Wash channel map:**
1. Red
2. Green
3. Blue

---

### Movers (14ch each)
| Fixture | Start | End | Mode |
|---|---:|---:|---|
| Mover 1 | 247 | 260 | 14ch |
| Mover 2 | 261 | 274 | 14ch |
| Mover 3 | 275 | 288 | 14ch |

**Mover channel map (formalized — adjust labels if your manual differs):**
1. Pan
2. Tilt
3. Pan Fine (if used)
4. Tilt Fine (if used)
5. Speed / Movement (if used)
6. Dimmer
7. Shutter
8. Red
9. Green
10. Blue
11. White
12. (unused/reserved)
13. (unused/reserved)
14. (unused/reserved)

---

## Free Space (Universe 1)
- Channels 193..231: free (reserved for future)
- Channels 289..512: free (reserved for expansion)

## Notes / Requirements
- All fixtures must be patched exactly as above on Universe 1.
- Bars must be in **48ch mode**.
- Washes must be in **3ch RGB** mode.
- Movers must be in **14ch** mode.

## Verified Scene Parameters (Movers)
*Offsets are 0-based relative to Mover Start Address*

| Offset | Ch | Function | Setup | Sunshine | Madness | Aqua | Rasta | Focus Left | Focus Center | Focus Right | Police |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **0** | 1 | **Pan** | 127 | Anim (107-147) | Rand (0-255) | 127 | Osc (117-137) | **0** | **0** | **0** | **Sweep** (64-192) Desync |
| **1** | 2 | **Pan Fine** | - | - | Rand (0-255) | - | 0 | 0 | 0 | 0 | - |
| **2** | 3 | **Tilt** | 127 | Anim (80-120) | Rand (80-120) | 127 | Osc (117-147) | **75** | **75** | **75** | **0** |
| **3** | 4 | **Tilt Fine** | - | - | - | - | 0 | 0 | 0 | 0 | - |
| **4** | 5 | **Speed** | - | - | - | - | 0 | 0 | 0 | 0 | - |
| **5** | 6 | **Dimmer** | Fade | 255 | 255 | 255 | 255 | **255** | **255** | **255** | **255** |
| **6** | 7 | **Shutter** | - | - | - | - | - | 0 | 0 | 0 | **0** |
| **7** | 8 | **Red** | 255 | 255 | 255 | 0 | 255 | 0 | 0 | 0 | **Switch** (255/0) |
| **8** | 9 | **Green** | 255 | 255 | 255 | 255 | 0 | 0 | 0 | 0 | 0 |
| **9** | 10 | **Blue** | 255 | 0 | 255 | 255 | 0 | 0 | 0 | 0 | **Switch** (0/255) |
| **10** | 11 | **White** | 255 | Pulse (0-127) | 255 | Flicker (0-50) | 0 | **255** (M1) | **255** (M2) | **255** (M3) | 0 |

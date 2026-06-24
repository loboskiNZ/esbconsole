# PH065 — Application Validation Checklist

Status: R7 (pre-cutover) and R9 (post-cutover) — Gate 5  
Authority: PH061 §8.4, PH065 R7/R9  
Date: 2026-06-24

---

## Cloud Studio (`band.edandtheshadowboys.com`)

| # | Test | Pre-cutover | Post-cutover | Pass |
|---|------|:-----------:|:------------:|:----:|
| A1 | `GET /up` → 200 | ☐ | ☐ | ☐ |
| A2 | Login page loads (no 500) | ☐ | ☐ | ☐ |
| A3 | Session / auth scaffold | ☐ | ☐ | ☐ |
| A4 | Studio library availability | ☐ | ☐ | ☐ |
| A5 | Songs list read (if data) | ☐ | ☐ | ☐ |
| A6 | Charts list / open PDF (if data) | ☐ | ☐ | ☐ |
| A7 | Shows list (if data) | ☐ | ☐ | ☐ |
| A8 | Performances list (if data) | ☐ | ☐ | ☐ |
| A9 | Onboarding | **DISABLED** until PH048B | ☐ gated | ☐ |
| A10 | Effects / console prep UI (if exposed) | ☐ | ☐ | ☐ |

---

## Website (`edandtheshadows` Forge site)

| # | Test | Pass |
|---|------|:----:|
| W1 | Public pages load | ☐ |
| W2 | Authentication (if applicable) | ☐ |
| W3 | No 500 on DB-backed routes | ☐ |
| W4 | Co-tenant DB connection verified | ☐ |

---

## Future Live Stage compatibility (post–Gate 6 / LS realignment)

| # | Test | Pass |
|---|------|:----:|
| L1 | `backend/` CCMM loader loads same paths | ☐ |
| L2 | `information_schema` column match Cloud vs LS for shared tables | ☐ |
| L3 | `php artisan ccmm:validate-schema` on LS after parity apply | ☐ |
| L4 | LS-EXT tables present only on Live Stage | ☐ |
| L5 | No `person_invitations` on Live Stage (Cloud-only) | ☐ |

---

## Gate 5 pass criteria

- [ ] A1 + A2 pass post-cutover
- [ ] No critical 500 errors on scoped smoke paths
- [ ] Onboarding remains disabled unless PH048B explicitly approved
- [ ] Operator sign-off recorded

---

End of Application Validation Checklist

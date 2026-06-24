# PH065 — Cloud Provisioning Checklist

Status: R2 provisioning — **new cluster only**  
Authority: PH056 Path B, PH058, PH065 R2  
Date: 2026-06-24

---

## DigitalOcean Managed PostgreSQL

| # | Item | Requirement | Done | Notes |
|---|------|-------------|:----:|-------|
| P1 | **Create new cluster** | Separate from forensic `pr-esbdata-68105` | ☐ | Path B |
| P2 | **Naming convention** | `esb-cloud-<env>` e.g. `esb-cloud-prod` | ☐ | Operator records in Gate 2 |
| P3 | **PostgreSQL version** | **16+** (match PH059 / PH064) | ☐ | |
| P4 | **Database name** | `esb_cloud` (operator-approved) | ☐ | Not `defaultdb` |
| P5 | **Application user** | Least privilege; not superuser | ☐ | |
| P6 | **SSL** | `sslmode=require` for Laravel | ☐ | |
| P7 | **Extensions** | `uuid-ossp` or gen_random_uuid native — verify | ☐ | Laravel uuid columns |
| P8 | **Trusted sources** | Forge app servers + operator IP only | ☐ | |
| P9 | **Automated backups** | Enabled | ☐ | |
| P10 | **PITR** | Enabled if available on plan | ☐ | |
| P11 | **Monitoring/alerts** | CPU, connections, disk | ☐ | |
| P12 | **Connection pool** | Document max connections | ☐ | |

---

## Forge integration

| # | Item | Done | Notes |
|---|------|:----:|-------|
| F1 | Credentials stored in Forge env for Cloud Studio only | ☐ | |
| F2 | Website co-tenancy decision documented | ☐ | Same cluster if yes |
| F3 | `backend/` **not** given Cloud credentials | ☐ | PH055 |
| F4 | Deploy hook reviewed — no migrate to forensic DB | ☐ | |
| F5 | Maintenance page ready for R8 | ☐ | Optional |

---

## Validation (before R3)

```bash
psql "postgresql://USER:PASS@HOST:PORT/esb_cloud?sslmode=require" -c "SELECT version();"
psql ... -c "\dt"   # Expect empty public schema
```

| Check | Pass |
|-------|:----:|
| Connect succeeds | ☐ |
| Database empty | ☐ |
| Not forensic host | ☐ |

---

## Rollback (R2)

Wrong cluster → delete empty database/cluster; reprovision. Forensic cluster untouched.

---

End of Cloud Provisioning Checklist

# X32 Decision Log

X32 console workspace and integration decisions. Platform-wide governance remains in `docs/DECISION_LOG.md`.

---

## X32-DEC-001 — Monitor Buses Are First-Class Workspaces

| Field | Value |
|-------|-------|
| **Decision ID** | X32-DEC-001 |
| **Title** | Monitor Buses Are First-Class Workspaces |
| **Status** | Approved |

### Decision

Monitor buses are not treated solely as routing destinations. Each monitor bus represents a first-class workspace within the show model.

**Canonical route:**

```
shows/{show}/console/bus/{bus}/layout
```

The bus workspace is the single source of truth for:

- Monitor mix configuration
- Bus processing
- Bus EQ
- Bus dynamics
- Output assignments
- Future snapshots/presets
- Future musician self-mix

Engineer and musician views must operate on the same underlying bus workspace and data model.

| Role | Access |
|------|--------|
| **Engineer** | Full control |
| **Musician** | Permission-scoped control of assigned buses only |

### Relationship to Other Workspaces

| Workspace | Scope |
|-----------|--------|
| **Overview** | Channel-centric operational view |
| **Routing** | Signal flow and physical patching |
| **Configuration** | Console architecture and configuration overview |
| **Bus Workspace** | Monitor/IEM-centric workspace |

### Future Consequence

The IEM / Return Buses section becomes a navigation surface into bus workspaces rather than a static informational panel.

---

End of X32 Decision Log — X32-DEC-001

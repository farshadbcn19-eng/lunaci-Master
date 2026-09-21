# Decision Tree & Playbook Gap Closure
## LDO008 — Addendum 1

*Closes Findings 3–6 from the 2026-09-21 LDO008 Crisis Playbook Stress Test — a new playbook for LUNA identity/likeness challenges, a Decision Tree branch to route to it, a Level 1 resolution clarification, a check-in cadence for active incidents, and a Playbook C example extension for translation failures.*
*Document Status: Ratified · Revision 1.0 · Effective 2026-09-21 · Approved by: Farshad Zamani, Founder — Patriotic Trade SL*
*Drafted 2026-09-21, closing Findings 3–6 of the same-day LDO008 Crisis Playbook Stress Test, prior to ratification.*

---

## Contents
1. Purpose & Scope
2. Position in the Hierarchy
3. Playbook E — Identity, Likeness & Provenance Challenge *(closes Finding 3)*
4. Decision Tree Extension *(closes Finding 3)*
5. Counsel Trigger Addition *(closes Finding 3)*
6. Level 1 Resolution Clarification *(closes Finding 4)*
7. Check-In Cadence During Active Incidents *(closes Finding 5)*
8. Playbook C Example Extension *(closes Finding 6)*
9. What This Addendum Does Not Address
10. Decisions on Ratification
11. Governing Authority & Precedence

---

## 1. Purpose & Scope

The 2026-09-21 stress test of LDO008 traced nine scenarios through the document's actual mechanics and found six issues, ranked by severity. This addendum closes the four that are fixable by adding or clarifying process — Findings 3, 4, 5, and 6. Findings 1 (no counsel relationship exists to fulfill the same-day notification requirement) and 2 (no contingency for the sole approver being unreachable) are **not addressed here**, because they are infrastructure and organizational gaps, not document-wording gaps — no addendum text closes them. Section 9 restates this boundary explicitly.

This addendum does not revisit Findings that passed the stress test (Playbook B, Playbook A, or the LDO006↔LDO008 disclosure-error handoff in Playbook C) — those worked as designed and are left untouched.

---

## 2. Position in the Hierarchy

```
Brand Constitution
        ↓
Appendix A–C
        ↓
LMB001 / LMB002 / LMB003 / LMB004
        ↓
LDO006 — AI Generation Governance (legal/claims authority)
        ↓
LDO007 — Community Operations (routine response; escalates here)
        ↓
LDO008 — Crisis Communication (parent document)
        ↓
LDO008 — THIS ADDENDUM (playbook, decision-tree, and cadence extensions only)
```

This addendum is subordinate to LDO008 itself, the same as any provision within it, and does not change LDO008's position relative to LDO006 or LDO007.

---

## 3. Playbook E — Identity, Likeness & Provenance Challenge

*Closes Finding 3. Added to LDO008 §8 alongside Playbooks A–D.*

A real person, or a credible third party on their behalf, publicly claims that a LUNA asset uses, resembles, or was derived from their identity or likeness without consent — or otherwise publicly challenges the AI provenance LDO006 §4.1 confirms for the LUNA-Muse reference.

- **Do not argue the resemblance publicly, and do not dismiss the claim casually.** Per §7's Response Principles, dignity-not-defensiveness applies here with the same force it applies everywhere else — a rushed denial is exactly the failure mode this playbook exists to prevent.
- **Do not remove or alter the challenged asset while the claim is being assessed**, unless LDO006 §15's Responsible Person or engaged counsel advises otherwise — the asset and its AI Generation Record (LDO006 §10) are the evidence the claim will actually be evaluated against.
- **Pull the asset's AI Generation Record and formally run the LDO006 §5.2 three-part deepfake test** — this is not a rhetorical check; it is the actual legal test the claim needs to be assessed against, and it should be run explicitly, not from memory.
- **This is automatically Level 2 minimum**, on the same basis as Playbook B — an identity/likeness claim carries independent legal and reputational velocity the moment it's public, regardless of how it started. Escalate to Level 3 per the existing Section 3 criteria (press involvement, spreading, etc.) exactly as any other playbook would.
- **Counsel notification is automatic and same-day**, per the Section 6 addition below (Section 5 of this addendum) — this playbook does not wait for the general "accuracy of a past claim is being challenged" trigger to be inferred; it is now named directly.
- A holding statement (LDO008 §5) may be used if visibility is high enough that silence would itself become the story — the same threshold and rules that govern any other holding statement apply unchanged.

---

## 4. Decision Tree Extension

*Closes Finding 3. Inserts a new branch into LDO008 §9, positioned immediately after the safety check and before the "own published content" check — this ordering matters: without it, an identity/likeness claim about a LUNA asset would otherwise be caught by the existing "criticism directed at LUNACI's own published content" branch and misrouted into Playbook A, which has no provision for a legal identity claim.*

The Decision Tree, with this addendum's branch inserted, reads:

```
Is there a safety or health claim involved?
  → YES: Playbook D. Private acknowledgment within the hour, regardless of level.
          Counsel notified same day (Section 6).
  → NO: continue.

Does the situation involve a real person's claim that a LUNA asset uses or
resembles their identity/likeness without consent, or otherwise challenges
LUNA's AI provenance?                                          [ADDED — Addendum 1]
  → YES: Playbook E. Counsel notified same day (Section 6, as extended).
          Level 2 minimum, regardless of how the claim started.
  → NO: continue.

Is the criticism directed at LUNACI's own published content?
  → YES: Playbook A.
  → NO: continue.

Is it coming from a competitor or clearly competitive source?
  → YES: Playbook B.
  → NO: continue.

Did LUNACI make an actual governance or claims error?
  → YES: Playbook C.
  → NO: See Section 6 of this addendum (Level 1 Resolution Clarification)
         for where this resolves.

At every branch: does this trip any Section 6 counsel trigger?
  → YES: notify counsel the same day, independent of playbook chosen.
```

---

## 5. Counsel Trigger Addition

*Closes the remainder of Finding 3. Adds one explicit line to LDO008 §6's list of counsel notification triggers, alongside the existing five.*

> **A real person publicly claims a LUNACI/LUNA AI-generated asset uses, resembles, or was derived from their identity or likeness without consent, or otherwise challenges the provenance confirmed in LDO006 §4.1.**

This complements, rather than replaces, the existing line *"the accuracy of a past claim is being challenged"* — that line was general enough that a responder following the Decision Tree mechanically might never connect it to an identity/likeness claim specifically. This line removes that inference step.

This addendum governs the communication *process* only. LDO006 §4–5 remains the substantive authority on provenance and legal-compliance judgment for the asset itself; nothing here overrides that.

---

## 6. Level 1 Resolution Clarification

*Closes Finding 4 — the conflict between LDO008 §3 (Level 1 stays inside LDO008, uses a Section 8 playbook) and §9 (an unmatched Decision Tree result exits to LDO007).*

**Clarifying rule:** LDO008 §9's final branch — an unmatched case being "likely a Level 1, routine LDO007 matter" — applies only to an event reaching the Decision Tree *without* having first been escalated under LDO007 §8. Where an event has already been escalated into this document (i.e., it reached LDO008 specifically because an LDO007 §8 trigger fired, including the "when in doubt, escalate" trigger), an unmatched Decision Tree result does **not** exit back to LDO007. It resolves as a Level 1 event handled directly under this document, per §3, using whichever Section 8 playbook (A–E, as of this addendum) most closely fits — and this fit is recorded explicitly in the Incident Log (§11) as an "unmatched playbook, nearest fit: [X]" entry, so the gap stays visible rather than silently absorbed.

This does not change LDO007 §8's own escalation criteria — those still decide whether an event enters LDO008 at all. This rule only resolves what happens *after* entry, when the Decision Tree itself doesn't produce a clean match.

---

## 7. Check-In Cadence During Active Incidents

*Closes Finding 5 — LDO008 §3's "reclassify at the start of every check-in" had no defined interval.*

**Proposed cadence, while an incident remains Open or Monitoring (LDO008 §11):**

| Level | Reclassification cadence |
|---|---|
| Level 1 | No mandatory cadence — a single response typically closes it; reclassify immediately if any new trigger fires. |
| Level 2 | At minimum every 2 hours during active hours, or immediately on any new development (new platform spread, new complaint, journalist contact) — whichever comes first. |
| Level 3 | At minimum every 60 minutes, or immediately on any new development. |

An incident stops requiring active-cadence reclassification once it meets the Closure Checklist's first condition (LDO008 §11: no new negative signal for 48 hours) or is formally moved to Monitoring status.

These intervals were proposed as defaults, not derived from an existing document, and were confirmed as drafted on ratification — see Section 10.

---

## 8. Playbook C Example Extension

*Closes Finding 6.*

Adds one example to LDO008 §8 Playbook C's list of governance-error types, alongside forbidden language, missed AI disclosure, and exceeded claims:

> **A translation choice that reads as intended in its source language but lands as dismissive, culturally tone-deaf, or unintentionally offensive in the other (EN↔ES)** — a failure of LDO002's dual-language discipline, not necessarily a content-substance error. Handled the same as any other Playbook C event: correct the asset, then apply the same quiet-vs-visible-acknowledgment test based on reach and severity.

---

## 9. What This Addendum Does Not Address

Findings 1 and 2 from the stress test are explicitly out of scope here:

- **Finding 1 (no counsel relationship to fulfill same-day notification triggers, including the one this addendum adds in Section 5):** this is an organizational fact, not a wording gap. No addendum closes it — only engaging counsel does.
- **Finding 2 (no contingency for the sole approver being unreachable):** this requires an actual operational decision — a named backup, a defined unavailability protocol, or an accepted risk — none of which this addendum can supply on Farshad's behalf.

Raising these again here is deliberate: closing Findings 3–6 should not read as though the crisis protocol is now complete. The two most severe findings from the stress test remain open.

---

## 10. Decisions on Ratification

Three items were flagged as open in drafting and confirmed by Farshad Zamani, 2026-09-21, accepting the proposed defaults exactly as drafted:

1. ~~**Check-in cadence numbers (Section 7)**~~ — **Confirmed.** 2 hours (Level 2) / 60 minutes (Level 3), as drafted.
2. ~~**Playbook E's automatic Level 2 floor (Section 3)**~~ — **Confirmed.** Modeled on Playbook B's precedent, as drafted.
3. ~~**Incident Log format change (Section 6)**~~ — **Confirmed.** The "unmatched playbook, nearest fit: [X]" field is adopted as drafted.

**This addendum carried no outstanding open items into ratification.**

---

## 11. Governing Authority & Precedence

*Effective under Revision 1.0, as an addendum to LDO008, reviewed on the same cadence as LDO008 §13.*

This addendum is subordinate to, and may never override:
- Brand Constitution
- Appendix A — Canonical Definitions & Brand Invariants (LMB003)
- Appendix B — Brand Constants
- Appendix C — Approved & Forbidden Language
- LMB001, LMB002, LMB003, LMB004
- LDO006 — AI Generation Governance (Responsible Person authority on any product-safety, claims, or provenance matter — including the Playbook E judgment this addendum adds)
- LDO007 — Community Operations (source of escalation into this document)
- LDO008 — Crisis Communication (the document this addendum amends)

Where Playbook E's judgment touches product safety, a legal claim, or AI provenance, LDO006's Responsible Person role holds final authority on what may be stated or confirmed publicly — this addendum governs the communication process around that judgment, exactly as LDO008 §13 already establishes for the rest of the document.

---
**STATUS: RATIFIED. Approved by Farshad Zamani, Founder — Patriotic Trade SL, 2026-09-21.**
**REMINDER: Findings 1 and 2 from the stress test (no counsel relationship; no backup-approver protocol) remain open — see Section 9. This addendum does not close them.**
**END OF LDO008 ADDENDUM 1 — DECISION TREE & PLAYBOOK GAP CLOSURE**

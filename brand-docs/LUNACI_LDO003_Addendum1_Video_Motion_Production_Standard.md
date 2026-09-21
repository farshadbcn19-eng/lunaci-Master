# Video & Motion Asset Production Standard
## LDO003 — Addendum 1

*Fills the motion-specific gap LDO003 Category 5 leaves open: technical delivery specification, motion-specific shoot briefing, and production-status tracking for video assets*
*Document Status: Ratified · Revision 1.0 · Effective 2026-09-21 · Approved by: Farshad Zamani, Founder — Patriotic Trade SL*
*Drafted 2026-09-21 (v0.1–v0.4), incorporating the redline decisions and open-question resolutions recorded below, prior to ratification.*

---

### Decisions Recorded During Drafting (v0.1 → v0.4, prior to ratification)

| Decision | Effect |
|---|---|
| Deliverable-specific dates (Barcelona Walk, Brand Film) removed from this addendum | Tracked exclusively in the production calendar / LDO004 — see Section 6 |
| Duration targets (Section 4) reclassified | From proposed starting point → **Initial Operational Standard**, effective on ratification |
| Status Register removed from this addendum | Replaced with a bare requirement that an independent Production Register exist — see Section 6 |
| Production Register implementation decided (v0.3) | Spreadsheet (Excel / Google Sheets), not this document — see Section 6. Delivered as a companion file: `LUNACI_Motion_Production_Status_Register.xlsx`. Migration path to ClickUp/Notion recorded for when team and volume grow. Resolved Open Question 3. |
| Vendor codec dependency resolved (v0.4) | No vendor engaged yet — Section 4's codec/resolution spec stands as the default operational standard, not merely proposed. Revisit only if/when a vendor is engaged and their pipeline conflicts. Resolved Open Question 1. |
| Retrospective Compilation Film seasonal register resolved (v0.4) | Finished edit graded to Phase 3's own register (Autumn, per LDO005 §10) as a unifying treatment; source footage keeps its original register; LDO005 §5 governs shoots, not retrospective edits, so no exception to §5 is needed. Resolved Open Question 2. |
| **Addendum ratified (Revision 1.0)** | Approved by Farshad Zamani, Founder — Patriotic Trade SL, 2026-09-21, per the same approval format as LDO004 Addendum 1. |

*Source: Farshad Zamani, redlines and ratification, 2026-09-21.*

---

## Contents
1. Purpose & the Gap This Addendum Closes
2. Position in the Hierarchy
3. What Is Already Governed Elsewhere (and Not Duplicated Here)
4. Technical Delivery Specifications by Platform — Initial Operational Standard
5. Motion-Specific Shoot Briefing Fields
6. Status Tracking Requirement (Register Maintained Separately)
7. Asset Library & Naming — Motion Extension
8. Continuity Rules — Motion Extension
9. Open Questions — Resolution Log
10. Governing Authority & Precedence (Proposed)

---

## 1. Purpose & the Gap This Addendum Closes

LDO003 Section 3, Category 5 ("Brand Film & Motion") names motion as one of seven asset categories and sets quantities against it (Section 4) — but, unlike stills, motion has no technical delivery specification layer of its own. Product Photography and Detail & Texture inherit resolution and continuity rules through the Shoot Planning Template (LDO003 §5); LUNA Portraiture inherits identity continuity from the Character Bible. Motion inherits pacing and edit grammar from Character Bible Module 23 and camera/pacing discipline from LDO002 Template 5 — but nothing in the governing set currently specifies frame rate, delivery codec, per-platform aspect ratio, duration ceilings, or caption requirements. A brand film and a TikTok clip are currently briefed against the same generic Shoot Planning Template as a product photograph.

This gap is not hypothetical. LDO004 Addendum 1 (2026-09-13) records it directly: *"Video/motion assets (Barcelona walk, brand film) remain pending and are excluded from [the LinkedIn cadence increase] until produced"* — while confirming LUNA portraiture and product stills are production-ready. Motion is the one asset category without a tracked completion status, which is precisely why it was still open at the point a cadence decision needed to reference it.

This addendum closes that gap on three fronts: it gives motion a delivery specification (Section 4) and a shoot-briefing extension (Section 5) equivalent to what stills already have, and it requires that motion-readiness be tracked in a dedicated Production Register (Section 6) so a future cadence or activation decision can check actual status instead of relying on an ad hoc note in an unrelated addendum.

**What this addendum does not do:** it does not alter pacing, edit grammar, the mandatory silence interval (Character Bible Module 23), on-set sound direction (LDO005 §8), or the movement-discipline output criteria (LDO006 §12). Those remain governing and unchanged. **It also does not track individual deliverables, dates, or their current status** — the Barcelona Walk B-roll and Brand Film cited above are named only to establish why this gap matters, not as entries this document maintains. Deliverable-level scheduling and status belong to the production calendar and LDO004; this addendum's only obligation on that front is Section 6's requirement that a tracking system exist at all.

---

## 2. Position in the Hierarchy

```
Brand Constitution
        ↓
Appendix A–C
        ↓
LMB001 / LMB002 / LMB003
        ↓
LUNA Character Bible V4.2   (Modules 13, 18, 23 — motion technique, pacing, edit grammar)
        ↓
LDO005   (Creative Direction — §8 on-set sound register)
        ↓
LDO003   (Visual Asset Production Plan — Category 5, §§4–5)
        ↓
LDO003 — THIS ADDENDUM   (delivery spec, briefing extension, status-tracking requirement — Category 5 only)
        ↓
LDO004   (schedules from what this addendum confirms is delivered)
```

This addendum is subordinate to LDO003 itself, the same as any provision within it, and does not change LDO003's position relative to LDO002, LDO004, or LDO005.

---

## 3. What Is Already Governed Elsewhere (and Not Duplicated Here)

| Governs | Document / Section |
|---|---|
| Pacing, edit grammar, mandatory silence interval | Character Bible Modules 13, 18, 23 |
| On-set sound register (what plays during a shoot) | LDO005 §8 |
| Selection direction for finished-asset sound (instrumentation sparseness) | LDO005 §8 |
| Camera and pacing discipline for TikTok/Reels scripts | LDO002 Template 5 |
| Output acceptance — pace, silence, movement discipline | LDO006 §12 |
| What must exist, in what quantity, by phase | LDO003 §4 |

Nothing below restates or overrides these. This addendum operates strictly in the gap above them: technical delivery, briefing, and status tracking.

---

## 4. Technical Delivery Specifications by Platform — Initial Operational Standard

**Duration targets below are LUNACI's Initial Operational Standard, effective on ratification of this addendum — not a proposal awaiting confirmation.** They are set directly from LDO002 Template 5's pacing doctrine and Pillar D's presence-not-performance rule, not from external benchmarking, so they do not depend on vendor input to be correct. Once ratified, a motion deliverable briefed outside these duration targets is a governance departure, not a style choice, and should be treated the same as any other LDO003 §5 briefing gap.

**Resolution and codec values are also now the operational standard, not a proposal.** No production/edit vendor is engaged as of this revision (confirmed by Farshad Zamani, 2026-09-21), so there is no known pipeline constraint to weigh against ProRes 422 HQ master / H.264 MP4 delivery — a standard, widely-supported baseline for exactly this workflow. If a vendor is engaged later and their pipeline genuinely conflicts with these values, only the resolution/codec columns are revisited at that point; duration targets are not reopened by that process, per Farshad's Section 4 decision above.

| Platform / Use | Aspect Ratio | Resolution (master) | Frame Rate | Duration Target | Captions |
|---|---|---|---|---|---|
| Instagram Reels | 9:16 | 1080×1920 | 24 or 30fps | 8–20s (LDO002 Template 5 pacing — deliberately shorter than the platform's default allowance) | Burned-in or platform captions, always — sound-off viewing is the majority case |
| TikTok | 9:16 | 1080×1920 | 24 or 30fps | 8–15s (Pillar D is presence, not a tutorial — shorter than platform norm by design) | Same as above |
| LinkedIn native video | 1:1 or 16:9 | 1080×1080 or 1920×1080 | 24 or 30fps | 30–90s (LDO002 Template 6 register — factual, no hook-first editing) | Required — LinkedIn autoplay is sound-off by default |
| Website hero / Brand Film | 16:9 | 3840×2160 master (4K), 1920×1080 delivery | 24fps (cinematic, matches Character Bible pacing doctrine) | 60–90s per LDO003 §4 Phase 2 spec | Optional — hero context assumes sound-on |
| Pinterest (if activated for video) | 9:16 or 2:3 | 1080×1920 | 24 or 30fps | Under 15s | Burned-in captions |

**Delivery codec:** master edit in ProRes 422 HQ or equivalent, retained per Section 7; platform exports in H.264 MP4 at platform-recommended bitrate.

**Colour:** graded to the seasonal register named in the shoot's LDO005 Creative Direction Brief (§11 of that document) — a motion asset does not get a separate grading standard from the stills shot the same day.

---

## 5. Motion-Specific Shoot Briefing Fields

Added to the LDO003 §5 Shoot Planning Template whenever "Deliverables required" includes a motion clip count. Completed alongside, not instead of, the existing template and the LDO005 Creative Direction Brief.

```
Motion Deliverable Name:     [ ]
Category 5 Asset Type:       Short-form (Reels/TikTok) / Brand Film / Retrospective Compilation
Platforms this feeds:        [ from Section 4 table above ]
Duration target:             [ ]
Aspect ratio delivery set:   [ one or more from Section 4 ]
Captions required:           Y / N — platform default per Section 4
Silence interval placement:  [ per Character Bible Module 23 — approximate timestamp or "TBD in edit" ]
Music / sound source:        [ licensed track / original score / room tone only ]
Raw footage retention:       Y (default) — per Section 7
On-set sound register used:  [ per LDO005 §8 ]
```

A motion deliverable briefed without this block is not ready to shoot, on the same principle LDO003 §5 already applies to stills.

---

## 6. Status Tracking Requirement (Register Maintained Separately)

This addendum does not itself track which motion deliverables exist, their status, or their dates — that record is deliberately kept out of a governance document so it can be updated without triggering a governance revision each time a shoot moves from "Briefed" to "Shot." Embedding a status table here was the draft v0.1 approach and has been removed at Farshad's direction.

**What this addendum requires:** an independent Production Register must exist, tracking every Category 5 (Brand Film & Motion) deliverable committed under LDO003 §4, at minimum by deliverable name, phase, and status (Not Started / Briefed / Shot / In Edit / Delivered / Approved). No LDO004 cadence or publishing decision may assume a motion asset is available without checking that register first. This is the entire operational obligation this addendum imposes — this document defines the requirement, nothing more; it does not host, format, or maintain the register.

**Implementation (decided by Farshad Zamani, 2026-09-21):** the Production Register is maintained as a spreadsheet — Excel or Google Sheets — delivered alongside this addendum as `LUNACI_Motion_Production_Status_Register.xlsx`. This choice is deliberately consistent with the governance boundary this addendum draws throughout: the standard document defines the requirement; where the operational data actually lives is delegated to an independent executing tool, not written into governance.

**Future migration:** when the production team and project volume grow, the same field structure migrates to ClickUp or Notion. The spreadsheet's columns (Deliverable Name, Asset Type, LDO003 Phase, Status, Platforms Fed, Target Date, Last Updated, Owner, Blocking/Dependency, Notes) are the structure that should carry over unchanged, so historical entries stay comparable across the migration. This addendum records the migration path; it does not specify a trigger date or owner for executing it.

The Barcelona Walk B-roll and Brand Film — named in Section 1 only to establish why this gap exists — are seeded as the register's first two rows in the spreadsheet, sourced only from LDO004 Addendum 1 §4's existing citation. Their status, owner, and any target date are recorded there, not here.

---

## 7. Asset Library & Naming — Motion Extension

Extends LDO003 §6 (Asset Library & Reuse Governance), which currently defines a naming convention for stills only.

- **Naming convention:** `Motion_[Category]_[Phase]_[ShootDate]_[SequenceNumber]_[AspectRatio]` — e.g., `Motion_BrandFilm_P2_2026-11_001_16x9`.
- **Retention tiers:** raw footage (full session, uncut) retained indefinitely per LDO003 §6's retirement rule; graded master (ProRes/equivalent) retained as the source of truth; platform exports (per Section 4 table) regenerated from the master rather than re-edited, so a codec or caption fix never requires re-grading.
- **Reuse discipline:** unchanged from LDO003 §6 — a finished motion asset may be re-cropped per platform without being treated as a new shoot, but the underlying footage still counts against the phase's shoot-unit quota (LDO003 §4) at the point it was filmed, not at each re-export.

---

## 8. Continuity Rules — Motion Extension

Extends LDO003 §7.

- **Seasonal register continuity applies to motion exactly as it does to stills** (LDO005 §5) — a brand film does not mix seasonal registers within a single edit. **Retrospective Compilation Film exception (resolved 2026-09-21):** LDO005 §5 governs production — what register a shoot day is planned and shot under — not retrospective editing of archival footage from prior phases. The Retrospective Compilation Film's source footage keeps whatever register it was originally shot under (Phase 1's Spring or Winter per LDO005 §10, Phase 2's Autumn); the finished edit is graded to **Autumn**, Phase 3's own assigned register per LDO005 §10 ("What Remains"), as a unifying treatment. This is not an exception to §5 — §5 was never in conflict, since it does not address compilation edits of prior-phase footage. No amendment to LDO005 §5 itself is required.
- **No identity-altering retouching or beauty-filter smoothing in motion**, exactly as LDO003 §7 already states for stills — restated here, not introduced, since motion is sometimes treated in practice as exempt from stills-specific rules.
- **Every motion asset traceable to a Content Pillar**, per LDO003 §7's existing rule.

---

## 9. Open Questions — Resolution Log

All three items raised during drafting (v0.1) were resolved before ratification. This section is kept as the decision record rather than deleted, so the reasoning behind each resolution stays traceable.

1. ~~**Delivery vendor capability**~~ — **Resolved 2026-09-21.** No vendor engaged; Section 4's codec/resolution values stand as the operational default. Revisit only if a future vendor's pipeline conflicts. See Section 4.
2. ~~**Retrospective Compilation Film seasonal continuity**~~ — **Resolved 2026-09-21.** Finished edit graded to Phase 3's own register (Autumn); LDO005 §5 held to govern production, not retrospective compilation. No amendment to LDO005 §5 required. See Section 8.
3. ~~**Production Register implementation**~~ — **Resolved 2026-09-21.** Spreadsheet (Excel/Google Sheets), migrating to ClickUp or Notion when team and volume grow. See Section 6 and the companion file.

**This addendum carried no outstanding open questions into ratification.**

---

## 10. Governing Authority & Precedence

*Effective under Revision 1.0, as an addendum to LDO003, reviewed on the same cadence as LDO003 §8.*

This addendum is subordinate to, and may never override:
- Brand Constitution
- Appendix A — Canonical Definitions & Brand Invariants (LMB003)
- Appendix B — Brand Constants
- Appendix C — Approved & Forbidden Language
- LMB001, LMB002, LMB003
- LUNA Character Bible V4.2 (Modules 13, 18, 23 specifically)
- LDO005 — Creative Direction System (§8 and §10 specifically)
- LDO003 — Visual Asset Production Plan (the document this addendum amends)

It does not alter LDO003's own position relative to LDO002 or LDO004.

---
**STATUS: RATIFIED. Approved by Farshad Zamani, Founder — Patriotic Trade SL, 2026-09-21.**
**END OF LDO003 ADDENDUM 1 — VIDEO & MOTION ASSET PRODUCTION STANDARD**

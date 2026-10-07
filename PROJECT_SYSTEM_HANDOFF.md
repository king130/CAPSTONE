# OJT Intern Path — Project System Handoff

**Document type:** Complete system handoff / project context for another AI developer or reviewer  
**Generated from:** Live codebase inspection (read-only)  
**Repo root:** `CAPSTONE`  
**Related brief:** `SYSTEM_BRIEF_FOR_AI.txt` (older companion; this handoff supersedes it for current feature accuracy)  
**Secrets:** None included. Do not copy `.env` values into prompts.

**Status legend used below**
- **IMPLEMENTED** — Works end-to-end in current code (API + UI or server-enforced)
- **PARTIAL** — Exists but incomplete, UI-only, or limited
- **MOCK/PROTOTYPE** — Present as scaffold/UI/localStorage without full server truth
- **ORPHANED** — Tables/models/files exist but not meaningfully used by live flows
- **NOT IMPLEMENTED** — Safe to say it does not exist for demo/defense
- **NOT CONFIRMED** — Could not fully verify from inspection

---

## 1. Project Identity

| Field | Value |
|--------|--------|
| **Project title** | OJT Intern Path |
| **One-paragraph description** | Multi-tenant web platform connecting schools, companies, and student interns for internship discovery, school–company Agreements/MOAs, school endorsement, company hiring, interview proposals, OJT hour logging, assessments, KPIs/reports, certificates, org verification, RBAC, in-app messaging, and subscription billing via PayMongo. |
| **Target users** | Platform admins; school coordinators / department heads; company HR / admins; student interns |
| **Target organization types** | Schools and companies (tenant organizations) |
| **Main purpose** | Digitize the OJT internship lifecycle from partnership → placement → hours → completion evidence |
| **Current capstone/demo scope** | Runnable web demo of Admin verification → Agreement → student provisioning → internship → course-based soft matching → apply → endorse → accept → interview → OJT → assessment → HTML certificate → Free/paid subscription claims |
| **Explicitly OUT OF SCOPE / future** | Native mobile app; AI/ML recommendation engine; PDF/public certificate verification; dual-signature e-sign; student self-registration; advanced DSS scoring from `dss_*` tables; real-time websockets chat |

---

## 2. Technology Stack

### Frontend — IMPLEMENTED
| Area | Actual |
|------|--------|
| Framework | Vue 3.5 |
| Language | TypeScript |
| Build | Vite 7 |
| Routing | Vue Router 4 (`src/router/index.ts`) |
| State | Pinia 3 (`src/stores/auth.ts`) |
| CSS/UI | Tailwind CSS 3 + local shadcn-like components under `src/components/ui/` |
| API | `apiFetch` / axios Bearer token (`src/services/http.ts`, `apiClient.ts`) |
| Forms | vee-validate + zod |
| Icons | lucide-vue-next, @heroicons/vue |
| Other | vue-sonner, sweetalert2, tom-select, xlsx, @vueuse/core, @tanstack/vue-table |

### Backend — IMPLEMENTED
| Area | Actual |
|------|--------|
| Framework | Laravel 12 |
| Language | PHP ^8.2 |
| Auth | Laravel Sanctum personal access tokens (Bearer) |
| Authorization | Custom RBAC (`OrgPermissions`, `PermissionGate`, roles/permissions tables) — not Spatie |
| ORM | Eloquent |
| Database | SQLite (common local) or MySQL via `.env` — **NOT CONFIRMED** which env is used in every deploy |
| Mail | Laravel Mail (`StudentAccountSetupMail` for setup links) |
| File storage | Local disk (avatars, certificate HTML under private storage) |
| Payments | PayMongo checkout (requires `PAYMONGO_SECRET_KEY` in backend env — do not commit) |
| Notifications | DB-backed `notifications` table + API |
| Real-time | **NOT IMPLEMENTED** for chat — polling only |

### Testing — IMPLEMENTED tooling (do not claim pass/fail here)
| Layer | Tooling |
|-------|---------|
| Frontend unit | Vitest (`npm test`) |
| E2E | Playwright (`npm run test:e2e`) |
| Backend feature | PHPUnit / `php artisan test` under `backend/tests/Feature/` |
| Type-check | `vue-tsc --build` via `npm run type-check` / `npm run build` |
| Build | `npm run build`, optional `npm run deploy:backend-public` |

### Deployment assumptions — PARTIAL
- Local: `php artisan serve` (:8000) + `npm run dev` (:5173); `VITE_API_BASE_URL=http://127.0.0.1:8000/api`
- Production-ish: build SPA → stage into `backend/public/` → serve Laravel
- Queue/mail delivery depends on mail config — setup email may fail silently with fallback message

---

## 3. Project Structure

```
CAPSTONE/
  src/                          Vue SPA
    router/index.ts             Routes + auth/org guards
    stores/auth.ts              Session user
    services/                   Domain API clients
    utils/courseMatch.ts        Soft course matching (frontend)
    layouts/MainLayout.vue      Authenticated shell + theme toggle
    layouts/navigation.ts       Nav per role
    views/                      Role workspaces + auth pages
    components/ui/              Design-system primitives
    components/FloatingChatWidget.vue
    composables/useTheme.ts     Dark mode
    assets/globals.css          CSS variables / dark tokens
    config/auth.ts              AUTH_DISABLED flag
    config/courseCatalog.ts     Course groups / open-to-all label
  backend/
    routes/api.php              REST API
    app/Http/Controllers/       Feature controllers
    app/Http/Middleware/        user.active, org.verified
    app/Models/                 Eloquent models
    app/Services/               Eligibility, billing, RBAC, chat gate, etc.
    app/Support/OrgPermissions.php
    database/migrations/
    database/seeders/           DemoAccountsSeeder, RbacSeeder, …
    tests/Feature/              Security + workflow tests
  e2e/                          Playwright specs
  scripts/stage-frontend.ps1
  package.json                  Frontend scripts
  SYSTEM_BRIEF_FOR_AI.txt       Older brief
```

**Important frontend views:** `Landing`, `Login`, `RegisterSimple`, `AccountSetup`, `FindInternships`, `school/School.vue`, `company/Company.vue`, `intern/Intern.vue`, `Contracts.vue` (Agreements UI), `OJTHours.vue`, `StudentAccounts.vue`, `OrganizationSubscription.vue`, `TenantRbac.vue`, `admin/*`, `Settings.vue` / `SettingsPanel.vue`

**Important backend controllers:** `AuthController`, `AdminController`, `ContractController`, `InternshipController`, `ApplicationController`, `InterviewController`, `OjtLogController`, `AssessmentController`, `CertificateController`, `SchoolStudentController`, `SubscriptionCheckoutController`, `ChatController`, `NotificationController`, `TenantRbacController`, `OrganizationAccessController`

---

## 4. User Roles

### App-level role (`User.role` / `effectiveAppRole`)
| Role | Purpose | Org relationship | Major screens | Restrictions |
|------|---------|------------------|---------------|--------------|
| **admin** | Platform administration | No school/company tenant required | `/admin/*` user mgmt, plans, reports | Not gated by `org.verified`; can approve org verification |
| **school** | School coordinator workspace | Membership on school `Organization` | `/school*`, agreements, students, billing, OJT, access | Needs approved + active org for consequential APIs |
| **company** | Company HR/admin workspace | Membership on company `Organization` | `/dashboard*`, agreements, billing, OJT, access | Same org verification gate |
| **student** (UI: Intern) | Student intern | Linked `Student` + school; optional org membership as `student_member` | `/intern*`, OJT hours, notifications, chat | No public self-register; cannot propose interviews |
| **guest** | Incomplete onboarding | Often none until promoted | `/guest`, role selection, subscription, profile | Limited; promotion to school/company via profile flows |

### Org-scoped RBAC roles — IMPLEMENTED (`RbacSeeder` / `OrgPermissions`)
- Platform: `system_admin`, `platform_super_admin`, `platform_support_admin`
- School: `school_admin`, `school_department_head`, `student_member`
- Company: `company_admin`, `company_hr_manager`

Permission families: View / Manage / Reports / Approve (`org.view_*`, `org.manage_*`, `org.reports_*`, `org.approve_*`) plus legacy aliases.

UI: `/access/roles`, `/access/permissions` → `TenantRbac.vue`

---

## 5. Authentication + Onboarding

| Flow | Status | Notes |
|------|--------|-------|
| Register school/company | **IMPLEMENTED** | `POST /api/auth/register` via org provisioner |
| Register student publicly | **NOT IMPLEMENTED** (blocked) | Server returns 422: school-provisioned only |
| Login / logout | **IMPLEMENTED** | Sanctum token; logout allowed even if disabled |
| Account setup (token) | **IMPLEMENTED** | `/account-setup` + validate/complete APIs |
| Password change | **IMPLEMENTED** | `POST /api/auth/password`; forced via `must_change_password` |
| Email verification (email confirm) | **NOT CONFIRMED** as classic verify-email loop | Setup uses one-time setup token mail |
| Temporary accounts | **IMPLEMENTED** | School-created students: `is_temporary`, `must_change_password` |
| Org verification pending/rejected/approved | **IMPLEMENTED** | Admin updates; middleware `org.verified` |
| Disabled accounts | **IMPLEMENTED** | Middleware `user.active`; UI `/account-disabled` |

**Server-enforced:** student register block, active user, org verification for consequential routes, password policy on register/setup.  
**UI-assisted:** router redirects for verification, must-change-password, role dashboards.  
**AUTH_DISABLED** in `src/config/auth.ts` is currently `false` — leave false for real QA.

---

## 6. Organization System

| Concept | Status |
|---------|--------|
| `Organization` (type school\|company) | **IMPLEMENTED** |
| `School` / `Company` profile rows | **IMPLEMENTED** |
| Memberships + roles | **IMPLEMENTED** |
| Owner (`owner_user_id`) | **IMPLEMENTED** |
| Verification status on school/company | **IMPLEMENTED** (`pending` / `approved` / rejected reason in org settings) |
| `is_active` on organization | **IMPLEMENTED** |
| Courses on org profile (settings/profile JSON) | **IMPLEMENTED** (school/company settings UI) |
| Org billing subscription link | **IMPLEMENTED** |

Missing / limited: full multi-org switching UX beyond memberships payload — **PARTIAL / NOT CONFIRMED** for rich multi-tenant switching UI.

---

## 7. Agreement / MOA System — IMPLEMENTED (single acceptance)

**Lifecycle**
1. School or company creates request (`POST /agreements` or legacy `/contracts`)
2. Partner accepts → status `active` (**single acceptance**, not dual e-signature)
3. Reject → `rejected`; Cancel → `cancelled`
4. Amend → may set `pending_amendment`; partner re-accepts to `active`

| Item | Detail |
|------|--------|
| Permissions | `org.manage_agreements` / `org.approve_agreements` (+ legacy) |
| Notifications | On accept (and related transitions) |
| Models | `Contract` / `Agreement` dual-awareness; table renamed via migration; API dual routes |
| Frontend | `Contracts.vue`, `/agreements`, `/agreements/new`, `/agreements/types` |
| Effect on internships | **Hard eligibility:** company-hosted internship requires **active** agreement between student’s school and company (`InternshipEligibilityService`) |
| Dual signature | **NOT IMPLEMENTED** |
| Free-plan Agreement quota | **NOT IMPLEMENTED** (no agreements limit in `SubscriptionPlanService` serialize limits) |

**Terminology:** User-facing “Agreement”; code/filenames often still say Contract — intentional transition.

---

## 8. Student Provisioning — IMPLEMENTED

**Flow:** School → `/school/students` (`StudentAccounts.vue`) → `POST /api/school-students`  
Creates User + Student, temporary flags, `AccountSetupToken`, emails setup link (`StudentAccountSetupMail`). Student opens `/account-setup`, sets password, then logs in.

| Topic | Status |
|-------|--------|
| Roster create/update/delete/export | **IMPLEMENTED** |
| Resend setup link | **IMPLEMENTED** |
| Subscription student limit enforcement | **IMPLEMENTED** (`wouldExceedAfterIncrement` school.students) |
| Bulk provisioning | **PARTIAL** — export exists; full Excel bulk create **NOT CONFIRMED** as complete |
| Public student registration | **Blocked server-side** |
| Guest→student promotion | **Blocked** in `ProfileController::promoteGuestRole` |

---

## 9. Internship System — IMPLEMENTED

| Topic | Detail |
|-------|--------|
| Who creates | Company (typical) or school-hosted (`host_type`) with permissions |
| Fields | Title, status, eligible courses, slots, allowance, requirements, flexible schedule fields (`schedule_type`, weekly hours, days, time in/out, timezone, `is_flexible`) |
| Agreement requirement | Company-hosted: active MOA with student’s school (eligibility service) |
| School-hosted | Must match student’s school |
| Listing | Public `GET /internships`; student eligible `GET /internships/eligible` |
| Subscription limit | Company internships capped by plan (`company.internships`) |
| Approval workflow for posting | **NOT CONFIRMED** as separate admin approval — status field used (`active`, etc.) |

---

## 10. Course-Based Matching / DSS — CRITICAL

### HARD ELIGIBILITY — **IMPLEMENTED** (backend)
`InternshipEligibilityService`:
- Internship must be `active`
- Student must resolve to a school
- School-hosted: school_id match
- Company-hosted: **active Agreement** between school and company users
- Applied on apply + eligible listing query

Course match does **not** hard-block applications.

### SOFT COURSE MATCHING / DECISION SUPPORT — **IMPLEMENTED** (frontend)
`src/utils/courseMatch.ts` + usage in `Intern.vue` (and related listing UX):
- Levels: `strong` | `related` | `weak` | `open` | `unknown`
- Canonicalization + aliases (e.g. BSIT → BS Information Technology)
- Related group: Information Technology catalog family
- Sort/badge for decision support only

### Safe product claim
> “Course-based decision support for internship matching.”

### What does NOT exist
| Claim | Status |
|-------|--------|
| AI/ML recommendations | **NOT IMPLEMENTED** |
| Advanced DSS scoring engine driving matches | **ORPHANED** — `dss_weights` / `dss_scores` tables + models exist; not wired into live matching |
| Company→school program partner ranking | **NOT IMPLEMENTED** |
| Soft match as server hard gate | **NOT IMPLEMENTED** |

---

## 11. Application Workflow — IMPLEMENTED

```
Student applies (submitted)
  → School endorses (endorsed) or school_rejected
  → Company accepts (accepted) or rejected
```

| Item | Detail |
|------|--------|
| Create | `POST /applications` (auth active; not behind org.verified for student apply) |
| Status updates | `PATCH /applications/{id}/status` with role checks |
| Eligibility | Re-checked via `InternshipEligibilityService` on store |
| Notifications | Submit, endorse, school reject, accept, reject |
| UI | Intern opportunities/applications; School endorsements; Company applicants |
| Bypass protection | Status transition rules + company requires `endorsed` first |

Statuses of note: `submitted`/`pending` → `endorsed` | `school_rejected` → `accepted` | `rejected`                                                                     

---

## 12. Interview System — IMPLEMENTED (limited)

| Capability | Status |
|------------|--------|
| Propose by company/school | **IMPLEMENTED** after application `endorsed` or `accepted` |
| Student propose | **NOT IMPLEMENTED** (403) |
| Confirm | **IMPLEMENTED** (student confirms proposed) |
| Cancel | **IMPLEMENTED** |
| Reject / reschedule workflows | **NOT IMPLEMENTED** as first-class flows |
| Notifications | Propose / confirm / cancel |
| UI | `ApplicationInterviewPanel.vue` on school/company applicant views |

---

## 13. OJT Hours + Progress — IMPLEMENTED

| Topic | Detail |
|-------|--------|
| Create logs | Student `POST /ojt-logs` |
| Accepted application required | **IMPLEMENTED** (`accepted_application_required`) |
| Hours | Computed from time in/out |
| Statuses | pending → approved / rejected |
| Approvers | Company/school with `org.approve_ojt_logs` (admin can review) |
| Required hours | Org setting `required_ojt_hours` via `GET/PATCH /ojt-logs/required-hours` — **school/company only** (admin forbidden) |
| Progress | `GET /ojt-logs/progress`, school summaries, student progress object |
| Application scoping | Certificate/hour checks use application_id scope |
| Multiple accepted apps | Latest accepted by `updated_at` if application_id omitted |
| UI | `OJTHours.vue` |

---

## 14. Assessment — PARTIAL (persist yes; student UI no)

| Topic | Status |
|-------|--------|
| Create by company/school | **IMPLEMENTED** (`AssessmentController`, `org.manage_assessments`) |
| Tied to application | **IMPLEMENTED** |
| Student API list of own assessments | **IMPLEMENTED** (index filter) |
| Student assessment viewing UI | **NOT IMPLEMENTED** (no Intern.vue usage) |
| UI for staff | `ApplicationAssessmentPanel.vue` in School/Company dialogs |

**Demo phrasing:** “Assessment persistence works; student assessment viewing is not part of the current demo.”

---

## 15. Certificate — PARTIAL

| Topic | Status |
|-------|--------|
| Issuer | School (permission `org.approve_certificates`) |
| Prerequisite | Accepted placement + enough **application-scoped** approved OJT hours vs org required hours |
| Output | **School-issued HTML** stored on local disk; download returns `text/html` |
| Numbering | `Certificate::generateCertificateNumber` |
| PDF | **NOT IMPLEMENTED** |
| Public verification portal | **NOT IMPLEMENTED** |
| Student certificate list/download UI | **NOT IMPLEMENTED** |

---

## 16. Subscription / Billing — IMPLEMENTED (with caveats)

### Plans (from plan definitions migration seed)
| Plan | School price | Company price | School limits | Company limits |
|------|--------------|---------------|---------------|----------------|
| **Free** | ₱0 | ₱0 | 1 coordinator, 5 students | 1 account, 3 internships |
| **Standard** | ₱1999 | ₱2499 | 5 coordinators, 100 students | 5 accounts, 999 internships |
| **Premium** | ₱3999 | ₱4999 | 999 / 999 | 999 / 999 |

**Actually enforced server-side limits (serializePlan):**  
`school.coordinators`, `school.students`, `company.accounts`, `company.internships`  
**Agreement/MOA limits:** **NOT IMPLEMENTED**

| Topic | Status |
|-------|--------|
| Free plan available | **IMPLEMENTED** |
| Paid display | **IMPLEMENTED** |
| PayMongo checkout | **IMPLEMENTED** when secret configured |
| Fake success without payment | **NOT** the design — verify path expects PayMongo |
| Pending cancel / switch to Free | **IMPLEMENTED** (`cancel-pending`, `subscriptions/free`) |
| Unpaid/pending subscription treated as Free limits | **IMPLEMENTED** in `getPlanForOrganization` |
| Marketing feature strings (“advanced matching algorithms”) | Marketing copy only — **do not claim as implemented DSS** |

UI: `/billing` → `OrganizationSubscription.vue` + `SubscriptionManager.vue`

---

## 17. Notifications — IMPLEMENTED

- Model/table: `notifications`
- API: `GET /notifications`, `PATCH /notifications/{id}/read`
- UI: `NotificationBell.vue`, `/notifications`
- Preferences UI: Settings (largely **localStorage** preferences — **PARTIAL** vs server)

**Known producers (non-exhaustive):** application submit/endorse/accept/reject; agreement active; interview propose/confirm/cancel; OJT approve/reject; org verification-related admin flows; subscription overage notifications (service); certificate — **NOT CONFIRMED** for every event.

---

## 18. Chat / Messaging — IMPLEMENTED (server-backed polling)

| Topic | Status |
|-------|--------|
| UI | `FloatingChatWidget.vue` (full-screen panel + FAB) |
| Mount | `App.vue` for student/school/company |
| API | `/conversations`, messages, send, mark read |
| Authorization | `ChatRelationshipGate` — school↔company (agreement), student↔school, student↔company (relationship rules) |
| Storage | `conversations`, `conversation_participants`, `messages` |
| Transport | **Polling** (`setInterval`), not websockets |
| Attachments / emoji | UI stubs disabled |
| Unread | Badge on FAB + sidebar |
| Dark mode / FAB polish | Soft spots (Messenger-like styling) — polish backlog |
| Mock/localStorage chat | Live path is API; leftover e2e XSS org names may pollute directory |

Migration `2026_10_04_000001_create_chat_tables` must be applied or chat SQL-fails.

---

## 19. UI/UX Architecture

- Shell: `MainLayout.vue` sidebar + header (notifications, theme, avatar)
- Role nav: `navigation.ts`
- Dashboards: School/Company/Intern multi-section SPAs
- Design system: Tailwind + `components/ui/*` (Card, Button, Dialog, Table, …)
- Forms: vee-validate patterns in Settings/Register
- Chat launcher: FAB bottom-right
- Responsive: layout collapses to sheet nav; chat mobile hides conversation sidebar

---

## 20. Dark Mode — PARTIAL / visibly incomplete

| Topic | Detail |
|-------|--------|
| Toggle | MainLayout header Sun/Moon |
| Storage | `ojt-color-mode` via `@vueuse/core` `useColorMode` on `html.dark` |
| Tailwind | `darkMode: ['class']` |
| Tokens | `globals.css` `:root` + `.dark` HSL variables |
| Working | Layout shell / many UI primitives using `bg-background`, `bg-card`, etc. |
| Broken | Most role pages hard-code `bg-white`, `text-slate-950`, `border-slate-200` with almost no `dark:` variants (exception: `Alert.vue`) |
| Chat widget | Hard-coded light CSS |

---

## 21. Routes / Navigation (major)

| Path | Role | Purpose | Component |
|------|------|---------|-----------|
| `/` | public | Landing | Landing.vue |
| `/login` | public | Login | Login.vue |
| `/register`, `/register/school\|company` | public | Org signup | RegisterSimple.vue |
| `/account-setup` | public token | Student setup | AccountSetup.vue |
| `/find-internships` | public/student | Browse | FindInternships.vue |
| `/organization-verification` | school/company pending | Verification waiting | OrganizationVerification.vue |
| `/school*` | school | School workspace sections | School.vue |
| `/school/students` | school | Provision roster | StudentAccounts.vue |
| `/dashboard*` | company | Company workspace | Company.vue |
| `/intern*` | student | Intern workspace | Intern.vue |
| `/agreements*` | school/company | MOA | Contracts.vue / ManageContractTypes / NewContractRequest |
| `/ojt-hours` | auth non-guest | Hours | OJTHours.vue |
| `/billing` | school/company | Subscription | OrganizationSubscription.vue |
| `/access/roles\|permissions` | org staff | RBAC | TenantRbac.vue |
| `/admin/*` | admin | Platform admin | Admin views |
| `/settings` | auth | SettingsPanel | Settings.vue |
| `/notifications` | auth | Notifications page | Notifications.vue |

---

## 22. API Map (important endpoints)

### Auth
| Method | Path | Purpose | Authz |
|--------|------|---------|-------|
| POST | `/auth/register` | School/company/guest register; **blocks student** | Public |
| POST | `/auth/login` | Issue token | Public |
| POST | `/auth/logout` | Revoke | Sanctum |
| GET | `/auth/me` | Profile | Active user |
| POST | `/auth/password` | Change password | Active user |
| POST | `/auth/account-setup/validate\|complete` | Setup token | Public |

### Admin / Orgs
| Method | Path | Notes |
|--------|------|-------|
| GET/PATCH | `/admin/users`, `/admin/users/{user}` | Verification/active updates |
| * | Most org actions | Behind `org.verified` |

### Students
| Method | Path | Notes |
|--------|------|-------|
| CRUD-ish | `/school-students*` | Provision, resend, export; student limit |

### Agreements
| Method | Path | Notes |
|--------|------|-------|
| * | `/agreements*` and `/contracts*` | Dual; accept/reject/cancel/amend |

### Internships / Applications
| Method | Path | Notes |
|--------|------|-------|
| GET | `/internships`, `/internships/eligible`, `/internships/{id}` | Public show/index; eligible auth |
| POST/PATCH/DELETE | `/internships*` | Org verified |
| POST/GET/PATCH status | `/applications*` | Eligibility + status machine |

### Interviews / OJT / Assessments / Certificates
| Method | Path | Notes |
|--------|------|-------|
| * | `/interviews*` | Propose/confirm/cancel |
| * | `/ojt-logs*`, `/ojt-logs/progress`, `/ojt-logs/required-hours` | Hours + org required hours |
| * | `/assessments*` | Staff create; student can index own |
| * | `/certificates*` | Generate/download HTML |

### Chat / Notifications / Billing
| Method | Path | Notes |
|--------|------|-------|
| * | `/conversations*` | Relationship-gated |
| * | `/notifications*` | List/read |
| * | `/subscriptions/checkout\|verify\|free\|cancel-pending` | PayMongo |
| GET | `/subscription-plans` | Public plan list |

---

## 23. Database / Domain Model (conceptual)

```
User
 ├─ platformRole?
 ├─ organizationMemberships → Organization ←→ Subscription
 ├─ School | Company | Student
Organization.settings → verification, pending_plan_change, required_ojt_hours, …
Agreement/Contract (school_user ↔ company_user, status)
Internship (company_id | school_id, host_type, eligible courses, schedule…)
Application → ApplicationInterview, ApplicationAssessment, OjtLog, Certificate
Notification (user_id)
Conversation ↔ ConversationParticipant ↔ Message
AccountSetupToken
SubscriptionPlanDefinition
ORPHANED for live matching: dss_weights, dss_scores
```

---

## 24. Security / Authorization Status (known implemented — not a new audit)

- Sanctum Bearer auth
- `user.active` middleware on authenticated APIs (logout exempt)
- `org.verified` on consequential school/company routes
- Custom RBAC + `PermissionGate`
- Tenant membership checks on org resources
- Student public registration blocked
- Internship eligibility / agreement gate on apply
- Interview propose gated to endorsed/accepted; students cannot propose
- OJT requires accepted application
- Certificate hours scoped to application_id
- Subscription limit checks server-side for students/internships/accounts/coordinators
- Chat relationship gate
- Feature tests cover many of the above

---

## 25. Testing Status (inventory only — not executed for this handoff)

### Backend Feature suites (examples)
`OjtLogIntegrityTest`, `InterviewWorkflowTest`, `InternshipApplicationEligibilityTest`, `InternshipAuthorizationTest`, `StudentOnboardingHardeningTest`, `OrganizationVerification*`, `SubscriptionBillingSecurityTest`, `ChatApiAuthorizationTest`, `ChatDatabaseFoundationTest`, `TenantRbacSecurityTest`, `NotificationApiTest`, `NotificationProducerTest`, `DisabledUserTokenEnforcementTest`, org access security tests, etc.

### Frontend
- Vitest including `courseMatch.test.ts`
- Playwright: register/account-creation (+ XSS non-execution check), password policy

### Commands (for future agents)
- `cd backend && php artisan test`
- `npm test` / `npm run type-check` / `npm run build` / `npm run test:e2e`

**This handoff did not run tests.**

---

## 26. Known Limitations / Future Work

### P0 — Demo blockers
- None inherent if: migrations applied (incl. chat tables), DB seeded, API+SPA running, presenter creates Agreement + internship live (seed does not include MOA/internship by default)

### P1 — Important but non-blocking
- Dark mode inconsistent across dashboards
- Chat FAB/Messenger styling polish
- Admin System Settings “OJT hours” removed from admin — org-scoped only (school/company)
- Certificate HTML-only; no student cert UI
- Assessment no student UI
- Setup email depends on mail config
- Residual Contract naming in code
- Marketing plan feature strings overclaim advanced matching

### P2 — Future enhancements
- AI/ML / advanced DSS using `dss_*`
- Company↔school recommendation ranking
- Student-initiated interviews / reject / reschedule
- PDF certificates + public verification
- WebSocket realtime chat
- Attachments in chat
- Native mobile app
- Dual-signature MOA

---

## 27. Actual Demo Workflow (recommended)

| Step | Actor | Screen | Action | Expected |
|------|-------|--------|--------|----------|
| 1 | Admin | `/admin` users | Show school/company verification | Orgs approved (seeded already approved) |
| 2 | School | `/agreements/new` | Request MOA with company | Pending agreement |
| 3 | Company | `/agreements` | Accept | Active agreement |
| 4 | Company | Dashboard jobs | Create internship + eligible courses | Active posting |
| 5 | Student | `/intern/opportunities` | Show course match badges → Apply | Application submitted |
| 6 | School | Endorsements | Endorse | Endorsed |
| 7 | Company | Applicants | Accept → Propose interview | Accepted + proposed interview |
| 8 | Student | Notifications / applications | Confirm interview | Confirmed |
| 9 | Student | `/ojt-hours` | Submit log | Pending |
| 10 | Company | `/ojt-hours` | Approve | Progress updates |
| 11 | Company | Applicant dialog | Save assessment | Persisted |
| 12 | School | Placement/reports | Issue certificate | HTML certificate |
| 13 | School/Company | `/billing` | Show Free limits / paid plans | Limits displayed; no fake paid success |

Use seeded demo accounts from `DemoAccountsSeeder` (do not paste passwords into public docs; retrieve from seeder when presenting privately).

---

## 28. Safe Claims for Capstone Defense

### A. Claims you CAN safely make
1. Multi-tenant platform for schools, companies, and school-provisioned students  
2. Admin verifies organizations before protected org features unlock  
3. Active Agreements/MOAs gate company internship eligibility  
4. Students are school-provisioned (no public student self-registration)  
5. Course-based decision support (match badges + soft ordering) on eligible opportunities  
6. Application pipeline: apply → school endorsement → company accept/reject  
7. Staff can propose interviews; students confirm/cancel; notifications fire  
8. OJT hours require accepted application; approved hours drive progress  
9. Staff can persist assessments on applications  
10. Schools can issue HTML completion certificates when application-scoped hours meet org requirement  
11. Free plan enforces student/internship (and coordinator/account) limits; paid upgrades use PayMongo when configured  
12. Relationship-gated in-app messaging with unread polling  

### B. Claims you SHOULD NOT make
1. Free plan limits Agreements/MOA  
2. AI/ML or advanced DSS scoring / partner ranking  
3. Dual-signature electronic MOA  
4. Student-initiated interview requests / formal reject/reschedule  
5. PDF certificates or public certificate verification  
6. Student assessment or certificate portal as shipped UI  
7. Real-time websocket chat  
8. Successful paid upgrade without real PayMongo  
9. That `dss_scores` / `dss_weights` power the live matcher  
10. That dark mode is fully polished across all dashboards  

---

## 29. Important File Reference Map

### Auth
→ FE: `src/stores/auth.ts`, `src/services/auth.ts`, `src/router/index.ts`, `src/config/auth.ts`, `Login.vue`, `AccountSetup.vue`  
→ BE: `AuthController.php`, `EnsureUserIsActive.php`  
→ Tests: `DisabledUserTokenEnforcementTest`, `StudentOnboardingHardeningTest`, `GuestProfileRolePromotionTest`  
→ Notes: Student register blocked; Sanctum Bearer

### Organizations
→ FE: `OrganizationVerification.vue`, RegisterSimple, SettingsPanel courses  
→ BE: `AdminController`, `OrganizationAccessController`, `EnsureOrganizationVerified`  
→ Tests: `OrganizationVerification*`  
→ Notes: pending/approved/rejected

### Agreements
→ FE: `Contracts.vue`, NewContractRequest, ManageContractTypes  
→ BE: `ContractController`, Contract/Agreement models, rename migration  
→ Tests: eligibility tests covering agreement_required  
→ Notes: single accept; dual API paths

### Students
→ FE: `StudentAccounts.vue`  
→ BE: `SchoolStudentController`, `AccountSetupToken`, `StudentAccountSetupMail`  
→ Tests: `SchoolRosterAuthorizationTest`, `StudentSchoolAttachmentSecurityTest`, `StudentOnboardingHardeningTest`

### Internships
→ FE: Company/School/Intern/FindInternships views  
→ BE: `InternshipController`, `InternshipEligibilityService`  
→ Tests: `InternshipAuthorizationTest`, `InternshipApplicationEligibilityTest`

### Course Matching
→ FE: `src/utils/courseMatch.ts`, `Intern.vue`, `courseCatalog.ts`  
→ BE: N/A for soft match (hard eligibility separate)  
→ Tests: `courseMatch.test.ts`  
→ Notes: soft DSS only; `dss_*` orphaned

### Applications
→ FE: Intern/School/Company sections  
→ BE: `ApplicationController`  
→ Tests: eligibility + authorization suites

### Interviews
→ FE: `ApplicationInterviewPanel.vue`  
→ BE: `InterviewController`  
→ Tests: `InterviewWorkflowTest`  
→ Notes: no student propose

### OJT
→ FE: `OJTHours.vue`, SettingsPanel org hours  
→ BE: `OjtLogController`, `StudentProgressController`  
→ Tests: `OjtLogIntegrityTest`  
→ Notes: required hours school/company only

### Assessment
→ FE: `ApplicationAssessmentPanel.vue` (school/company)  
→ BE: `AssessmentController`  
→ Tests: covered indirectly / suite inventory  
→ Notes: no student UI

### Certificate
→ FE: School.vue generate action; `services/certificates.ts`  
→ BE: `CertificateController`  
→ Tests: `OjtLogIntegrityTest` certificate cases  
→ Notes: HTML only

### Subscription
→ FE: `OrganizationSubscription.vue`, `SubscriptionManager.vue`, `Subscription.vue`  
→ BE: `SubscriptionCheckoutController`, `SubscriptionPlanService`, plan definitions migration  
→ Tests: `SubscriptionBillingSecurityTest`  
→ Notes: no MOA limit; PayMongo for paid

### Notifications
→ FE: `NotificationBell.vue`, `Notifications.vue`  
→ BE: `NotificationController` + producers in domain controllers  
→ Tests: `NotificationApiTest`, `NotificationProducerTest`

### Chat
→ FE: `FloatingChatWidget.vue`, `App.vue`, `services/chat.ts`  
→ BE: `ChatController`, `ChatRelationshipGate`, chat migration  
→ Tests: `ChatApiAuthorizationTest`, `ChatDatabaseFoundationTest`  
→ Notes: polling; migrate chat tables

### Dark Mode
→ FE: `useTheme.ts`, `globals.css`, `MainLayout.vue`, `tailwind.config.ts`  
→ BE: N/A  
→ Notes: shell OK; pages mostly light-locked

### Global UI/Layout
→ FE: `MainLayout.vue`, `navigation.ts`, `components/ui/*`, `App.vue`  
→ Notes: AUTH_DISABLED must stay false for realistic demos

---

## 30. Final System Status

**PROJECT STATUS**
- **Core OJT journey:** IMPLEMENTED and demoable  
- **Demo readiness:** PASS WITH MINOR ISSUES (start servers, migrate/seed, create Agreement+internship live)  
- **Major implemented features:** Org verification, Agreements, school student provisioning, internships + hard eligibility, soft course match UI, applications, interviews, OJT+progress, assessments (staff), HTML certificates, subscriptions/PayMongo path, notifications, relationship-gated chat, RBAC  
- **Partial features:** Dark mode, chat visual polish, student assessment/certificate UI, some marketing copy vs reality, mail-dependent setup delivery  
- **Known future work:** Advanced DSS/ML, PDF/public certs, realtime chat, dual-sign MOA, mobile app  
- **P0 blockers:** None for core journey if environment prepared  
- **Recommended next development focus (polish, not another audit):** Chat launcher visual polish **or** dark-mode semantic class pass on top dashboards — one focused UI step at a time  

---

## Handoff meta

- This document is context only; it is not a request to change product code.
- Prefer additive changes; keep Agreement terminology in UI; keep `/contracts` aliases until fully retired.
- Do not commit secrets; do not enable `AUTH_DISABLED` for demos.
- When serving SPA from Laravel `public/`, rebuild after frontend changes.

**FILES CHANGED:** `PROJECT_SYSTEM_HANDOFF.md` (this handoff document only)  
**APPLICATION CODE CHANGED:** NONE  
**TESTS RUN:** NONE  
**SECRETS EXPOSED:** NONE

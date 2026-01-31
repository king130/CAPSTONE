# Backend Architecture (Laravel + Firebase)

This project uses **Laravel** for the REST API, RBAC, DSS, and reporting, and **Firebase** for file storage plus optional auth and realtime notifications.

## Stack Overview
- **API + RBAC + DSS + Reports:** Laravel (REST)
- **Database:** MySQL or PostgreSQL (Laravel migrations)
- **File storage:** Firebase Storage (PDFs, permits, endorsements)
- **Optional auth:** Firebase Auth (Google/Email) if needed
- **Realtime notifications (optional):** Firebase Cloud Messaging (FCM)

## Core Modules (Laravel)
- **Auth & RBAC**
  - Roles: `student`, `company`, `school`, `coordinator`, `admin`
  - Permissions enforced by middleware and policies
  - JWT or Laravel Sanctum for API tokens
- **User Profiles**
  - Student, Company, School metadata
  - Verification status and supporting documents
- **Internships**
  - Posting, approval, capacity, and status
- **Applications**
  - Apply → review → approve/reject → onboarding
- **Documents**
  - Metadata in DB, files in Firebase Storage
- **Time Logs & Journals**
  - Student submission, company/school validation
- **Evaluations**
  - Rubrics, scoring, completion criteria
- **DSS Engine**
  - Weighted scoring with configurable weights
- **Reports**
  - Placement stats, completion rates, KPI summaries

## Firebase Usage
- **Storage**
  - Store files in Firebase Storage
  - Save metadata in Laravel DB:
    - `file_url`, `file_type`, `file_size`, `owner_id`, `category`, `verified_at`
- **Optional Auth**
  - Use Firebase Auth if you want federated login
  - Sync user identity with Laravel via `firebase_uid`
- **Realtime Notifications (optional)**
  - Use FCM to send push notifications
  - Store notification records in Laravel DB for audit/read tracking

## Suggested Tables (Core)
- `users`
- `roles`, `permissions`, `role_user`
- `students`, `companies`, `schools`
- `internships`
- `applications`
- `documents`
- `time_logs`
- `journals`
- `evaluations`
- `dss_weights`, `dss_scores`
- `notifications`
- `reports` (or generated views)

## API Design (High-Level)
- `POST /auth/register`
- `POST /auth/login`
- `GET /users/me`
- `GET /internships`, `POST /internships`
- `POST /applications`, `PATCH /applications/{id}/status`
- `POST /documents`, `GET /documents`
- `POST /dss/match`
- `GET /reports/summary`

## File Upload Flow
1. Frontend requests signed upload or direct upload to Firebase
2. File uploaded to Firebase Storage
3. Frontend sends file metadata to Laravel
4. Laravel saves DB record and links to entity

## DSS Integration (Laravel Service)
- Inputs: student profile, company requirements, location
- Output: ranked internships + match score
- Store scores in `dss_scores` for audit and reporting

## Notes
- If using **Laravel Auth only**, Firebase is limited to **Storage + FCM**.
- If using **Firebase Auth**, Laravel treats Firebase token as identity and manages RBAC internally.

# Firebase Setup Guide

## ⚠️ CRITICAL: Deploy Firestore Rules FIRST

The `firestore.rules` file in this project **must be deployed** to Firebase before registration will work.

**Without deployed rules, you will get:**
- ✅ Registration appears to succeed (Firebase Auth user created)
- ❌ Login fails with "User role is missing" (Firestore document blocked)
- ❌ "User profile not found in database"

### Quick Deploy

```bash
# Install Firebase CLI (if not installed)
npm install -g firebase-tools

# Login to Firebase
firebase login

# Deploy rules and indexes
firebase deploy --only firestore
```

### Why this is required

When users register, the app:
1. ✅ Creates Firebase Auth user (succeeds)
2. ❌ Writes to Firestore `users/{uid}` (fails with 400 if rules not deployed)

Error without deployed rules:
```
POST accounts:signUp → 400 (Bad Request)
Missing or insufficient permissions
```

### Alternative: Console Upload

1. Go to https://console.firebase.google.com/project/capstone-6515a/firestore/rules
2. Copy contents of `firestore.rules`
3. Paste and **Publish**

---

## Required Firestore Indexes

Two composite indexes are required:

### Index 1: School Subscription Validation
- Collection: `users`
- Fields: `role`, `subscription.subscriptionCode`, `subscription.status`
- Used in: Student registration with school code

### Index 2: Temporary Accounts Query
- Collection: `users`
- Fields: `isTemporary`, `createdAt (desc)`
- Used in: Admin dashboard temporary accounts view

These are defined in `firestore.indexes.json` and deployed automatically with:
```bash
firebase deploy --only firestore
```

---

## Enable Email/Password Authentication

1. Go to https://console.firebase.google.com/project/capstone-6515a/authentication/providers
2. Click **Email/Password**
3. Toggle **Enable**
4. Click **Save**

---

## Environment Variables

Ensure `.env` contains:
```
VITE_FIREBASE_API_KEY=AIzaSyBR_ZmU77pSuKrAeJPpT8Zzua7NEEdnVLY
VITE_FIREBASE_AUTH_DOMAIN=capstone-6515a.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=capstone-6515a
VITE_FIREBASE_STORAGE_BUCKET=capstone-6515a.firebasestorage.app
VITE_FIREBASE_MESSAGING_SENDER_ID=428598999965
VITE_FIREBASE_APP_ID=1:428598999965:web:e4022a961d5aa30ab58d74
```

After updating `.env`, restart dev server:
```bash
npm run dev
```

---

## Troubleshooting: "User role is missing" Error

### Problem
You registered successfully but get "User role is missing" when logging in.

### Cause
Firestore rules were not deployed, so:
- Firebase Auth created your user ✅
- Firestore document creation was blocked ❌
- You exist in Auth but NOT in Firestore

### Solution 1: Deploy Rules and Re-register
```bash
firebase deploy --only firestore
```
Then delete test users in Firebase Console and register again.

### Solution 2: Manually Create Firestore Document
1. Go to: https://console.firebase.google.com/project/capstone-6515a/authentication/users
2. Copy your user's **UID**
3. Go to: https://console.firebase.google.com/project/capstone-6515a/firestore/data
4. Create document in `users` collection with **Document ID = your UID**
5. Add fields:
   ```
   role: "student"
   email: "your-email@example.com"
   displayName: "Your Name"
   isActive: true
   isTemporary: false
   createdAt: (timestamp - use current time)
   profile: (map - add empty object {})
   ```
6. Save and try logging in again

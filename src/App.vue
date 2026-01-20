<script setup lang="ts"></script>

<template>
  <div class="page">
    <div class="card">
      <!-- Left Illustration Panel -->
      <div class="left">
        <h2>Let’s get Started!</h2>

        <div class="illustration">
          <div class="person">
            <div class="head"></div>
            <div class="body"></div>
          </div>

          <div class="doc doc-main"></div>
          <div class="doc doc-small"></div>
        </div>
      </div>

      <!-- Right Login Panel -->
      <div class="right">
        <div class="icon">
          <span></span>
          <span></span>
          <span></span>
        </div>

        <h1>Welcome!</h1>
        <p class="subtitle">Log in to manage opportunities and application</p>

        <form>
          <input type="email" placeholder="Email Address:" />
          <input type="password" placeholder="Password:" />

          <div class="row">
            <label>
              <input type="checkbox" />
              Remember me
            </label>
            <a href="#">Forgot password</a>
          </div>

          <button type="submit">Log In</button>

          <p class="signup">
            Don’t have an account?
            <a href="#">Sign up</a>
          </p>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts"></script>

<style scoped>
/* Page background */
.page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background:
    radial-gradient(circle at bottom left, #9ec5ff 0 30%, transparent 31%),
    radial-gradient(circle at top right, #9ec5ff 0 25%, transparent 26%), #f5f7fb;
  font-family:
    Inter,
    system-ui,
    -apple-system,
    BlinkMacSystemFont;
}

/* Main Card */
.card {
  width: 960px;
  height: 520px;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  display: grid;
  grid-template-columns: 1fr 1fr;
  overflow: hidden;
}

/* Left Side */
.left {
  padding: 48px;
}

.left h2 {
  color: #2563eb;
  font-size: 20px;
  margin-bottom: 40px;
}

.illustration {
  position: relative;
  width: 100%;
  height: 280px;
  background: #f1f5ff;
  border-radius: 10px;
}

/* Simple abstract illustration */
.person {
  position: absolute;
  left: 40px;
  bottom: 40px;
}

.person .head {
  width: 36px;
  height: 36px;
  background: #1e40af;
  border-radius: 50%;
  margin: auto;
}

.person .body {
  width: 60px;
  height: 80px;
  background: #3b82f6;
  border-radius: 8px;
  margin-top: 6px;
}

.doc {
  position: absolute;
  background: white;
  border-radius: 6px;
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
}

.doc-main {
  width: 110px;
  height: 140px;
  right: 60px;
  top: 50px;
}

.doc-small {
  width: 70px;
  height: 90px;
  right: 20px;
  bottom: 40px;
}

/* Right Side */
.right {
  padding: 56px 48px;
  text-align: center;
}

.icon {
  width: 40px;
  height: 24px;
  margin: 0 auto 12px;
  position: relative;
}

.icon span {
  position: absolute;
  width: 10px;
  height: 10px;
  background: #2563eb;
  border-radius: 50%;
}

.icon span:nth-child(1) {
  left: 0;
}
.icon span:nth-child(2) {
  left: 15px;
}
.icon span:nth-child(3) {
  left: 30px;
}

.right h1 {
  font-size: 26px;
  margin-bottom: 6px;
}

.subtitle {
  color: #6b7280;
  font-size: 14px;
  margin-bottom: 28px;
}

form {
  text-align: left;
}

input[type='email'],
input[type='password'] {
  width: 100%;
  padding: 12px 14px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
  margin-bottom: 16px;
  font-size: 14px;
}

.row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  margin-bottom: 20px;
}

.row a {
  color: #2563eb;
  text-decoration: none;
}

button {
  width: 100%;
  padding: 12px;
  background: #2563eb;
  border: none;
  border-radius: 6px;
  color: white;
  font-weight: 600;
  cursor: pointer;
}

.signup {
  text-align: center;
  font-size: 13px;
  margin-top: 14px;
}

.signup a {
  color: #2563eb;
  font-weight: 500;
  text-decoration: none;
}
</style>

<style scoped></style>

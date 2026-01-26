<script setup lang="ts">
import { RouterLink } from 'vue-router'
import Navbar from '@/components/Navbar.vue'
</script>

<template>
  <div class="page">
    <Navbar />

    <main>
      <section class="hero">
        <div class="hero-text">
          <p class="eyebrow">Find the Right Internship</p>
          <h1>Hire the Right Talent. Smarter and Faster.</h1>
          <p class="muted">
            A web-based internship platform that helps students find the right opportunities, schools manage OJT programs, 
            and companies hire qualified interns using a decision support system and mobile application.
          </p>
          
        </div>
        <div class="hero-visual">
          <div class="hero-blob"></div>
          <img src="/undraw_working-remotely_ivtz-1024x815.webp" alt="Illustration" />
        </div>
      </section>

      <section id="features" class="section features">
        <div class="pill">Features</div>
        <h2 class="center">Everything You Need for Internship Management</h2>
        <p class="center muted">
          Powerful tools integrated into one seamless workflow.
        </p>

        <!-- Main Feature Block -->
        <div class="main-feature-block">
          <div class="main-feature-icon">
            <img src="/icons/logo-main.png" alt="Smart Matching" class="main-feature-img" />
          </div>
          <div class="main-feature-content">
            <h3>Smart Internship Matching Decision Support System</h3>
            <p>
              The platform uses a decision support system to intelligently match students with internship opportunities based on their course, skills, performance data, and company requirements, ensuring better and more suitable internship placements.
            </p>
          </div>
        </div>

        <!-- Feature Cards Grid -->
        <div class="feature-grid">
          <div class="feature-card">
            <div class="feature-icon">
              <img src="/icons/icon-company.png" alt="Performance" class="feature-icon-img" />
            </div>
            <h4>Performance Evaluation & KPI Tracking</h4>
            <p>Evaluates intern performance using predefined KPIs.</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">
              <img src="/icons/icon-student.png" alt="Attendance" class="feature-icon-img" />
            </div>
            <h4>Attendance & Task Monitoring</h4>
            <p>Tracks attendance and internship activities in real time.</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">
              <img src="/icons/icon-school.png" alt="Documents" class="feature-icon-img" />
            </div>
            <h4>MOA, Contract & Document Management</h4>
            <p>Manages internship agreements and required documents.</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">
              <img src="/icons/icon-student.png" alt="Certificate" class="feature-icon-img" />
            </div>
            <h4>Certificate & Internship Completion</h4>
            <p>Generates verified certificates after internship completion.</p>
          </div>
        </div>
      </section>

      <section id="why" class="section">
        <p class="eyebrow center">Why Students, Schools, and Companies Choose Our Platform</p>
        <h2 class="center">Complete visibility and collaboration in one place</h2>
        <div class="cards three">
          <div class="card">
            <h3>Students</h3>
            <p>Personalized matches, streamlined applications, faster placements.</p>
          </div>
          <div class="card">
            <h3>Schools</h3>
            <p>Real-time oversight, reporting, and centralized coordination for cohorts.</p>
          </div>
          <div class="card">
            <h3>Companies</h3>
            <p>Curated candidates, easy scheduling, and faster hiring decisions.</p>
          </div>
        </div>
      </section>

      <section id="benefits" class="section benefits">
        <div class="pill">Benefits</div>
        <h2 class="center">Why Students, Schools, and Companies Choose Our Platform</h2>
        <p class="center muted">
          We combine cutting-edge technology with human-centric design to transform your hiring experience.
        </p>

        <div class="benefit-grid three">
          <div class="benefit-card">
            <div class="icon-circle">
              <img src="/icons/icon-student.png" alt="Students" class="benefit-icon-img" />
            </div>
            <h4>Students</h4>
            <p>Find internships aligned with skills and career goals.</p>
          </div>
          <div class="benefit-card">
            <div class="icon-circle">
              <img src="/icons/icon-school.png" alt="Schools" class="benefit-icon-img" />
            </div>
            <h4>Schools</h4>
            <p>Manage OJT programs and monitor student progress.</p>
          </div>
          <div class="benefit-card">
            <div class="icon-circle">
              <img src="/icons/icon-company.png" alt="Companies" class="benefit-icon-img" />
            </div>
            <h4>Companies</h4>
            <p>Hire and manage interns efficiently.</p>
          </div>
        </div>

        <div class="benefit-grid two">
          <div class="benefit-card">
            <div class="icon-circle">
              <img src="/icons/logo-main.png" alt="Smart Decisions" class="benefit-icon-img" />
            </div>
            <h4>Smarter Decisions</h4>
            <p>DSS-powered internship matching.</p>
          </div>
          <div class="benefit-card">
            <div class="icon-circle">
              <img src="/icons/icon-student.png" alt="Easy Access" class="benefit-icon-img" />
            </div>
            <h4>Easy Access</h4>
            <p>Web and mobile platform support.</p>
          </div>
        </div>
      </section>

      <section id="features" class="section alt">
        <p class="eyebrow center">Everything You Need for Internship Management</p>
        <h2 class="center">Smarter decisions. Less busywork.</h2>
        <div class="cards two">
          <div class="card">
            <h3>Matching & Routing</h3>
            <p>Smart matching to connect the right students with the right roles automatically.</p>
          </div>
          <div class="card">
            <h3>Progress & Feedback</h3>
            <p>Track evaluations, requirements, and documents in one shared workspace.</p>
          </div>
        </div>
        <div class="cards two">
          <div class="card">
            <h3>Communication</h3>
            <p>Keep schools, students, and companies aligned with shared updates and alerts.</p>
          </div>
          <div class="card">
            <h3>Certificates & Reports</h3>
            <p>Automate completion proofs and export reports for stakeholders in seconds.</p>
          </div>
        </div>
      </section>

      <section id="steps" class="section process">
        <div class="process-pill">Process</div>
        <h2 class="center">Internship Made Simple for Everyone</h2>
        <p class="center muted">Follow simple steps to proceed</p>

        <div class="process-track">
          <div class="process-node">
            <div class="process-circle">1</div>
            <h4>Register</h4>
            <p>Students, schools, and companies create verified accounts.</p>
          </div>
          <div class="process-node">
            <div class="process-circle">2</div>
            <h4>Match &amp; Apply</h4>
            <p>Students are matched to internships based on skills and course fit.</p>
          </div>
          <div class="process-node">
            <div class="process-circle">3</div>
            <h4>Monitor &amp; Evaluate</h4>
            <p>Schools and companies track attendance, tasks, and performance.</p>
          </div>
          <div class="process-node">
            <div class="process-circle">4</div>
            <h4>Complete &amp; Certify</h4>
            <p>Internships finish and verified certificates are issued.</p>
          </div>
        </div>
      </section>

      <section id="cta" class="cta">
        <div class="cta-content">
          <h2>Ready to start your internship journey?</h2>
          <p>Join students, schools, and companies already using OJT Path.</p>
          <div class="cta-actions">
            <RouterLink to="/register" class="btn btn-primary">Get Started</RouterLink>
            <RouterLink to="/login" class="btn btn-ghost">Login</RouterLink>
          </div>
        </div>
      </section>
    </main>

    <footer class="footer">
      <div class="logo">
        <img src="/icons/logo-main.png" alt="OJT Path" class="logo-icon" />
        <span class="logo-text">OJT Path</span>
      </div>
      <div class="foot-links">
        <a href="#why">Why Us</a>
        <a href="#features">Features</a>
        <a href="#steps">How It Works</a>
        <RouterLink to="/login">Login</RouterLink>
      </div>
      <small>© {{ new Date().getFullYear() }} OJT Path. All rights reserved.</small>
    </footer>
  </div>
</template>

<style scoped src="../styles/Landing.css">
</style>


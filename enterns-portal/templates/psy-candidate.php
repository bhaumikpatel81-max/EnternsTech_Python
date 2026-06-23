<?php
/**
 * Psychometric assessment — candidate-facing template.
 * Loaded via [enp_psychometric] shortcode.
 * Token is read from ?t= query param.
 * Multi-step flow is driven by psychometric.js.
 *
 * SECURITY: This template renders ONLY the shell. All question data
 * comes via AJAX after token validation. correct/reverse_scored are
 * never present in any JS payload or HTML.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$token = sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) );
?>
<div id="enp-psy-root" class="enp-psy" data-token="<?php echo esc_attr( $token ); ?>">

  <!-- ═══════════════════ INVALID / EXPIRED STATE ═══════════════════ -->
  <div id="enp-psy-invalid" class="enp-psy__screen" style="display:none">
    <div class="enp-psy__card enp-psy__card--center">
      <div class="enp-psy__icon">&#128274;</div>
      <h1 class="enp-psy__title"><?php esc_html_e( 'This link is no longer valid', 'enterns-portal' ); ?></h1>
      <p class="enp-psy__body">
        <?php esc_html_e( 'The assessment link you followed has expired or has already been used. Please contact Enterns Tech for a new link.', 'enterns-portal' ); ?>
      </p>
    </div>
  </div>

  <!-- ═══════════════════ LOADING STATE ════════════════════════════ -->
  <div id="enp-psy-loading" class="enp-psy__screen enp-psy__screen--loading">
    <div class="enp-psy__spinner" role="status" aria-label="<?php esc_attr_e( 'Loading…', 'enterns-portal' ); ?>"></div>
  </div>

  <!-- ═══════════════════ STEP 1: WELCOME ══════════════════════════ -->
  <div id="enp-psy-welcome" class="enp-psy__screen" style="display:none">
    <div class="enp-psy__card">
      <div class="enp-psy__brand">
        <span class="enp-psy__brand-name">Enterns Tech</span>
        <span class="enp-psy__brand-tag"><?php esc_html_e( 'Professional Assessment', 'enterns-portal' ); ?></span>
      </div>
      <h1 class="enp-psy__title enp-psy__title--lg">
        <?php esc_html_e( 'Professional Suitability Assessment', 'enterns-portal' ); ?>
      </h1>
      <div class="enp-psy__meta-grid">
        <div class="enp-psy__meta-item">
          <span class="enp-psy__meta-icon">&#9200;</span>
          <span><?php esc_html_e( '25–30 minutes', 'enterns-portal' ); ?></span>
        </div>
        <div class="enp-psy__meta-item">
          <span class="enp-psy__meta-icon">&#128196;</span>
          <span><?php esc_html_e( '8 sections', 'enterns-portal' ); ?></span>
        </div>
        <div class="enp-psy__meta-item">
          <span class="enp-psy__meta-icon">&#128241;</span>
          <span><?php esc_html_e( 'Mobile-friendly', 'enterns-portal' ); ?></span>
        </div>
      </div>
      <div class="enp-psy__infobox">
        <p><?php esc_html_e( 'This assessment helps us understand your strengths, preferences, and working style. There are no right or wrong answers — answer honestly for the most useful profile.', 'enterns-portal' ); ?></p>
        <p><?php esc_html_e( 'Your responses are confidential and used solely for placement and development purposes.', 'enterns-portal' ); ?></p>
      </div>
      <p class="enp-psy__consent">
        <?php esc_html_e( 'By proceeding, you consent to Enterns Tech processing your responses in line with our privacy policy.', 'enterns-portal' ); ?>
      </p>
      <button id="enp-psy-begin-btn" class="enp-psy__btn enp-psy__btn--primary enp-psy__btn--lg">
        <?php esc_html_e( 'Begin Assessment', 'enterns-portal' ); ?> &rarr;
      </button>
    </div>
  </div>

  <!-- ═══════════════════ STEP 2: CANDIDATE DETAILS ════════════════ -->
  <div id="enp-psy-details" class="enp-psy__screen" style="display:none">
    <div class="enp-psy__card">
      <div class="enp-psy__step-label"><?php esc_html_e( 'Your Details', 'enterns-portal' ); ?></div>
      <h2 class="enp-psy__title"><?php esc_html_e( 'Tell us about yourself', 'enterns-portal' ); ?></h2>
      <p class="enp-psy__body"><?php esc_html_e( 'This information helps us tailor your assessment and match you with the right opportunities.', 'enterns-portal' ); ?></p>

      <form id="enp-psy-details-form" novalidate>
        <div class="enp-psy__form-grid">
          <div class="enp-psy__form-group">
            <label for="psy-name" class="enp-psy__label">
              <?php esc_html_e( 'Full name', 'enterns-portal' ); ?> <span class="enp-psy__required" aria-hidden="true">*</span>
            </label>
            <input type="text" id="psy-name" name="candidate_name" class="enp-psy__input"
                   autocomplete="name" required>
          </div>
          <div class="enp-psy__form-group">
            <label for="psy-email" class="enp-psy__label">
              <?php esc_html_e( 'Email address', 'enterns-portal' ); ?> <span class="enp-psy__required" aria-hidden="true">*</span>
            </label>
            <input type="email" id="psy-email" name="candidate_email" class="enp-psy__input"
                   autocomplete="email" required>
          </div>
          <div class="enp-psy__form-group">
            <label for="psy-phone" class="enp-psy__label">
              <?php esc_html_e( 'Contact number', 'enterns-portal' ); ?> <span class="enp-psy__required" aria-hidden="true">*</span>
            </label>
            <input type="tel" id="psy-phone" name="candidate_phone" class="enp-psy__input"
                   autocomplete="tel" required>
          </div>
          <div class="enp-psy__form-group">
            <label for="psy-edu" class="enp-psy__label">
              <?php esc_html_e( 'Highest education level', 'enterns-portal' ); ?> <span class="enp-psy__required" aria-hidden="true">*</span>
            </label>
            <select id="psy-edu" name="education_level" class="enp-psy__select" required>
              <option value=""><?php esc_html_e( '— please select —', 'enterns-portal' ); ?></option>
              <option value="1"><?php esc_html_e( 'School-leaving / A-levels / equivalent', 'enterns-portal' ); ?></option>
              <option value="2"><?php esc_html_e( 'Diploma / Associate degree', 'enterns-portal' ); ?></option>
              <option value="3"><?php esc_html_e( "Bachelor's degree", 'enterns-portal' ); ?></option>
              <option value="4"><?php esc_html_e( 'Postgraduate / Master\'s / PhD', 'enterns-portal' ); ?></option>
            </select>
          </div>
          <div class="enp-psy__form-group">
            <label for="psy-field" class="enp-psy__label">
              <?php esc_html_e( 'Field of interest / programme', 'enterns-portal' ); ?> <span class="enp-psy__required" aria-hidden="true">*</span>
            </label>
            <select id="psy-field" name="field" class="enp-psy__select" required>
              <option value=""><?php esc_html_e( '— please select —', 'enterns-portal' ); ?></option>
              <option value="IT"><?php esc_html_e( 'Information Technology', 'enterns-portal' ); ?></option>
              <option value="DATA_AI"><?php esc_html_e( 'Data Science & AI', 'enterns-portal' ); ?></option>
              <option value="BUSINESS"><?php esc_html_e( 'Business & Management', 'enterns-portal' ); ?></option>
              <option value="HR"><?php esc_html_e( 'Human Resources', 'enterns-portal' ); ?></option>
              <option value="FINANCE"><?php esc_html_e( 'Finance & Accounting', 'enterns-portal' ); ?></option>
              <option value="MARKETING"><?php esc_html_e( 'Marketing & Communications', 'enterns-portal' ); ?></option>
              <option value="INFRA"><?php esc_html_e( 'Infrastructure & Cloud', 'enterns-portal' ); ?></option>
              <option value="INFOSEC"><?php esc_html_e( 'Information Security', 'enterns-portal' ); ?></option>
              <option value="HONORS"><?php esc_html_e( 'Honors / Academic Track', 'enterns-portal' ); ?></option>
            </select>
          </div>
        </div>

        <div id="enp-psy-details-error" class="enp-psy__error-msg" style="display:none" role="alert"></div>

        <div class="enp-psy__form-actions">
          <button type="submit" id="enp-psy-details-submit" class="enp-psy__btn enp-psy__btn--primary">
            <?php esc_html_e( 'Continue', 'enterns-portal' ); ?> &rarr;
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══════════════════ STEP 3: INSTRUCTIONS ═════════════════════ -->
  <div id="enp-psy-instructions" class="enp-psy__screen" style="display:none">
    <div class="enp-psy__card">
      <div class="enp-psy__step-label"><?php esc_html_e( 'Before You Start', 'enterns-portal' ); ?></div>
      <h2 class="enp-psy__title"><?php esc_html_e( 'How the assessment works', 'enterns-portal' ); ?></h2>
      <div class="enp-psy__instructions-list">
        <div class="enp-psy__instr-item">
          <div class="enp-psy__instr-icon">1&ndash;5</div>
          <div>
            <strong><?php esc_html_e( 'Rating scale (Likert)', 'enterns-portal' ); ?></strong>
            <p><?php esc_html_e( 'Rate each statement from 1 (Strongly Disagree) to 5 (Strongly Agree). Choose the option that best reflects you — not what you think is "ideal".', 'enterns-portal' ); ?></p>
          </div>
        </div>
        <div class="enp-psy__instr-item">
          <div class="enp-psy__instr-icon">A/B</div>
          <div>
            <strong><?php esc_html_e( 'Preference choice', 'enterns-portal' ); ?></strong>
            <p><?php esc_html_e( 'Pick the option that is most like you. Both may apply — choose whichever fits better.', 'enterns-portal' ); ?></p>
          </div>
        </div>
        <div class="enp-psy__instr-item">
          <div class="enp-psy__instr-icon">1–N</div>
          <div>
            <strong><?php esc_html_e( 'Ranking', 'enterns-portal' ); ?></strong>
            <p><?php esc_html_e( 'Drag to rank items in order of importance to you (1 = most important). On mobile, use the numbered input next to each item.', 'enterns-portal' ); ?></p>
          </div>
        </div>
        <div class="enp-psy__instr-item">
          <div class="enp-psy__instr-icon">&#9679;&nbsp;&#9679;&nbsp;&#9679;&nbsp;&#9679;</div>
          <div>
            <strong><?php esc_html_e( 'Multiple choice', 'enterns-portal' ); ?></strong>
            <p><?php esc_html_e( 'Select the single best answer from the options given.', 'enterns-portal' ); ?></p>
          </div>
        </div>
        <div class="enp-psy__instr-item">
          <div class="enp-psy__instr-icon">&#9998;</div>
          <div>
            <strong><?php esc_html_e( 'Open questions', 'enterns-portal' ); ?></strong>
            <p><?php esc_html_e( 'Write a short, honest response. There is no word limit — a few sentences is fine.', 'enterns-portal' ); ?></p>
          </div>
        </div>
      </div>
      <div class="enp-psy__infobox enp-psy__infobox--sm">
        <strong><?php esc_html_e( 'Tips:', 'enterns-portal' ); ?></strong>
        <?php esc_html_e( 'Your progress saves automatically. If you close the browser, your answers will be waiting when you return using the same link.', 'enterns-portal' ); ?>
      </div>
      <div class="enp-psy__form-actions">
        <button id="enp-psy-start-btn" class="enp-psy__btn enp-psy__btn--primary">
          <?php esc_html_e( "Let's go", 'enterns-portal' ); ?> &rarr;
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════ STEP 4: SECTION RUNNER ═══════════════════ -->
  <div id="enp-psy-runner" class="enp-psy__screen" style="display:none">

    <!-- Progress bar -->
    <div class="enp-psy__progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
      <div id="enp-psy-progress-fill" class="enp-psy__progress-fill"></div>
    </div>
    <div class="enp-psy__progress-label">
      <span id="enp-psy-section-label"></span>
      <span id="enp-psy-progress-pct"></span>
    </div>

    <!-- Autosave indicator -->
    <div id="enp-psy-autosave-indicator" class="enp-psy__autosave" aria-live="polite"></div>

    <!-- Section container (populated by JS) -->
    <div id="enp-psy-section-container" class="enp-psy__card enp-psy__card--runner">
      <div class="enp-psy__section-header">
        <div id="enp-psy-section-num" class="enp-psy__section-num"></div>
        <h2 id="enp-psy-section-title" class="enp-psy__section-title"></h2>
        <p id="enp-psy-section-desc" class="enp-psy__section-desc"></p>
      </div>
      <div id="enp-psy-questions" class="enp-psy__questions"></div>
      <div id="enp-psy-runner-error" class="enp-psy__error-msg" style="display:none" role="alert"></div>
      <div class="enp-psy__nav-row">
        <button id="enp-psy-back-btn" class="enp-psy__btn enp-psy__btn--ghost" style="display:none">
          &larr; <?php esc_html_e( 'Back', 'enterns-portal' ); ?>
        </button>
        <button id="enp-psy-next-btn" class="enp-psy__btn enp-psy__btn--primary">
          <?php esc_html_e( 'Next', 'enterns-portal' ); ?> &rarr;
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════ STEP 5: REVIEW ════════════════════════════ -->
  <div id="enp-psy-review" class="enp-psy__screen" style="display:none">
    <div class="enp-psy__card enp-psy__card--center">
      <div class="enp-psy__icon enp-psy__icon--check">&#10003;</div>
      <h2 class="enp-psy__title"><?php esc_html_e( "You've answered all sections", 'enterns-portal' ); ?></h2>
      <p class="enp-psy__body">
        <?php esc_html_e( "Take a moment to review — once you submit you won't be able to make changes.", 'enterns-portal' ); ?>
      </p>
      <div id="enp-psy-review-summary" class="enp-psy__review-summary"></div>
      <div id="enp-psy-submit-error" class="enp-psy__error-msg" style="display:none" role="alert"></div>
      <div class="enp-psy__form-actions enp-psy__form-actions--center">
        <button id="enp-psy-submit-btn" class="enp-psy__btn enp-psy__btn--primary enp-psy__btn--lg">
          <?php esc_html_e( 'Submit Assessment', 'enterns-portal' ); ?>
        </button>
        <button id="enp-psy-back-to-runner" class="enp-psy__btn enp-psy__btn--ghost">
          &larr; <?php esc_html_e( 'Go back to questions', 'enterns-portal' ); ?>
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════ STEP 6: THANK YOU ════════════════════════ -->
  <div id="enp-psy-thankyou" class="enp-psy__screen" style="display:none">
    <div class="enp-psy__card enp-psy__card--center">
      <div class="enp-psy__icon enp-psy__icon--done">&#127881;</div>
      <h1 class="enp-psy__title enp-psy__title--lg">
        <?php esc_html_e( 'Thank you!', 'enterns-portal' ); ?>
      </h1>
      <p class="enp-psy__body">
        <?php esc_html_e( 'Your assessment has been submitted successfully. Our team will be in touch with you regarding next steps.', 'enterns-portal' ); ?>
      </p>
      <p class="enp-psy__body" style="margin-top:.5rem">
        <?php esc_html_e( 'You may now close this window.', 'enterns-portal' ); ?>
      </p>
    </div>
  </div>

</div><!-- #enp-psy-root -->

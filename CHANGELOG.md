# Changelog

## 0.1.2 (2026-10-18)

- Removed the moving dots-and-lines particle animation from the homepage hero. Pages already set up need no update step: the old animation element is hidden by the theme.

## 0.1.1 (2026-10-18)

- The repository is public: updates work without a GitHub token, and the "Connect updates" notice is gone (an optional token box remains for a private repository).
- No email address ships in the theme any more: the contact form recipient is set under Settings → General (empty = site admin email).

## 0.1.0 (2026-10-18)

- First release, built from the approved HTML design (`redesign/ionpower/`).
- Bright one-page homepage with the original ionpower.info copy, photos, stats and video, at the original section anchors (`#about`, `#cavitation`, `#a-zerogrid-solution`, …).
- Hero: one large photo (power lines at dusk) that zooms out very slowly, ion particle field, line-by-line headline.
- Motion: ticker band, quote deck, scroll-lit About quote, rotating "Everywhere. Everyone. Everything.", count-up stats, animated cavitation chamber, zero-grid diagram, production timeline, click-to-play video, bento gallery with lightbox, transmission-loss slider.
- New pages: Contact Us (original form), Privacy Policy, Terms and Conditions, Cookie Policy (drafts for owner review). Old `/pages/cookie-policy` redirects (301) to `/cookie-policy/`.
- Every page is a real editable WordPress page created by the setup button; Appearance → ION Power Pages updates pages to newer designs.
- Contact form saved under Enquiries and emailed; honeypot, minimum time and rate-limit spam protection.
- Private-repository GitHub updater: token pasted once under Appearance → ION Power Updates (stored in the site database only).

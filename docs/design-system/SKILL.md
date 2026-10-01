---
name: rock-admin-design
description: Use this skill to generate well-branded interfaces and assets for Rock Admin — a universal admin portal framework. Contains design guidelines, color/type tokens, CSS, and React components for building e-commerce, CMS, CRM, monitoring, and other admin portals.
user-invocable: true
---

Read the README.md file within this skill, and explore the other available files.

If creating visual artifacts (admin screens, prototypes, mocks, etc.), copy assets out and create static HTML files for the user to view. Load ../../styles.css for tokens, ../../_ds_bundle.js for components.

Key design rules from README.md:
- Two dark sticky navbars (navy #0d1117 + #161b26) above a light gray-blue page (#f0f2f7)
- White surfaces for cards, tables, modals
- Inter font (Google Fonts), 14px base, tight letter-spacing on headings
- Electric Blue primary (#2563eb), semantic colors for status badges
- Pulsing nav badges for live counts
- No gradients, no emoji, no colored left-border card accents

Component namespace: `window.RockAdminDesignSystem_fced52`

If the user invokes this skill without other guidance, ask:
1. What type of admin portal? (e-commerce, CMS, CRM, monitoring, custom?)
2. Which screens are needed?
3. Real data/content, or placeholder?
4. Any project-specific branding on top of Rock Admin?

Then act as an expert admin UI designer — output complete HTML prototypes or production-ready component code.

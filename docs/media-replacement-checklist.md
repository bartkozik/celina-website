# Media and resource replacement checklist

The original project bundle contained no standalone production photography and
no downloadable Corporate Resources files. CEL-21 and CEL-22 recovered the
client-selected photography for all ten managed pages from the read-only client
WordPress audit. Resource unavailable states remain deliberate and safe.

## Before replacing an image

Page photographs shipped in the theme are registered in Media Library by the
[managed media migration](managed-content.md#editable-media-migration).
Gallery photographs have native attachment IDs, including the original
Leadership portraits. Adding a photograph must retain every current portrait,
its caption, and its position. Reopen the editor after saving to verify this.

- Confirm CELINA owns or is licensed to use the final asset.
- Upload through the WordPress Media Library; do not hotlink a production or
  third-party URL.
- Use an appropriately sized modern format where the target hosting supports
  it; avoid uploading an unnecessarily large original.
- Write concise, contextual alternative text for meaningful images.
- Mark a purely decorative image as decorative and leave alt text empty.
- Keep the existing crop/aspect intent and preview desktop plus mobile.
- Confirm the page has no horizontal overflow and the image is not stretched.
- Re-run browser/accessibility checks after a batch of replacements.

## Remaining photography decisions

- The four articles confirmed for CEL-24 (CELINA Turns 30, ATA Tent Training,
  One CELINA Brand, and the recycled-rubble foundation story) use explicit
  local placeholders. Their legacy image paths were unavailable in the audit;
  do not substitute unrelated page photography. Replace only with Natalie’s
  supplied or otherwise approved master assets.
- “Thirty Years Built by People”: historical, employee, community, and
  leadership photographs remain outside the CEL-22 page-media scope.
- Contextual alternative text still requires the CEL-9 accessibility sign-off
  where the client Media Library did not provide approved metadata.

The source-of-truth mapping is maintained in
content-sources.md (document excluded from this source export).

## Resource downloads

For each Resource Item:

1. obtain the approved, public document from CELINA;
2. confirm its title, revision/date, file type, and accessibility;
3. upload it to the Media Library;
4. select it in the Resource Item and provide an unambiguous link label;
5. preview and download the file in a logged-out browser;
6. remove superseded files according to CELINA's retention policy.

If any file or approval is missing, leave the block unconfigured. The explicit
unavailable state is correct; never substitute `#`, an invented path, or a
private document.

## Release verification

- No request goes to a placeholder host or unapproved image CDN.
- Non-leading content images retain lazy loading.
- Hero imagery is not forced to lazy-load.
- The canonical Contact page may request the official HubSpot embed; newly
  inserted HubSpot blocks remain request-free until configured.
- The Careers Paycor block uses
  the approved public ID `portfolio-unconfigured`; retain its direct
  fallback link so it remains usable if the script is blocked.
- Production CSP includes `script-src https://recruitingbypaycor.com` and
  `frame-src https://recruitingbypaycor.com`; smoke-test the embed from an
  allowed `example.invalid` parent domain after deployment.
- Legacy BambooHR blocks remain request-free and should be replaced with Paycor.
- `pnpm test:e2e:content` passes at mobile and desktop widths.
- Production Core Web Vitals are rechecked only after real media, hosting, and
  CDN configuration exist.

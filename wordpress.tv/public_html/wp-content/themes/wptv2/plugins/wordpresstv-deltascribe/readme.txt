=== DeltaScribe Captioning Integration ===

Adds a "Caption with DeltaScribe" link to video pages, sending contributors to a
DeltaScribe (https://github.com/[owner]/deltascribe) instance pre-loaded with the
video, and accepting finished captions back into the same pending-attachment
moderation queue used by wordpresstv-upload-subtitles.

== How it works ==

1. `caption-with-deltascribe-template.php` collects a WordPress.org username, email,
   and language (same fields as the existing "Subtitle this video" flow).
2. `wordpresstv_deltascribe_start` (admin-post.php) validates that, builds an
   HMAC-signed, expiring `submit` callback URL, and redirects out to the configured
   DeltaScribe instance with `media`, `submit`, `lang`, and `format=ttml` query args.
3. DeltaScribe (a purely client-side app) loads the video, lets the contributor time
   captions, and POSTs the finished TTML as JSON to the `submit` URL.
4. `wordpresstv_deltascribe_submit` (admin-post.php) verifies the HMAC token, saves
   the TTML as a pending attachment, and tags it with the same `_wptv_submitted_subtitles`
   postmeta shape the existing plugin uses — so it shows up in the normal Media Library
   moderation screen with zero changes there.

== Configuration ==

Settings -> General -> "DeltaScribe URL" controls which DeltaScribe instance
contributors are sent to. Defaults to https://deltascribe.stephanis.me/ if unset.

== Why the DeltaScribe URL is configurable rather than bundled ==

DeltaScribe (https://github.com/[owner]/deltascribe) is a purely client-side
React/Vite app — it never uploads media or subtitles anywhere itself, and the
integration relies entirely on it being loaded in the contributor's own browser
and POSTing back to us. That means it doesn't strictly need to be hosted
separately: it could be added as a git submodule under this theme and built +
served same-origin at, say, `/deltascribe/`.

That approach was considered for this integration and intentionally deferred:

* This is an initial prototype, and the externally-hosted instance is expected
  to change URLs during that phase — an admin-configurable settings field
  covers that without a deploy.
* wordpress.tv's deploy pipeline (`.github/workflows/dev.yaml`) currently ships
  this theme as plain PHP/CSS/JS with no Node/Vite build step. Bundling
  DeltaScribe would require adding a full JS build stage to that pipeline
  (`npm install && npm run build` producing static assets to serve from
  `/deltascribe/`), plus routing (e.g. a rewrite rule so `/deltascribe/` serves
  its `index.html`) and submodule bookkeeping (`git submodule update --init`
  on deploy, pinning/updating the submodule commit on each DeltaScribe release).

If DeltaScribe's API/URL params stabilize, bundling it same-origin is a
reasonable follow-up and would simplify this integration meaningfully: the
cross-origin CORS preflight handling and the HMAC-signed callback token in
`wordpresstv-deltascribe.php` both exist specifically to make the cross-origin
POST safe, and neither would be needed for a same-origin submodule build —
a plain nonce-verified admin-post handler would do. The `submitted_via` /
`_wptv_submitted_subtitles` postmeta contract, the bridge-page collection
flow, and the moderation pipeline this plugin feeds into would all stay
exactly as they are today either way.

To switch later: add DeltaScribe as a submodule (e.g. `git submodule add
<deltascribe-repo-url> wp-content/themes/wptv2/deltascribe`), add a build step
to `.github/workflows/dev.yaml`, add a rewrite so `/deltascribe/` resolves to
its built `index.html`, update `WordPressTV_DeltaScribe::get_url()` to point
at the local path by default, and simplify/remove the CORS + HMAC handling in
the `_submit` callback in favor of a standard WordPress nonce.

# How we would like reports written

Reports are read by the WordPress Security Team, who know the platform and the bug classes and read many reports a
day. Keep every verifiable fact and drop everything else.

## Layout and length

- Under 1,000 words.
- A title in the form "<bug class> in <component> allows <attacker action>", for example "Stored XSS in the Plugin
  Directory readme parser allows a plugin committer to run script for reviewers".
- Then, in this order, skipping any section there is nothing for: a summary of up to 100 words; one numbered entry per
  independent defect; how the defects chain, if they do; reproduction steps; impact in up to 50 words; the severity
  from threat_model.md.

## What every report should state

- One sentence of the form: <actor> can <action> against <target>, which they otherwise could not.
- An explicit actor / already holds / gains line. Say how the actor gets their access on that site: no account, a
  newly registered account, or owning an approved plugin or theme.
- Whether a default production setup is affected, or the finding needs a setting, a mock, or local-only behavior.
- The affected file and function, as paths under /src.
- A self-contained reproduction against the local sites: the `wporg run <site> wp ...` setup, then `curl` requests or
  a PHPUnit test, with the site's URL from `wporg sites`. Steps the reader can follow without asking questions.

## Style

- Plain and neutral: no hedging, no emphasis for effect, no stock openers, no background the reader already knows, and
  no rebuttals to expected objections.
- State each fact once, in its most specific place. Keep any uncertainty exactly as it is.
- One root cause per report. When the same defect has twins on other paths (the REST and AJAX versions of one action,
  for example), list them in the same report.
- Redact live secrets and other people's personal data, and say what was removed.
- American English.

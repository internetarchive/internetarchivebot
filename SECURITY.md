# Security policy

## Reporting a vulnerability

Please report security problems privately. **Don't** open a public GitHub issue or Phabricator task, or post about them on a wiki talk page.

To report one, open the repository's **Security** tab and click **Report a vulnerability**, or go straight to:

https://github.com/internetarchive/internetarchivebot/security/advisories/new

Only the maintainers can see the report. Please include:

- what the problem is and which part of IABot it affects
- the steps to reproduce it, or a proof of concept
- what an attacker could do with it

## What's in scope

- The code in this repository
- InternetArchiveBot as it runs on Wikimedia wikis
- The IABot Management Interface and its API at iabot.wmcloud.org

Only the current `master` branch is supported. We don't patch older versions.

## What's out of scope

- Problems in MediaWiki or on Wikimedia sites themselves. Report those to Wikimedia: https://www.mediawiki.org/wiki/Reporting_security_bugs
- Problems in the Wayback Machine or archive.org. Report those to the Internet Archive.
- Problems in third-party dependencies that don't affect IABot. Report those upstream.

## While you test

- Don't make bot edits or interface changes on live wikis to test a finding. Use your own install or a test wiki.
- Don't access, change or keep other users' data beyond what you need to show the problem.
- Don't run tests that could slow down or take down the bot, the interface or the wikis.

## What happens next

We aim to reply within a week. We'll keep you updated while we work on a fix and tell you when it's released. If you'd like, we'll credit you in the published security advisory.

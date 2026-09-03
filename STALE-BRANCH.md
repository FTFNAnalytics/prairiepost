# This branch is not production

`claude/prairie-post-news-site-hiffgl` is a stale line of the network:
nine papers, an obsolete config-file tenant mapping, and deployment
runbooks that no longer describe the live system.

**Production is `claude/master-dashboard-control-room-nr3mp4`** — sixteen
papers plus the Civis Media hub, database-row tenant mapping, CI, and
the VPS tooling. All new work lands there via pull request.

Do not base new papers, runbooks, or fixes on this branch, and never
check this branch out inside the production release directory: it would
regress every live paper. A duplicate London Lookout briefly merged
here (#65) has been reverted; londonlookout.com serves the master
line's paper 16.

# Account Service Redirect Issue

## Summary
The Account Service callback redirect is not occurring as expected. Users complete login, but the browser does not navigate back to the intended page after authentication.

## Impact
- Login flow stalls after Account Service authentication.
- Users are not returned to the intended page.
- Blocks testing and further work that depends on the redirect flow.

## Expected Behavior
After successful Account Service login:
- The user is redirected to the callback URL.
- The plugin processes the JWT, stores session data, and then redirects to the original return URL (or dashboard).

## Actual Behavior
- The redirect does not happen after login.
- The user is not returned to the callback/return URL.

## Current Status
- Investigation is in progress by another developer.
- Work on dependent tasks is paused until redirect tests can be completed.

## Required Tests (Per `DEVELOPMENT-PLAN.md`)
- Flush permalinks (Settings → Permalinks → Save) to register `/palm-reading/auth/callback`.
- Log in via Account Service and confirm callback redirect works.
- Verify JWT validation succeeds and session persists.
- Verify Account Service login URL uses `/account/login?redirect_url={callback_url}` (no `service`, `callback`, or `redirect` params).
- Confirm post-login redirect returns to the originally requested page via session-stored return URL.

## Notes
- If the redirect failure is upstream (Account Service), verify that it honors `redirect_url` and that the callback URL is publicly accessible.
- If the redirect failure is local, confirm the rewrite rule exists and WordPress is resolving `/palm-reading/auth/callback`.

## Next Steps
- Re-run the required tests once the redirect issue is resolved.
- Update `DEVELOPMENT-PLAN.md` with test results and unblock related tasks.
